// What the Loyvo APIs return. Fields are as the API sends them (snake_case
// for SMS and Loyalty, camelCase for Wallet); unknown extra fields may appear.

// ---------------------------------------------------------------- key

export interface KeyInfo {
  key_id: string;
  env: "live" | "test";
  scopes: string[];
  org: { id: string; name: string };
  /** Active Loyvo products, e.g. ["loyalty", "sms"]. */
  products: string[];
}

// ---------------------------------------------------------------- SMS

export type Encoding = "GSM-7" | "UCS-2";

export interface SendResult {
  campaign_id: string;
  sender_id: string;
  encoding: Encoding;
  segments: number;
  total_segments: number;
  recipients: number;
  status: "queued" | "scheduled";
  schedule_at?: string | null;
  /** True when sent with a test key: never delivered, no credits used. */
  test: boolean;
  messages?: Array<{ id: string; to: string }>;
}

export interface Message {
  id: string;
  campaign_id: string;
  to: string;
  sender_id: string;
  message: string;
  encoding: Encoding;
  segments: number;
  status: "scheduled" | "queued" | "sent" | "delivered" | "failed" | "cancelled";
  failure_reason?: string;
  source: "portal" | "api" | "sdk";
  test: boolean;
  reference: string | null;
  created_at: string;
  schedule_at: string | null;
  sent_at: string | null;
  delivered_at: string | null;
  failed_at: string | null;
}

export interface Campaign {
  id: string;
  name: string | null;
  type: "single" | "bulk";
  source: "portal" | "api" | "sdk";
  sender_id: string;
  message: string;
  personalized: boolean;
  test: boolean;
  recipients: number;
  segments: number;
  status: "scheduled" | "sending" | "completed" | "cancelled";
  reference: string | null;
  schedule_at: string | null;
  cancelled_at: string | null;
  created_at: string;
  counts: Record<string, number>;
}

export interface CampaignPage {
  campaign: Campaign;
  messages: Message[];
  pagination: { page: number; page_size: number; total: number; [k: string]: unknown };
}

export interface SenderId {
  id: string;
  mask: string;
  status: "pending" | "submitted" | "approved" | "rejected" | "suspended";
  created_at: string;
  trial?: boolean;
}

export interface Balance {
  /** Credits you can use now. One credit sends one SMS part. */
  credits: number;
  buckets?: Array<Record<string, unknown>>;
  wallet_cents?: number;
  rate_per_part_cents?: number;
  currency: string;
  plan?: Record<string, unknown>;
}

// ---------------------------------------------------------------- Wallet & POS

export interface Member {
  id: string;
  fullName: string | null;
  phone: string | null;
  email: string | null;
  createdAt: string;
}

export interface Pass {
  id: string;
  cardTemplateId: string;
  memberId: string;
  serialNumber: string;
  status: string;
  pointsBalance: number;
  stampCount: number;
  barcodeValue: string;
  expiresAt: string | null;
  updatedAt: string;
}

export interface ResolveResult {
  /** Enrolled in Loyvo Loyalty. */
  exists: boolean;
  /** Has a wallet card. */
  hasLocalPass: boolean;
  member?: Member;
  pass?: Pass;
  /** The customer's loyalty record (points, tier, ...). */
  onePayLoyalty?: Record<string, unknown>;
}

export interface EnrollResult {
  member: Member;
  pass: Pass;
  /** Send this link to the customer: it adds the card to Apple or Google Wallet. */
  publicInstallUrl: string;
}

export interface TransactionResult {
  points_earned: number;
  points_balance: number;
  transaction_id: string;
  pass?: Pass;
  [k: string]: unknown;
}

export interface RedemptionStarted {
  redemption_id: string;
  /** Seconds until the PIN expires. */
  expires_in: number;
  [k: string]: unknown;
}
