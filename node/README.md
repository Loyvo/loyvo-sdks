# Loyvo for Node.js

The official Node.js SDK for [Loyvo](https://loyvo.lk): send SMS in Sri Lanka, award and redeem loyalty points, and issue Apple Wallet and Google Wallet cards, all with one API key.

```sh
npm install loyvo
```

Node 18 or later. No dependencies. Works with `import` and `require`, and ships TypeScript types.

## Quick start

Create a key in [app.loyvo.lk](https://app.loyvo.lk) → **Settings → API keys**, and keep it on your server.

```ts
import Loyvo from "loyvo";

const loyvo = new Loyvo({ apiKey: process.env.LOYVO_API_KEY });

const sent = await loyvo.sms.send({
  from: "QUBA", // an approved sender ID
  to: "0771234567",
  message: "Your order #1055 is on its way!",
});
console.log(sent.campaign_id, sent.status);
```

`new Loyvo()` with no options reads `LOYVO_API_KEY` from the environment.

**Test keys** (`lvo_test_…`) work with SMS only: messages are never delivered and use no credits. Loyalty and Wallet need a live key.

## SMS

```ts
// One message, now or later
await loyvo.sms.send({ from: "QUBA", to: ["0771234567", "0712345678"], message: "Sale starts today!", scheduleAt: new Date("2026-12-01T09:00:00+05:30") });

// Personalised campaign (up to 10,000 recipients)
await loyvo.sms.bulk({
  from: "QUBA",
  name: "December promo",
  message: "Hi {{name}}, your code is {{code}}",
  recipients: [
    { to: "0771234567", vars: { name: "Kamal", code: "K10" } },
    { to: "0712345678", vars: { name: "Nimal", code: "N10" } },
  ],
});

const msg = await loyvo.sms.get("msg_…");        // delivery status
const page = await loyvo.campaigns.get("cmp_…", { page: 1, pageSize: 100 });
await loyvo.campaigns.cancel("cmp_…");            // scheduled campaigns only
const senderIds = await loyvo.senderIds.list();
const { credits } = await loyvo.balance();
```

Sends get an `Idempotency-Key` automatically, so a retry never sends twice. Pass your own `idempotencyKey` (for example your order ID) to make your own retries safe too.

## Loyalty at the point of sale

`loyvo.pos` awards and redeems points **and** keeps the customer's wallet card up to date in the same call. Identify the customer by `phone` or by the `barcodeValue` scanned from their card.

```ts
// Is this customer enrolled?
const found = await loyvo.pos.resolve({ phone: "0771234567" });

// Enrol and issue a wallet card; send them the link
const { publicInstallUrl } = await loyvo.pos.enroll({ phone: "0771234567", firstName: "Kamal", cardTemplateId: "…" });

// Award points for a purchase
const t = await loyvo.pos.transaction({ phone: "0771234567", amount: 2500, invoiceId: "INV-1055" });
console.log(t.points_awarded, t.new_points_balance);

// Redeem: Loyvo texts the customer a PIN, then you confirm it
const { redemption_id } = await loyvo.pos.redemptions.initiate({ phone: "0771234567", points: 100 });
await loyvo.pos.redemptions.confirm({ phone: "0771234567", redemptionId: redemption_id, pin: "4821" });
```

Calls that change points are **not retried** after a server or network error, because a retry could award points twice. If one fails that way, check with `loyvo.pos.resolve()` before trying again.

More: `loyvo.cardTemplates` (your card designs: `list()`, `get()`; use a template's `id` as `cardTemplateId`), `loyvo.passes` (wallet cards: get, Apple pass file, Google save link, void), `loyvo.loyalty` (earning rules, branches, customer lookup, transaction details).

## Which key is this?

```ts
const me = await loyvo.whoami();
// { org: { id: "org_…", name: "QUBA" }, env: "live", scopes: ["sms:send", "loyalty:pos"], products: ["loyalty", "sms"] }
```

## Errors

Every failure is a `LoyvoError` with `status`, `code`, `message`, `field` (when an input is wrong) and `requestId` (quote it to support@loyvo.lk).

```ts
import { LoyvoError, PaymentRequiredError, ValidationError } from "loyvo";

try {
  await loyvo.sms.send({ from: "QUBA", to: "0771234567", message: "Hi" });
} catch (err) {
  if (err instanceof PaymentRequiredError) console.log("Top up SMS credits");
  else if (err instanceof ValidationError) console.log(`Fix ${err.field}: ${err.message}`);
  else if (err instanceof LoyvoError) console.log(err.code, err.requestId);
  else throw err;
}
```

| Class | When |
|---|---|
| `ValidationError` | 400 / 422: invalid input (see `field`) |
| `AuthenticationError` | 401: missing, invalid or revoked key |
| `PaymentRequiredError` | 402: not enough credits, or the plan has ended |
| `PermissionDeniedError` | 403: the key may not do this (e.g. `test_key_not_supported`) |
| `NotFoundError` | 404 |
| `ConflictError` | 409 |
| `RateLimitError` | 429 (see `retryAfter`) |
| `ServerError` | 5xx |
| `ConnectionError` | Loyvo could not be reached (`code: "timeout"` or `"connection_error"`) |

## Options

```ts
new Loyvo({
  apiKey: "lvo_live_…",
  timeout: 30_000,  // ms per request
  maxRetries: 2,    // rate limits always; server/network errors only when safe
  baseUrl: "https://api.loyvo.lk",
  fetch,            // bring your own fetch
});
```

`normalizePhone("077 123 4567")` returns `"94771234567"` (or `null` for a non-mobile number).

## Links

- Docs: https://loyvo.lk/docs
- Support: support@loyvo.lk

MIT licensed.
