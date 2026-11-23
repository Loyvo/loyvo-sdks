# Loyvo for PHP

The official PHP SDK for [Loyvo](https://loyvo.lk): send SMS in Sri Lanka, award and redeem loyalty points, and issue Apple Wallet and Google Wallet cards, all with one API key.

```sh
composer require loyvo/loyvo
```

PHP 7.4 or later with the `curl` and `json` extensions. No other dependencies, so it is safe to bundle inside WordPress plugins.

## Quick start

Create a key in [app.loyvo.lk](https://app.loyvo.lk) → **Settings → API keys**, and keep it on your server.

```php
use Loyvo\Loyvo;

$loyvo = new Loyvo(['apiKey' => getenv('LOYVO_API_KEY')]);

$sent = $loyvo->sms->send([
    'from' => 'QUBA',          // an approved sender ID
    'to' => '0771234567',
    'message' => 'Your order #1055 is on its way!',
]);
echo $sent['campaign_id'], ' ', $sent['status'];
```

`new Loyvo()` with no options reads `LOYVO_API_KEY` from the environment. Responses are associative arrays, exactly as the API returns them.

**Test keys** (`lvo_test_…`) work with SMS only: messages are never delivered and use no credits. Loyalty and Wallet need a live key.

## SMS

```php
// One message, now or later
$loyvo->sms->send(['from' => 'QUBA', 'to' => ['0771234567', '0712345678'], 'message' => 'Sale starts today!', 'scheduleAt' => new DateTime('2026-12-01 09:00', new DateTimeZone('Asia/Colombo'))]);

// Personalised campaign (up to 10,000 recipients)
$loyvo->sms->bulk([
    'from' => 'QUBA',
    'name' => 'December promo',
    'message' => 'Hi {{name}}, your code is {{code}}',
    'recipients' => [
        ['to' => '0771234567', 'vars' => ['name' => 'Kamal', 'code' => 'K10']],
        ['to' => '0712345678', 'vars' => ['name' => 'Nimal', 'code' => 'N10']],
    ],
]);

$msg = $loyvo->sms->get('msg_…');              // delivery status
$page = $loyvo->campaigns->get('cmp_…', 1, 100); // page, page size
$loyvo->campaigns->cancel('cmp_…');            // scheduled campaigns only
$senderIds = $loyvo->senderIds->list();
$credits = $loyvo->balance()['credits'];
```

Sends get an `Idempotency-Key` automatically, so a retry never sends twice. Pass your own `idempotencyKey` (for example `'order-1055-shipped'`) to make your own retries safe too.

## Loyalty at the point of sale

`$loyvo->pos` awards and redeems points **and** keeps the customer's wallet card up to date in the same call. Identify the customer with `phone`, or with the `barcodeValue` scanned from their card.

```php
// Is this customer enrolled?
$found = $loyvo->pos->resolve(['phone' => '0771234567']);

// Enrol and issue a wallet card; send them the link
$enrolled = $loyvo->pos->enroll(['phone' => '0771234567', 'firstName' => 'Kamal', 'cardTemplateId' => '…']);
$link = $enrolled['publicInstallUrl'];

// Award points for a purchase
$t = $loyvo->pos->transaction(['phone' => '0771234567', 'amount' => 2500, 'invoiceId' => 'INV-1055']);
echo $t['points_awarded'], ' / ', $t['new_points_balance'];

// Redeem: Loyvo texts the customer a PIN, then you confirm it
$r = $loyvo->pos->redemptions->initiate(['phone' => '0771234567', 'points' => 100]);
$loyvo->pos->redemptions->confirm(['phone' => '0771234567', 'redemptionId' => $r['redemption_id'], 'pin' => '4821']);
```

Calls that change points are **not retried** after a server or network error, because a retry could award points twice. If one fails that way, check with `$loyvo->pos->resolve()` before trying again.

More: `$loyvo->cardTemplates` (your card designs: `list()`, `get()`; use a template's `id` as `cardTemplateId`), `$loyvo->passes` (wallet cards: `get`, `applePass`, `googleSaveUrl`, `updateBalance`, `void`) and `$loyvo->loyalty` (`getConfig`, `updateConfig`, `listBranches`, `createBranch`, `updateBranch`, `deleteBranch`, `lookupCustomer`, `resendPass`, `deleteCustomer`, `getTransaction`, `updateTransaction`).

## Which key is this?

```php
$me = $loyvo->whoami();
// ['org' => ['id' => 'org_…', 'name' => 'QUBA'], 'env' => 'live', 'scopes' => [...], 'products' => ['loyalty', 'sms']]
```

## Errors

Every failure is a `Loyvo\Exception\LoyvoException` with `$status`, `$errorCode`, `getMessage()`, `$field` (when an input is wrong) and `$requestId` (quote it to support@loyvo.lk).

```php
use Loyvo\Exception\LoyvoException;
use Loyvo\Exception\PaymentRequiredError;
use Loyvo\Exception\ValidationError;

try {
    $loyvo->sms->send(['from' => 'QUBA', 'to' => '0771234567', 'message' => 'Hi']);
} catch (PaymentRequiredError $e) {
    // top up SMS credits
} catch (ValidationError $e) {
    error_log("Fix {$e->field}: {$e->getMessage()}");
} catch (LoyvoException $e) {
    error_log("{$e->errorCode} {$e->requestId}");
}
```

| Class | When |
|---|---|
| `ValidationError` | 400 / 422: invalid input (see `$field`) |
| `AuthenticationError` | 401: missing, invalid or revoked key |
| `PaymentRequiredError` | 402: not enough credits, or the plan has ended |
| `PermissionDeniedError` | 403: the key may not do this (e.g. `test_key_not_supported`) |
| `NotFoundError` | 404 |
| `ConflictError` | 409 |
| `RateLimitError` | 429 (see `$retryAfter`) |
| `ServerError` | 5xx |
| `ConnectionError` | Loyvo could not be reached (`$errorCode` `timeout` or `connection_error`) |

## Options

```php
new Loyvo([
    'apiKey' => 'lvo_live_…',
    'timeout' => 30,       // seconds per request
    'maxRetries' => 2,     // rate limits always; server/network errors only when safe
    'baseUrl' => 'https://api.loyvo.lk',
    'transport' => $transport, // any \Loyvo\Http\Transport, e.g. one built on wp_remote_request()
]);
```

`Loyvo\Phone::normalize('077 123 4567')` returns `'94771234567'` (or `null` for a non-mobile number).

## Links

- Docs: https://loyvo.lk/docs
- Support: support@loyvo.lk

MIT licensed.
