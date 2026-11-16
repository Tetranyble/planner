<?php

namespace Modules\Unipotence\Domain\Contracts;

interface UnipotenceRepository
{
    public function claim(
        string $scope,
        string $key,
        string $requestHash,
        ?string $reference,
        int $leaseSeconds,
    ): string;

    /**
     * @param  array<string, mixed>  $responseBody
     * @param  array<string, string>  $responseHeaders
     */
    public function complete(
        string $scope,
        string $key,
        string $requestHash,
        string $claimToken,
        int $responseStatus,
        array $responseBody,
        array $responseHeaders,
    ): void;

    /**
     * @param  array<string, mixed>  $responseBody
     * @param  array<string, string>  $responseHeaders
     */
    public function markIndeterminate(
        string $scope,
        string $key,
        string $requestHash,
        string $claimToken,
        ?int $responseStatus,
        array $responseBody,
        array $responseHeaders = [],
    ): void;

    public function release(string $scope, string $key, string $claimToken): void;

    /**
     * @param  array<string, mixed>  $responseBody
     * @param  array<string, string>  $responseHeaders
     */
    public function completeIndeterminateByReference(
        string $scope,
        string $reference,
        int $responseStatus,
        array $responseBody,
        array $responseHeaders,
    ): int;
}
