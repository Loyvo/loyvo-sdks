<?php

namespace Loyvo\Resources;

use Loyvo\Http\Client;

/**
 * Loyvo Loyalty settings, branches and records. For checkout (points,
 * enrolment, redemptions) use $loyvo->pos, which also updates wallet cards.
 * Needs a live key with loyalty:pos (or loyalty:read for reads).
 */
final class Loyalty
{
    /** @var Client */
    private $http;

    public function __construct(Client $http)
    {
        $this->http = $http;
    }

    /** How points are earned (e.g. 1 point per Rs. 100). @return array<string, mixed> */
    public function getConfig(): array
    {
        return $this->http->request('GET', '/v1/loyalty/companies/loyalty-config');
    }

    /** @param array{spendAmount?: int|float, pointsEarned?: int} $p @return array<string, mixed> */
    public function updateConfig(array $p): array
    {
        return $this->http->request('PATCH', '/v1/loyalty/companies/loyalty-config', [
            'body' => ['spend_amount' => $p['spendAmount'] ?? null, 'points_earned' => $p['pointsEarned'] ?? null],
            'retrySafe' => true,
        ]);
    }

    /** @return array<mixed> */
    public function listBranches(): array
    {
        return $this->http->request('GET', '/v1/loyalty/branches');
    }

    /** @return array<string, mixed> */
    public function getBranch(int $id): array
    {
        return $this->http->request('GET', '/v1/loyalty/branches/' . $id);
    }

    /** @param array{branch_code: string, branch_name?: string, phone?: string, email?: string, is_active?: bool} $p @return array<string, mixed> */
    public function createBranch(array $p): array
    {
        return $this->http->request('POST', '/v1/loyalty/branches', ['body' => $p]);
    }

    /** @param array<string, mixed> $p @return array<string, mixed> */
    public function updateBranch(int $id, array $p): array
    {
        return $this->http->request('PATCH', '/v1/loyalty/branches/' . $id, ['body' => $p, 'retrySafe' => true]);
    }

    public function deleteBranch(int $id): void
    {
        $this->http->request('DELETE', '/v1/loyalty/branches/' . $id, ['retrySafe' => true]);
    }

    /** A customer's points and details by phone. @return array<string, mixed> */
    public function lookupCustomer(string $phone): array
    {
        return $this->http->request('GET', '/v1/loyalty/pos/customers/lookup', ['query' => ['phone' => $phone]]);
    }

    /** Text the customer their wallet card link again. @return array<string, mixed> */
    public function resendPass(string $phone): array
    {
        return $this->http->request('POST', '/v1/loyalty/pos/customers/resend-pass', ['body' => ['phone' => $phone]]);
    }

    /** Remove a customer by phone or customer id. @param array{phone?: string, customerId?: string} $p @return array<string, mixed>|null */
    public function deleteCustomer(array $p): ?array
    {
        $q = isset($p['phone']) ? ['phone' => $p['phone']] : ['customer_id' => $p['customerId'] ?? null];
        return $this->http->request('DELETE', '/v1/loyalty/pos/customers/delete', ['query' => $q]);
    }

    /** @return array<string, mixed> */
    public function getTransaction(string $transactionUuid): array
    {
        return $this->http->request('GET', '/v1/loyalty/pos/transactions/details', ['query' => ['transaction_uuid' => $transactionUuid]]);
    }

    /** Correct a purchase amount (points are recalculated). @return array<string, mixed> */
    public function updateTransaction(string $transactionUuid, float $amount): array
    {
        return $this->http->request('PATCH', '/v1/loyalty/pos/transactions/details', [
            'query' => ['transaction_uuid' => $transactionUuid],
            'body' => ['amount' => $amount],
        ]);
    }
}
