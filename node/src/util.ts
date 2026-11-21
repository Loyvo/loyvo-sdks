export function newIdempotencyKey(): string {
  const c = globalThis.crypto;
  if (c?.randomUUID) return `loyvo-${c.randomUUID()}`;
  return `loyvo-${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}${Math.random().toString(36).slice(2)}`;
}

/**
 * A Sri Lankan mobile number as Loyvo stores it: "94771234567".
 * Accepts "0771234567", "771234567", "+94 77 123 4567", "0094-77-1234567".
 * Returns null when it is not a Sri Lankan mobile number.
 */
export function normalizePhone(input: string): string | null {
  let d = String(input).replace(/[^\d+]/g, "");
  if (d.startsWith("+")) d = d.slice(1);
  if (d.startsWith("00")) d = d.slice(2);
  if (d.startsWith("94")) d = d.slice(2);
  else if (d.startsWith("0")) d = d.slice(1);
  return /^7\d{8}$/.test(d) ? `94${d}` : null;
}
