import type { HttpClient } from "../http.js";

type Json = Record<string, unknown>;

export interface BranchInput {
  branch_code: string;
  branch_name?: string;
  phone?: string;
  email?: string;
  is_active?: boolean;
}

/**
 * Loyvo Loyalty settings, branches and records. For checkout (points,
 * enrolment, redemptions) use `loyvo.pos`, which also updates wallet cards.
 * Needs a live key with `loyalty:pos` (or `loyalty:read` for reads).
 */
export class Loyalty {
  readonly branches: Branches;
  readonly customers: LoyaltyCustomers;
  readonly transactions: LoyaltyTransactions;
  constructor(private readonly http: HttpClient) {
    this.branches = new Branches(http);
    this.customers = new LoyaltyCustomers(http);
    this.transactions = new LoyaltyTransactions(http);
  }

  /** How points are earned (e.g. 1 point per Rs. 100). */
  getConfig(): Promise<Json> {
    return this.http.request("GET", "/v1/loyalty/companies/loyalty-config");
  }

  updateConfig(p: { spendAmount?: number; pointsEarned?: number }): Promise<Json> {
    return this.http.request("PATCH", "/v1/loyalty/companies/loyalty-config", {
      body: { spend_amount: p.spendAmount, points_earned: p.pointsEarned },
      retrySafe: true,
    });
  }
}

export class Branches {
  constructor(private readonly http: HttpClient) {}
  list(): Promise<Json[] | Json> {
    return this.http.request("GET", "/v1/loyalty/branches");
  }
  get(id: number): Promise<Json> {
    return this.http.request("GET", `/v1/loyalty/branches/${id}`);
  }
  create(p: BranchInput): Promise<Json> {
    return this.http.request("POST", "/v1/loyalty/branches", { body: p });
  }
  update(id: number, p: Partial<BranchInput>): Promise<Json> {
    return this.http.request("PATCH", `/v1/loyalty/branches/${id}`, { body: p, retrySafe: true });
  }
  delete(id: number): Promise<void> {
    return this.http.request("DELETE", `/v1/loyalty/branches/${id}`, { retrySafe: true });
  }
}

export class LoyaltyCustomers {
  constructor(private readonly http: HttpClient) {}
  /** A customer's points and details by phone. */
  lookup(phone: string): Promise<Json> {
    return this.http.request("GET", "/v1/loyalty/pos/customers/lookup", { query: { phone } });
  }
  /** Text the customer their wallet card link again. */
  resendPass(phone: string): Promise<Json> {
    return this.http.request("POST", "/v1/loyalty/pos/customers/resend-pass", { body: { phone } });
  }
  /** Remove a customer (by phone or customer id). */
  delete(p: { phone: string } | { customerId: string }): Promise<Json> {
    const query = "phone" in p ? { phone: p.phone } : { customer_id: p.customerId };
    return this.http.request("DELETE", "/v1/loyalty/pos/customers/delete", { query });
  }
}

export class LoyaltyTransactions {
  constructor(private readonly http: HttpClient) {}
  get(transactionUuid: string): Promise<Json> {
    return this.http.request("GET", "/v1/loyalty/pos/transactions/details", { query: { transaction_uuid: transactionUuid } });
  }
  /** Correct a purchase amount (points are recalculated). */
  update(transactionUuid: string, p: { amount: number }): Promise<Json> {
    return this.http.request("PATCH", "/v1/loyalty/pos/transactions/details", { query: { transaction_uuid: transactionUuid }, body: p });
  }
}
