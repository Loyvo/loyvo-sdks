import { readFileSync } from "node:fs";
import { describe, expect, it } from "vitest";
import {
  AuthenticationError,
  ConnectionError,
  Loyvo,
  normalizePhone,
  PaymentRequiredError,
  PermissionDeniedError,
  RateLimitError,
  ServerError,
  ValidationError,
  VERSION,
} from "../src/index.js";

type Call = { method: string; url: URL; headers: Record<string, string>; body: unknown };
type Reply = { status?: number; body?: unknown; headers?: Record<string, string> } | Error;

/** A fetch that records calls and answers from a script. */
function fakeFetch(...replies: Reply[]) {
  const calls: Call[] = [];
  const fn = (async (input: URL | string, init: RequestInit = {}) => {
    calls.push({
      method: init.method ?? "GET",
      url: new URL(String(input)),
      headers: init.headers as Record<string, string>,
      body: init.body ? JSON.parse(String(init.body)) : undefined,
    });
    const r = replies.shift() ?? { status: 200, body: {} };
    if (r instanceof Error) throw r;
    const text = r.body === undefined ? "" : typeof r.body === "string" ? r.body : JSON.stringify(r.body);
    return new Response(r.status === 204 ? null : text, { status: r.status ?? 200, headers: r.headers });
  }) as typeof fetch;
  return { fn, calls };
}

const client = (f: ReturnType<typeof fakeFetch>, opts: Partial<ConstructorParameters<typeof Loyvo>[0]> = {}) =>
  new Loyvo({ apiKey: "lvo_test_abc", fetch: f.fn, maxRetries: 2, ...opts });

