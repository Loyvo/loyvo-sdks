import type { HttpClient } from "../http.js";
import type { EnrollResult, Pass, RedemptionStarted, ResolveResult, TransactionResult } from "../types.js";

/** Identify the customer by phone, or by the barcode scanned from their wallet card. */
export type Customer = { phone: string; barcodeValue?: never } | { barcodeValue: string; phone?: never };

const who = (c: Customer) => (c.phone !== undefined ? { phone: c.phone } : { barcodeValue: c.barcodeValue });

/**
 * Point-of-sale: points, enrolment and redemptions, keeping the customer's
 * Apple / Google Wallet card up to date in the same call. Needs a live key
 * with `loyalty:pos`.
 *
 * Calls that change points are never retried after a server or network
 * error (that could award points twice); check with `resolve()` first.
 */
export class Pos {
  readonly redemptions: Redemptions;
  constructor(private readonly http: HttpClient) {
    this.redemptions = new Redemptions(http);
  }

  /** Is this customer enrolled, and do they have a wallet card? */
  resolve(customer: Customer): Promise<ResolveResult> {
    return this.http.request("POST", "/v1/wallet/pos/customers/resolve", { body: who(customer), retrySafe: true });
  }

  /** Enrol a customer and issue their wallet card. Send them `publicInstallUrl`. */
  enroll(p: { phone: string; firstName: string; cardTemplateId: string; branchId?: number }): Promise<EnrollResult> {
    return this.http.request("POST", "/v1/wallet/pos/customers/enroll", { body: p });
  }

  /** Record a purchase: awards points and updates the wallet card. */
  transaction(
    p: Customer & {
      amount: number;
      branchId?: number;
      /** Your invoice number. */
      invoiceId?: string;
      getOffer?: boolean;
      /** Issue a card from this template if the customer has none yet. */
      cardTemplateId?: string;
    },
  ): Promise<TransactionResult> {
    const { phone: _p, barcodeValue: _b, ...rest } = p;
    return this.http.request("POST", "/v1/wallet/pos/transactions", { body: { ...who(p), ...rest } });
  }
}

export class Redemptions {
  constructor(private readonly http: HttpClient) {}

  /** Start a redemption: Loyvo texts the customer a 4-digit PIN. */
  initiate(p: Customer & { points: number; branchId?: number; invoiceId?: string }): Promise<RedemptionStarted> {
    return this.http.request("POST", "/v1/wallet/pos/redemptions/initiate", {
      body: { ...who(p), pointsToRedeem: p.points, branchId: p.branchId, invoiceId: p.invoiceId },
    });
  }

  /** Confirm with the PIN the customer received. Deducts the points. */
  confirm(p: Customer & { redemptionId: string; pin: string }): Promise<TransactionResult & { pass?: Pass }> {
    return this.http.request("POST", "/v1/wallet/pos/redemptions/confirm", { body: { ...who(p), redemptionId: p.redemptionId, pin: p.pin } });
  }

  /** Text the PIN again. */
  resendPin(redemptionId: string): Promise<Record<string, unknown>> {
    return this.http.request("POST", "/v1/wallet/pos/redemptions/resend-pin", { body: { redemptionId } });
  }

  /** Cancel a redemption that was not confirmed. */
  cancel(redemptionId: string): Promise<Record<string, unknown>> {
    return this.http.request("POST", "/v1/wallet/pos/redemptions/cancel", { body: { redemptionId }, retrySafe: true });
  }
}

/** Wallet cards (Apple Wallet and Google Wallet). Needs a live key with `wallet:issue`. */
export class Passes {
  constructor(private readonly http: HttpClient) {}

  get(id: string): Promise<Pass> {
    return this.http.request("GET", `/v1/wallet/passes/${encodeURIComponent(id)}`);
  }

  /** The Apple Wallet card file (.pkpass). */
  applePass(id: string): Promise<Uint8Array> {
    return this.http.request("GET", `/v1/wallet/passes/${encodeURIComponent(id)}/apple.pkpass`, { binary: true });
  }

  /** A "Save to Google Wallet" link. */
  googleSaveUrl(id: string): Promise<{ saveUrl: string }> {
    return this.http.request("GET", `/v1/wallet/passes/${encodeURIComponent(id)}/google-save-url`);
  }

  /** Change the card's points or stamps (does not change Loyvo Loyalty's ledger). */
  updateBalance(id: string, p: { pointsDelta?: number; stampsDelta?: number }): Promise<Pass> {
    return this.http.request("PATCH", `/v1/wallet/passes/${encodeURIComponent(id)}/balance`, { body: p });
  }

  /** Void the card. */
  void(id: string): Promise<Pass> {
    return this.http.request("POST", `/v1/wallet/passes/${encodeURIComponent(id)}/void`, { retrySafe: true });
  }
}
