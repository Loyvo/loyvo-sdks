import { HttpClient, type ClientOptions } from "./http.js";
import { Loyalty } from "./resources/loyalty.js";
import { CardTemplates, Passes, Pos } from "./resources/pos.js";
import { Campaigns, getBalance, SenderIds, Sms } from "./resources/sms.js";
import type { Balance, KeyInfo } from "./types.js";

/**
 * The Loyvo API: SMS, Loyalty and Wallet with one API key.
 *
 *   const loyvo = new Loyvo({ apiKey: process.env.LOYVO_API_KEY });
 *   await loyvo.sms.send({ from: "QUBA", to: "0771234567", message: "Hi!" });
 */
export class Loyvo {
  readonly sms: Sms;
  readonly campaigns: Campaigns;
  readonly senderIds: SenderIds;
  readonly pos: Pos;
  readonly passes: Passes;
  readonly cardTemplates: CardTemplates;
  readonly loyalty: Loyalty;
  private readonly http: HttpClient;

  constructor(opts: ClientOptions = {}) {
    this.http = new HttpClient(opts);
    this.sms = new Sms(this.http);
    this.campaigns = new Campaigns(this.http);
    this.senderIds = new SenderIds(this.http);
    this.pos = new Pos(this.http);
    this.passes = new Passes(this.http);
    this.cardTemplates = new CardTemplates(this.http);
    this.loyalty = new Loyalty(this.http);
  }

  /** True for `lvo_test_` keys: SMS is free and never delivered; Loyalty and Wallet refuse them. */
  get isTest(): boolean {
    return this.http.apiKey.startsWith("lvo_test_");
  }

  /** Which organisation this key belongs to, live or test, and what it may do. */
  whoami(): Promise<KeyInfo> {
    return this.http.request("GET", "/v1/key");
  }

  /** Your SMS credits. */
  balance(): Promise<Balance> {
    return getBalance(this.http);
  }
}

export default Loyvo;
export type { ClientOptions } from "./http.js";
export * from "./errors.js";
export * from "./types.js";
export { normalizePhone } from "./util.js";
export type { SendParams, BulkParams, BulkRecipient } from "./resources/sms.js";
export type { Customer } from "./resources/pos.js";
export type { BranchInput } from "./resources/loyalty.js";
export { VERSION } from "./version.js";
