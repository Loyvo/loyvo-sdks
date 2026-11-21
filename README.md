# Loyvo SDKs

Official client libraries for the [Loyvo API](https://loyvo.lk/docs): SMS, Loyalty and Wallet cards with one API key.

| Language | Package | Folder |
|---|---|---|
| Node.js / TypeScript | [`loyvo`](https://www.npmjs.com/package/loyvo) | [`node/`](node) |
| PHP | `loyvo/loyvo` (coming soon) | `php/` |

Get an API key in [app.loyvo.lk](https://app.loyvo.lk) → Settings → API keys.

## Releasing

- **Node:** bump `node/package.json` and `node/src/version.ts`, then push a tag `node-vX.Y.Z`. The release workflow tests, builds and publishes to npm (needs the `NPM_TOKEN` secret).
