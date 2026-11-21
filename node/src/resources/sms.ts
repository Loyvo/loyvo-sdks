import type { HttpClient } from "../http.js";
import type { Balance, CampaignPage, Campaign, Message, SendResult, SenderId } from "../types.js";
import { newIdempotencyKey } from "../util.js";

export interface SendParams {
  /** An approved sender ID (mask), e.g. "QUBA". */
  from: string;
  /** One number, or up to 100. Any Sri Lankan format. */
  to: string | string[];
  message: string;
  /** Your own reference, stored with the message. */
  reference?: string;
  /** ISO 8601 time to send later. */
  scheduleAt?: string | Date;
  /** Makes retries safe. One is made for you when omitted. */
  idempotencyKey?: string;
}

export interface BulkRecipient {
  to: string;
  /** Values for `{{placeholders}}` in the message. */
  vars?: Record<string, string | number>;
}

export interface BulkParams {
  from: string;
  /** Text, or a template with `{{placeholders}}` filled from each recipient's vars. */
  message: string;
  recipients: BulkRecipient[];
  /** Campaign name shown in app.loyvo.lk. */
  name?: string;
  reference?: string;
  scheduleAt?: string | Date;
  idempotencyKey?: string;
}

const iso = (v?: string | Date) => (v instanceof Date ? v.toISOString() : v);

export class Sms {
  constructor(private readonly http: HttpClient) {}

  /** Send one message to one or more numbers. */
  send(p: SendParams): Promise<SendResult> {
    return this.http.request("POST", "/v1/sms/send", {
      body: { sender_id: p.from, to: p.to, message: p.message, reference: p.reference, schedule_at: iso(p.scheduleAt) },
      idempotencyKey: p.idempotencyKey ?? newIdempotencyKey(),
    });
  }

  /** Send a personalised campaign (up to 10,000 recipients). */
  bulk(p: BulkParams): Promise<SendResult> {
    return this.http.request("POST", "/v1/sms/bulk", {
      body: { sender_id: p.from, name: p.name, message: p.message, recipients: p.recipients, reference: p.reference, schedule_at: iso(p.scheduleAt) },
      idempotencyKey: p.idempotencyKey ?? newIdempotencyKey(),
    });
  }

  /** One message and its delivery status. */
  get(id: string): Promise<Message> {
    return this.http.request("GET", `/v1/sms/${encodeURIComponent(id)}`);
  }
}

export class Campaigns {
  constructor(private readonly http: HttpClient) {}

  /** A campaign with a page of its messages. */
  get(id: string, opts: { page?: number; pageSize?: number } = {}): Promise<CampaignPage> {
    return this.http.request("GET", `/v1/campaigns/${encodeURIComponent(id)}`, { query: { page: opts.page, page_size: opts.pageSize } });
  }

  /** Cancel a scheduled campaign. Its credits are returned. */
  cancel(id: string): Promise<Campaign> {
    return this.http.request("POST", `/v1/campaigns/${encodeURIComponent(id)}/cancel`, { retrySafe: true });
  }
}

export class SenderIds {
  constructor(private readonly http: HttpClient) {}

  /** Your sender IDs. Only `approved` ones can send. */
  async list(): Promise<SenderId[]> {
    const r = await this.http.request<{ data: SenderId[] }>("GET", "/v1/sender-ids");
    return r.data;
  }
}

export const getBalance = (http: HttpClient) => http.request<Balance>("GET", "/v1/balance");
