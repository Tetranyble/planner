<?php

namespace Modules\Unipotence\Infrastructure\Persistence;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Unipotence\Domain\Contracts\UnipotenceRepository;
use Modules\Unipotence\Domain\Enums\UnipotenceState;
use Modules\Unipotence\Domain\Exceptions\OutcomeUnknown;
use Modules\Unipotence\Domain\Exceptions\ReplayResponse;
use Modules\Unipotence\Domain\Exceptions\RequestInFlight;
use Modules\Unipotence\Domain\Exceptions\StaleClaim;
use Modules\Unipotence\Domain\Exceptions\UnipotenceConflict;
use Modules\Unipotence\Domain\Exceptions\UnipotenceStoreUnavailable;
use Modules\Unipotence\Infrastructure\Persistence\Models\Unipotence;
use Psr\Log\LoggerInterface;

final class DatabaseUnipotenceRepository implements UnipotenceRepository
{
    public function __construct(
        private readonly string $connectionName,
        private readonly LoggerInterface $logger,
    ) {}

    public function claim(
        string $scope,
        string $key,
        string $requestHash,
        ?string $reference,
        int $leaseSeconds,
    ): string {
        try {
            return $this->claimFromStore($scope, $key, $requestHash, $reference, $leaseSeconds);
        } catch (QueryException $exception) {
            throw new UnipotenceStoreUnavailable($exception);
        }
    }

    public function complete(
        string $scope,
        string $key,
        string $requestHash,
        string $claimToken,
        int $responseStatus,
        array $responseBody,
        array $responseHeaders,
    ): void {
        $updated = $this->table()
            ->where('scope', $scope)
            ->where('key', $key)
            ->where('request_hash', $requestHash)
            ->where('claim_token', $claimToken)
            ->where('state', UnipotenceState::Claimed->value)
            ->update([
                'state' => UnipotenceState::Completed->value,
                'response_status' => $responseStatus,
                'response_body' => $this->encrypt($responseBody),
                'response_headers' => $this->encrypt($responseHeaders),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new StaleClaim;
        }
    }

    public function markIndeterminate(
        string $scope,
        string $key,
        string $requestHash,
        string $claimToken,
        ?int $responseStatus,
        array $responseBody,
        array $responseHeaders = [],
    ): void {
        $updated = $this->table()
            ->where('scope', $scope)
            ->where('key', $key)
            ->where('request_hash', $requestHash)
            ->where('claim_token', $claimToken)
            ->where('state', UnipotenceState::Claimed->value)
            ->update([
                'state' => UnipotenceState::Indeterminate->value,
                'response_status' => $responseStatus,
                'response_body' => $this->encrypt($responseBody),
                'response_headers' => $this->encrypt($responseHeaders),
                'indeterminate_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new StaleClaim;
        }
    }

    public function release(string $scope, string $key, string $claimToken): void
    {
        $this->table()
            ->where('scope', $scope)
            ->where('key', $key)
            ->where('claim_token', $claimToken)
            ->where('state', UnipotenceState::Claimed->value)
            ->delete();
    }

    public function completeIndeterminateByReference(
        string $scope,
        string $reference,
        int $responseStatus,
        array $responseBody,
        array $responseHeaders,
    ): int {
        return $this->table()
            ->where('scope', $scope)
            ->where('reference', $reference)
            ->where(function (Builder $query): void {
                $query->where('state', UnipotenceState::Indeterminate->value)
                    ->orWhere(function (Builder $query): void {
                        $query->where('state', UnipotenceState::Completed->value)
                            ->where('response_status', 409);
                    });
            })
            ->update([
                'state' => UnipotenceState::Completed->value,
                'response_status' => $responseStatus,
                'response_body' => $this->encrypt($responseBody),
                'response_headers' => $this->encrypt($responseHeaders),
                'completed_at' => now(),
                'indeterminate_at' => null,
                'updated_at' => now(),
            ]);
    }

    private function resolveExistingClaim(
        string $scope,
        string $key,
        string $requestHash,
        ?string $reference,
        int $leaseSeconds,
    ): string {
        $existing = Unipotence::on($this->connectionName)
            ->useWritePdo()
            ->where('scope', $scope)
            ->where('key', $key)
            ->first();

        if ($existing === null) {
            return $this->claim($scope, $key, $requestHash, $reference, $leaseSeconds);
        }

        if (! hash_equals($existing->request_hash, $requestHash)) {
            throw new UnipotenceConflict;
        }

        if ($existing->state === UnipotenceState::Completed) {
            throw new ReplayResponse(
                (int) $existing->response_status,
                $existing->response_body ?? [],
                $existing->response_headers ?? [],
            );
        }

        if ($existing->state === UnipotenceState::Indeterminate) {
            throw new OutcomeUnknown;
        }

        if ($existing->locked_until->isFuture()) {
            throw new RequestInFlight;
        }

        $updated = $this->table()
            ->where('id', $existing->getKey())
            ->where('state', UnipotenceState::Claimed->value)
            ->where('claim_token', $existing->claim_token)
            ->where('locked_until', '<=', now())
            ->update([
                'state' => UnipotenceState::Indeterminate->value,
                'indeterminate_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated === 1) {
            $this->logger->warning('Unipotence outcome became indeterminate.', [
                'scope' => $scope,
                'key_hash' => hash('sha256', $key),
                'reference_hash' => $existing->reference === null
                    ? null
                    : hash('sha256', $existing->reference),
                'reason' => 'expired_claim',
            ]);
        }

        throw new OutcomeUnknown;
    }

    private function claimFromStore(
        string $scope,
        string $key,
        string $requestHash,
        ?string $reference,
        int $leaseSeconds,
    ): string {
        $claimToken = (string) Str::uuid();

        try {
            Unipotence::on($this->connectionName)->create([
                'scope' => $scope,
                'key' => $key,
                'request_hash' => $requestHash,
                'reference' => $reference,
                'claim_token' => $claimToken,
                'state' => UnipotenceState::Claimed,
                'locked_until' => now()->addSeconds($leaseSeconds),
            ]);

            return $claimToken;
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }
        }

        return $this->resolveExistingClaim($scope, $key, $requestHash, $reference, $leaseSeconds);
    }

    private function table(): Builder
    {
        return DB::connection($this->connectionName)->table('unipotences');
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = $exception->errorInfo[1] ?? null;

        return $sqlState === '23505'
            || $driverCode === 1062
            || str_contains($exception->getMessage(), 'UNIQUE constraint failed');
    }

    /** @param array<string, mixed> $value */
    private function encrypt(array $value): string
    {
        return Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR));
    }
}
