<?php

namespace Loyvo\Resources;

use Loyvo\Http\Client;

final class Redemptions
{
    /** @var Client */
    private $http;

    public function __construct(Client $http)
    {
        $this->http = $http;
    }

    /**
     * Start a redemption: Loyvo texts the customer a 4-digit PIN.
     * @param array{phone?: string, barcodeValue?: string, points: int, branchId?: int, invoiceId?: string} $p
     * @return array<string, mixed> redemption_id, expires_in
     */
    public function initiate(array $p): array
    {
        return $this->http->request('POST', '/v1/wallet/pos/redemptions/initiate', [
            'body' => Pos::who($p) + ['pointsToRedeem' => $p['points'], 'branchId' => $p['branchId'] ?? null, 'invoiceId' => $p['invoiceId'] ?? null],
        ]);
    }

    /**
     * Confirm with the PIN the customer received; deducts the points.
     * @param array{phone?: string, barcodeValue?: string, redemptionId: string, pin: string} $p
     * @return array<string, mixed> redemption_id, points_redeemed, previous_balance, new_points_balance, discount_applied_amount, pass
     */
    public function confirm(array $p): array
    {
        return $this->http->request('POST', '/v1/wallet/pos/redemptions/confirm', [
            'body' => Pos::who($p) + ['redemptionId' => $p['redemptionId'], 'pin' => (string) $p['pin']],
        ]);
    }

    /** Text the PIN again. @return array<string, mixed> */
    public function resendPin(string $redemptionId): array
    {
        return $this->http->request('POST', '/v1/wallet/pos/redemptions/resend-pin', ['body' => ['redemptionId' => $redemptionId]]);
    }

    /** Cancel a redemption that was not confirmed. @return array<string, mixed> */
    public function cancel(string $redemptionId): array
    {
        return $this->http->request('POST', '/v1/wallet/pos/redemptions/cancel', ['body' => ['redemptionId' => $redemptionId], 'retrySafe' => true]);
    }
}
