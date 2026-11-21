import { ConnectionError, LoyvoError, parseError } from "./errors.js";
import { VERSION } from "./version.js";

export interface ClientOptions {
  /** Your Loyvo API key (`lvo_live_…` or `lvo_test_…`). Defaults to the LOYVO_API_KEY environment variable. */
  apiKey?: string;
  /** Defaults to https://api.loyvo.lk. */
  baseUrl?: string;
  /** Per-request timeout in milliseconds. Default 30000. */
  timeout?: number;
  /** Retries for rate limits, and for server/network errors when a retry is safe. Default 2. */
  maxRetries?: number;
  /** A fetch implementation (defaults to the global fetch, Node 18+). */
  fetch?: typeof fetch;
}

export interface RequestOptions {
  query?: Record<string, string | number | undefined>;
  body?: unknown;
  /** Makes the request safe to retry (SMS sends). */
  idempotencyKey?: string;
  /** May be retried after a server or network error. GETs always are. */
  retrySafe?: boolean;
  /** Return the raw response body as bytes. */
  binary?: boolean;
}

export class HttpClient {
  readonly apiKey: string;
  readonly baseUrl: string;
  private readonly timeout: number;
  private readonly maxRetries: number;
  private readonly fetch: typeof fetch;

  constructor(opts: ClientOptions = {}) {
    const key = opts.apiKey ?? (typeof process !== "undefined" ? process.env?.LOYVO_API_KEY : undefined);
    if (!key) throw new LoyvoError("Missing API key: pass { apiKey } or set LOYVO_API_KEY.", { status: 0, code: "missing_api_key" });
    this.apiKey = key;
    this.baseUrl = (opts.baseUrl ?? "https://api.loyvo.lk").replace(/\/+$/, "");
    this.timeout = opts.timeout ?? 30_000;
    this.maxRetries = Math.max(0, opts.maxRetries ?? 2);
    const f = opts.fetch ?? globalThis.fetch;
    if (!f) throw new LoyvoError("No fetch available: use Node 18+ or pass { fetch }.", { status: 0, code: "no_fetch" });
    this.fetch = f;
  }

  async request<T>(method: string, path: string, opts: RequestOptions = {}): Promise<T> {
    const url = new URL(this.baseUrl + path);
    for (const [k, v] of Object.entries(opts.query ?? {})) if (v !== undefined && v !== "") url.searchParams.set(k, String(v));

    const headers: Record<string, string> = {
      Authorization: `Bearer ${this.apiKey}`,
      Accept: opts.binary ? "*/*" : "application/json",
      "User-Agent": `loyvo-node/${VERSION}`,
    };
    let body: string | undefined;
    if (opts.body !== undefined) {
      headers["Content-Type"] = "application/json";
      body = JSON.stringify(opts.body);
    }
    if (opts.idempotencyKey) headers["Idempotency-Key"] = opts.idempotencyKey;
    const retrySafe = method === "GET" || !!opts.idempotencyKey || !!opts.retrySafe;

    for (let attempt = 0; ; attempt++) {
      let res: Response;
      try {
        res = await this.fetch(url, { method, headers, body, signal: AbortSignal.timeout(this.timeout) });
      } catch (err) {
        // Not reached, or no answer: retry only when that can't do something twice.
        if (retrySafe && attempt < this.maxRetries) {
          await sleep(backoff(attempt));
          continue;
        }
        const timedOut = (err as Error)?.name === "TimeoutError" || (err as Error)?.name === "AbortError";
        throw new ConnectionError(timedOut ? `Request timed out after ${this.timeout} ms` : `Could not reach Loyvo: ${(err as Error)?.message ?? err}`, {
          status: 0,
          code: timedOut ? "timeout" : "connection_error",
        });
      }

      if (res.ok) {
        if (opts.binary) return new Uint8Array(await res.arrayBuffer()) as T;
        if (res.status === 204) return undefined as T;
        const text = await res.text();
        return (text ? JSON.parse(text) : undefined) as T;
      }

      const text = await res.text();
      let parsed: unknown = text;
      try {
        parsed = text ? JSON.parse(text) : undefined;
      } catch {
        /* not JSON */
      }
      const error = parseError(res.status, parsed, res.headers);
      // 429 means it was not processed, so it is always safe to try again.
      const retry = attempt < this.maxRetries && (res.status === 429 || (retrySafe && [500, 502, 503, 504].includes(res.status)));
      if (!retry) throw error;
      const ra = Number(res.headers.get("retry-after"));
      await sleep(Number.isFinite(ra) && ra > 0 ? Math.min(ra, 60) * 1000 : backoff(attempt));
    }
  }
}

const backoff = (attempt: number) => Math.min(8000, 500 * 2 ** attempt) * (0.75 + Math.random() * 0.5);
const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));