describe("requests", () => {
  it("sends the key, a user agent and maps SMS send fields", async () => {
    const f = fakeFetch({ status: 202, body: { campaign_id: "cmp_1", test: true } });
    const r = await client(f).sms.send({ from: "QUBA", to: ["0771234567"], message: "Hi", scheduleAt: new Date("2026-12-01T10:00:00Z") });
    expect(r.campaign_id).toBe("cmp_1");
    const c = f.calls[0];
    expect(c.method).toBe("POST");
    expect(c.url.href).toBe("https://api.loyvo.lk/v1/sms/send");
    expect(c.headers.Authorization).toBe("Bearer lvo_test_abc");
    expect(c.headers["User-Agent"]).toMatch(/^loyvo-node\//);
    expect(c.headers["Idempotency-Key"]).toMatch(/^loyvo-/);
    expect(c.body).toEqual({ sender_id: "QUBA", to: ["0771234567"], message: "Hi", schedule_at: "2026-12-01T10:00:00.000Z" });
  });

  it("maps bulk, campaign paging and lists sender IDs", async () => {
    const f = fakeFetch({ status: 202, body: {} }, { body: { campaign: {}, messages: [], pagination: {} } }, { body: { data: [{ mask: "QUBA" }] } });
    const l = client(f);
    await l.sms.bulk({ from: "QUBA", message: "Hi {{name}}", recipients: [{ to: "0771234567", vars: { name: "Kamal" } }], name: "Promo" });
    await l.campaigns.get("cmp_1", { page: 2, pageSize: 50 });
    expect(await l.senderIds.list()).toEqual([{ mask: "QUBA" }]);
    expect(f.calls[0].body).toMatchObject({ sender_id: "QUBA", name: "Promo", recipients: [{ to: "0771234567", vars: { name: "Kamal" } }] });
    expect(f.calls[1].url.pathname).toBe("/v1/campaigns/cmp_1");
    expect(f.calls[1].url.searchParams.get("page_size")).toBe("50");
    expect(f.calls[2].url.pathname).toBe("/v1/sender-ids");
  });

  it("maps POS calls to the wallet routes", async () => {
    const f = fakeFetch({ body: { exists: true } }, { status: 201, body: { points_earned: 25 } }, { body: { redemption_id: "r1" } }, { body: {} });
    const l = client(f, { apiKey: "lvo_live_x" });
    await l.pos.resolve({ barcodeValue: "BC1" });
    await l.pos.transaction({ phone: "0771234567", amount: 2500, invoiceId: "INV-1" });
    await l.pos.redemptions.initiate({ phone: "0771234567", points: 100 });
    await l.pos.redemptions.confirm({ phone: "0771234567", redemptionId: "r1", pin: "1234" });
    expect(f.calls.map((c) => c.url.pathname)).toEqual([
      "/v1/wallet/pos/customers/resolve",
      "/v1/wallet/pos/transactions",
      "/v1/wallet/pos/redemptions/initiate",
      "/v1/wallet/pos/redemptions/confirm",
    ]);
    expect(f.calls[0].body).toEqual({ barcodeValue: "BC1" });
    expect(f.calls[1].body).toEqual({ phone: "0771234567", amount: 2500, invoiceId: "INV-1" });
    expect(f.calls[2].body).toEqual({ phone: "0771234567", pointsToRedeem: 100 });
    expect(f.calls[3].body).toEqual({ phone: "0771234567", redemptionId: "r1", pin: "1234" });
  });

  it("calls whoami, balance and loyalty with query strings", async () => {
    const f = fakeFetch({ body: { env: "live", org: { id: "org_1", name: "QUBA" } } }, { body: { credits: 10 } }, { body: {} }, { status: 204 });
    const l = client(f);
    expect((await l.whoami()).org.name).toBe("QUBA");
    expect((await l.balance()).credits).toBe(10);
    await l.loyalty.customers.lookup("0771234567");
    await l.loyalty.branches.delete(3);
    expect(f.calls[0].url.pathname).toBe("/v1/key");
    expect(f.calls[1].url.pathname).toBe("/v1/balance");
    expect(f.calls[2].url.search).toBe("?phone=0771234567");
    expect(f.calls[3].method).toBe("DELETE");
  });

  it("uses a custom base URL and LOYVO_API_KEY", async () => {
    process.env.LOYVO_API_KEY = "lvo_live_env";
    const f = fakeFetch({ body: {} });
    const l = new Loyvo({ fetch: f.fn, baseUrl: "http://localhost:9000/" });
    delete process.env.LOYVO_API_KEY;
    await l.balance();
    expect(f.calls[0].url.href).toBe("http://localhost:9000/v1/balance");
    expect(f.calls[0].headers.Authorization).toBe("Bearer lvo_live_env");
    expect(l.isTest).toBe(false);
    expect(() => new Loyvo({ fetch: f.fn })).toThrow(/Missing API key/);
  });
});

describe("errors", () => {
  it("reads the SMS error shape", async () => {
    const f = fakeFetch({ status: 422, body: { error: { code: "validation_failed", message: "Unknown sender ID", field: "sender_id", request_id: "req_1" } } });
    const e = await client(f).sms.send({ from: "X", to: "1", message: "m" }).catch((x) => x);
    expect(e).toBeInstanceOf(ValidationError);
    expect(e).toMatchObject({ status: 422, code: "validation_failed", field: "sender_id", requestId: "req_1", message: "Unknown sender ID" });
  });

  it("reads the Wallet error shape and the test-key refusal", async () => {
    const f = fakeFetch(
      { status: 403, body: { error: { code: "test_key_not_supported", message: "Test keys work with the SMS API only." } } },
      { status: 409, body: { error: "POS isn't set up for this business yet" } },
    );
    const l = client(f);
    const e1 = await l.pos.resolve({ phone: "0771234567" }).catch((x) => x);
    expect(e1).toBeInstanceOf(PermissionDeniedError);
    expect(e1.code).toBe("test_key_not_supported");
    const e2 = await l.pos.resolve({ phone: "0771234567" }).catch((x) => x);
    expect(e2.message).toMatch(/POS isn't set up/);
    expect(e2.code).toBe("conflict");
  });

  it("reads the Loyalty error shapes", async () => {
    const f = fakeFetch({ status: 400, body: { status: "error", errors: { phone: ["Enter a valid phone."] } } }, { status: 401, body: { detail: "Invalid token." } });
    const l = client(f, { apiKey: "lvo_live_x" });
    const e1 = await l.loyalty.customers.resendPass("x").catch((x) => x);
    expect(e1).toBeInstanceOf(ValidationError);
    expect(e1).toMatchObject({ field: "phone", message: "Enter a valid phone." });
    const e2 = await l.loyalty.getConfig().catch((x) => x);
    expect(e2).toBeInstanceOf(AuthenticationError);
    expect(e2.message).toBe("Invalid token.");
  });

  it("maps 402 and keeps the request id header", async () => {
    const f = fakeFetch({ status: 402, body: { error: { code: "insufficient_balance", message: "Not enough credits" } }, headers: { "x-request-id": "gw_9" } });
    const e = await client(f).sms.send({ from: "QUBA", to: "0771234567", message: "m" }).catch((x) => x);
    expect(e).toBeInstanceOf(PaymentRequiredError);
    expect(e.requestId).toBe("gw_9");
  });
});

describe("retries", () => {
  it("retries a rate limit after Retry-After, with the same idempotency key", async () => {
    const f = fakeFetch({ status: 429, body: { error: { code: "rate_limited", message: "Slow down" } }, headers: { "retry-after": "0" } }, { status: 202, body: { campaign_id: "c" } });
    await client(f).sms.send({ from: "QUBA", to: "0771234567", message: "m" });
    expect(f.calls).toHaveLength(2);
    expect(f.calls[0].headers["Idempotency-Key"]).toBe(f.calls[1].headers["Idempotency-Key"]);
  });

  it("gives up with RateLimitError after maxRetries", async () => {
    const limited = { status: 429, body: {}, headers: { "retry-after": "0" } };
    const f = fakeFetch(limited, limited);
    const e = await client(f, { maxRetries: 1 }).balance().catch((x) => x);
    expect(e).toBeInstanceOf(RateLimitError);
    expect(e.retryAfter).toBe(0);
    expect(f.calls).toHaveLength(2);
  });

  it("retries safe calls after a server or network error", async () => {
    const f = fakeFetch({ status: 503, body: {} }, new TypeError("fetch failed"), { body: { credits: 1 } });
    expect((await client(f).balance()).credits).toBe(1);
    expect(f.calls).toHaveLength(3);
  });

  it("never retries a POS points call after a server or network error", async () => {
    const f1 = fakeFetch({ status: 502, body: {} });
    expect(await client(f1).pos.transaction({ phone: "0771234567", amount: 10 }).catch((x) => x)).toBeInstanceOf(ServerError);
    expect(f1.calls).toHaveLength(1);

    const f2 = fakeFetch(new TypeError("socket hang up"));
    const e = await client(f2).pos.redemptions.confirm({ phone: "0771234567", redemptionId: "r", pin: "1" }).catch((x) => x);
    expect(e).toBeInstanceOf(ConnectionError);
    expect(e.code).toBe("connection_error");
    expect(f2.calls).toHaveLength(1);
  });

  it("reports a timeout", async () => {
    const slow = (async (_u: unknown, init: RequestInit = {}) =>
      new Promise((_, reject) => init.signal?.addEventListener("abort", () => reject(init.signal!.reason)))) as typeof fetch;
    const e = await new Loyvo({ apiKey: "lvo_live_x", fetch: slow, timeout: 20, maxRetries: 0 }).pos.resolve({ phone: "1" }).catch((x) => x);
    expect(e).toBeInstanceOf(ConnectionError);
    expect(e.code).toBe("timeout");
  });
});

describe("normalizePhone", () => {
  it.each([
    ["0771234567", "94771234567"],
    ["771234567", "94771234567"],
    ["+94 77 123 4567", "94771234567"],
    ["0094-77-1234567", "94771234567"],
    ["94771234567", "94771234567"],
  ])("%s", (input, out) => expect(normalizePhone(input)).toBe(out));
  it("rejects non-mobile numbers", () => {
    expect(normalizePhone("0112345678")).toBeNull();
    expect(normalizePhone("12345")).toBeNull();
  });
});

it("VERSION matches package.json", () => {
  expect(VERSION).toBe(JSON.parse(readFileSync(new URL("../package.json", import.meta.url), "utf8")).version);
});
