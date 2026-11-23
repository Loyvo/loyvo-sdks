# Loyvo SDKs

Official client libraries for the [Loyvo API](https://loyvo.lk/docs): SMS, Loyalty and Wallet cards with one API key.

| Language | Package | Folder |
|---|---|---|
| Node.js / TypeScript | [`loyvo`](https://www.npmjs.com/package/loyvo) | [`node/`](node) |
| PHP | `loyvo/loyvo` | [`php/`](php) |

Get an API key in [app.loyvo.lk](https://app.loyvo.lk) → Settings → API keys.

## PHP quick start

```sh
composer require loyvo/loyvo
```

```php
$loyvo = new Loyvo\Loyvo(['apiKey' => getenv('LOYVO_API_KEY')]);
$loyvo->sms->send(['from' => 'QUBA', 'to' => '0771234567', 'message' => 'Hi!']);
```

Full guide: [php/README.md](php/README.md). Node.js: [node/README.md](node/README.md).

## Releasing

- **Node:** bump `node/package.json` and `node/src/version.ts`, then push a tag `node-vX.Y.Z`. The release workflow tests, builds and publishes to npm through npm Trusted Publishing (no token; configured on the package's npm settings).
- **PHP:** bump `php/src/Version.php`, then push a tag `vX.Y.Z` (Packagist reads tags; plain `v` tags are the PHP versions). Packagist updates through its GitHub hook.
