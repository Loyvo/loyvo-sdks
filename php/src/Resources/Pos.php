<?php

namespace Loyvo\Resources;

use Loyvo\Exception\LoyvoException;
use Loyvo\Http\Client;

/**
 * Point-of-sale: points, enrolment and redemptions, keeping the customer's
 * Apple / Google Wallet card up to date in the same call. Needs a live key
 * with loyalty:pos.
 *
 * Identify the customer with ['phone' => ...] or ['barcodeValue' => ...]
 * (scanned from their wallet card).
 *
 * Calls that change points are never retried after a server or network
 * error (that could award points twice); check with resolve() first.
 */
final class Pos
{
    /** @var Client */
    private $http;
    /** @var Redemptions */
    public $redemptions;

    public function __construct(Client $http)
    {
        $this->http = $http;
        $this->redemptions = new Redemptions($http);
    }

    /**
     * Is this customer enrolled, and do they have a wallet card?
     * @param array{phone?: string, barcodeValue?: string} $customer
     * @return array<string, mixed> exists, hasLocalPass, member, pass
     */
    public function resolve(array $customer): array
    {
        return $this->http->request('POST', '/v1/wallet/pos/customers/resolve', ['body' => self::who($customer), 'retrySafe' => true]);
    }

    /**
     * Enrol a customer and issue their wallet card; send them publicInstallUrl.
     * @param array{phone: string, firstName: string, cardTemplateId: string, branchId?: int} $p
     * @return array<string, mixed> member, pass, publicInstallUrl
     */
    public function enroll(array $p): array
    {
        return $this->http->request('POST', '/v1/wallet/pos/customers/enroll', [
            'body' => ['phone' => $p['phone'], 'firstName' => $p['firstName'], 'cardTemplateId' => $p['cardTemplateId'], 'branchId' => $p['branchId'] ?? null],
        ]);
    }

    /**
     * Record a purchase: awards points and updates the wallet card.
     * @param array{phone?: string, barcodeValue?: string, amount: float|int, branchId?: int, invoiceId?: string, getOffer?: bool, cardTemplateId?: string} $p
     * @return array<string, mixed> points_earned, points_balance, transaction_id, pass
     */
    public function transaction(array $p): array
    {
        return $this->http->request('POST', '/v1/wallet/pos/transactions', [
            'body' => self::who($p) + [
                'amount' => $p['amount'],
                'branchId' => $p['branchId'] ?? null,
                'invoiceId' => $p['invoiceId'] ?? null,
                'getOffer' => $p['getOffer'] ?? null,
                'cardTemplateId' => $p['cardTemplateId'] ?? null,
            ],
        ]);
    }

    /**
     * @internal
     * @param array<string, mixed> $c
     * @return array<string, string>
     */
    public static function who(array $c): array
    {
        if (isset($c['phone']) && $c['phone'] !== '') {
            return ['phone' => (string) $c['phone']];
        }
        if (isset($c['barcodeValue']) && $c['barcodeValue'] !== '') {
            return ['barcodeValue' => (string) $c['barcodeValue']];
        }
        throw new LoyvoException('Identify the customer with "phone" or "barcodeValue".', 0, 'missing_customer', 'phone');
    }
}
