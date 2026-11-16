<?php

namespace App\Services\Social;

use App\Models\MetaDataDeletionRequest;
use App\Repository\Contracts\MetaDataDeletionRepositoryContract;
use Illuminate\Support\Facades\Log;

/** Entry point for signed, unauthenticated Meta callbacks (not a Kinship-user route). */
final class HandleMetaDataDeletion
{
    public function __construct(
        private readonly MetaSignedRequestVerifier $verifier,
        private readonly MetaDataDeletionRepositoryContract $repository,
    ) {}

    public function receive(string $signedRequest): MetaDataDeletionRequest
    {
        $payload = $this->verifier->verify($signedRequest);

        return $this->repository->accept($payload['user_id'], hash('sha256', $signedRequest));
    }

    /** For the scheduled worker. */
    public function processPending(int $limit = 20): array
    {
        $result = ['attempted' => 0, 'completed' => 0, 'needs_review' => 0, 'failed' => 0];
        foreach ($this->repository->dueIds(max(1, min($limit, 100))) as $requestId) {
            $result['attempted']++;
            try {
                $this->repository->process($requestId);
                $status = MetaDataDeletionRequest::query()->whereKey($requestId)->value('state');
                if ($status === 'COMPLETED') {
                    $result['completed']++;
                } else {
                    $result['needs_review']++;
                }
            } catch (\Throwable $exception) {
                // No exception text, provider ID, credential or payload in process output.
                $this->repository->fail($requestId, 'PROCESSING_ERROR');
                Log::warning('meta_deletion_processing_failed', [
                    'request_id' => $requestId, 'error_code' => 'PROCESSING_ERROR',
                ]);
                $result['failed']++;
            }
        }

        return $result;
    }
}
