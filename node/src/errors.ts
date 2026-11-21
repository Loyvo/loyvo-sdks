/** Any error the Loyvo API returned, or a failure to reach it. */
export class LoyvoError extends Error {
  /** HTTP status (0 when the API could not be reached). */
  readonly status: number;
  /** Stable machine-readable code, e.g. "insufficient_balance". */
  readonly code: string;
  /** The input field the error is about, when there is one. */
  readonly field?: string;
  /** Quote this to support@loyvo.lk. */
  readonly requestId?: string;
  /** The raw response body. */
  readonly body?: unknown;

  constructor(message: string, opts: { status: number; code: string; field?: string; requestId?: string; body?: unknown }) {
    super(message);
    this.name = new.target.name;
    this.status = opts.status;
    this.code = opts.code;
    this.field = opts.field;
    this.requestId = opts.requestId;
    this.body = opts.body;
  }
}

/** 400 / 422: the request was not valid. See `field`. */
export class ValidationError extends LoyvoError {}
/** 401: missing, invalid or revoked API key. */
export class AuthenticationError extends LoyvoError {}
/** 402: not enough SMS credits, or the plan has ended. */
export class PaymentRequiredError extends LoyvoError {}
/** 403: the key may not do this (missing permission, test key on Loyalty, ...). */
export class PermissionDeniedError extends LoyvoError {}
/** 404: no such resource. */
export class NotFoundError extends LoyvoError {}
/** 409: conflicts with the current state (e.g. an idempotency key reused with a different body). */
export class ConflictError extends LoyvoError {}
/** 429: too many requests. Wait `retryAfter` seconds. */
export class RateLimitError extends LoyvoError {
  readonly retryAfter?: number;
  constructor(message: string, opts: ConstructorParameters<typeof LoyvoError>[1] & { retryAfter?: number }) {
    super(message, opts);
    this.retryAfter = opts.retryAfter;
  }
}
/** 5xx: something went wrong at Loyvo. */
export class ServerError extends LoyvoError {}
/** The API could not be reached (network error or timeout). */
export class ConnectionError extends LoyvoError {}

type Detail = { code?: string; message?: string; field?: string; request_id?: string };

/** Reads the error shapes the Loyvo APIs use into one: SMS
 *  `{error:{code,message,field}}`, Wallet `{error:"...", code}`, Loyalty
 *  `{status:"error", errors:{field:[...]}}`, `{message}` or `{detail}`. */
export function parseError(status: number, body: unknown, headers: Headers): LoyvoError {
  const b = (body && typeof body === "object" ? body : {}) as Record<string, unknown>;
  let code = "";
  let message = "";
  let field: string | undefined;
  let requestId = headers.get("x-request-id") ?? undefined;

  if (b.error && typeof b.error === "object") {
    const d = b.error as Detail;
    code = d.code ?? "";
    message = d.message ?? "";
    field = d.field || undefined;
    requestId = d.request_id || requestId;
  } else if (typeof b.error === "string") {
    message = b.error;
  }
  if (!code) code = str(b.code) || str(b.error_code);
  if (!message) message = str(b.message) || str(b.detail);
  if (b.errors && typeof b.errors === "object") {
    const [k, v] = Object.entries(b.errors as Record<string, unknown>)[0] ?? [];
    if (k) {
      field ??= k === "non_field_errors" ? undefined : k;
      if (!message) message = Array.isArray(v) ? String(v[0]) : String(v);
    }
  }
  if (!message) message = typeof body === "string" && body ? body.slice(0, 300) : `HTTP ${status}`;
  if (!code) code = defaultCode(status);

  const opts = { status, code, field, requestId, body };
  if (status === 400 || status === 422) return new ValidationError(message, opts);
  if (status === 401) return new AuthenticationError(message, opts);
  if (status === 402) return new PaymentRequiredError(message, opts);
  if (status === 403) return new PermissionDeniedError(message, opts);
  if (status === 404) return new NotFoundError(message, opts);
  if (status === 409) return new ConflictError(message, opts);
  if (status === 429) {
    const ra = Number(headers.get("retry-after"));
    return new RateLimitError(message, { ...opts, retryAfter: Number.isFinite(ra) && ra >= 0 ? ra : undefined });
  }
  if (status >= 500) return new ServerError(message, opts);
  return new LoyvoError(message, opts);
}

const str = (v: unknown) => (typeof v === "string" ? v : "");

function defaultCode(status: number): string {
  return (
    { 400: "invalid_request", 401: "unauthorized", 402: "payment_required", 403: "forbidden", 404: "not_found", 409: "conflict", 422: "validation_failed", 429: "rate_limited" }[status] ??
    (status >= 500 ? "server_error" : "error")
  );
}
