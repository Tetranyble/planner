<?php

namespace App\Domain\Publishing\Media;

/** Pure, conservative reducer. Never interprets a simulation as proof of a remote post. */
final class MediaStageTransition
{
    public static function first(string $provider): string
    {
        return match ($provider) {
            'instagram' => 'CREATE_CONTAINER',
            'tiktok' => 'INITIALIZE_VIDEO',
            'youtube' => 'INITIALIZE_UPLOAD_SESSION',
            default => throw new \DomainException('Unsupported media provider.'),
        };
    }

    public static function isProbe(string $operation): bool
    {
        return in_array($operation, ['CHECK_CONTAINER_STATUS', 'FETCH_PUBLISH_STATUS', 'VERIFY_MEDIA_RECEIPT', 'VERIFY_VIDEO_RECEIPT'], true);
    }

    /** A state transition is derived from a normalized receipt, not raw HTTP or caller inputs. */
    public static function next(string $provider, string $operation, array $outcome, int $pollCount): array
    {
        $state = $outcome['state'] ?? 'UNCERTAIN';
        $receipt = $outcome['provider_receipt'] ?? null;
        if ($state === 'BLOCKED') {
            return ['state' => 'HELD', 'operation' => $operation, 'code' => $outcome['code'] ?? 'DELIVERY_BLOCKED'];
        }
        if ($state === 'UNCERTAIN') {
            return ['state' => 'UNCERTAIN', 'operation' => $operation, 'code' => $outcome['code'] ?? 'REMOTE_RESULT_UNKNOWN'];
        }
        if ($state === 'FAILED') {
            return ['state' => 'FAILED', 'operation' => $operation, 'code' => $outcome['code'] ?? 'PROVIDER_REJECTED'];
        }
        if ($state === 'PROCESSING') {
            if (! self::isProbe($operation) || $pollCount >= 12) {
                return ['state' => 'MANUAL_REQUIRED', 'operation' => $operation, 'code' => 'PROVIDER_POLL_LIMIT'];
            }

            return ['state' => 'WAITING_PROVIDER', 'operation' => $operation, 'code' => 'PROVIDER_STILL_PROCESSING'];
        }
        if ($provider === 'instagram') {
            if ($operation === 'CREATE_CONTAINER' && $state === 'CONTAINER_CREATED' && self::validRef($receipt)) {
                return ['state' => 'NEXT_STAGE', 'operation' => 'CHECK_CONTAINER_STATUS', 'receipt' => $receipt, 'code' => 'CONTAINER_RECEIVED'];
            }
            if ($operation === 'CHECK_CONTAINER_STATUS' && $state === 'READY_TO_FINALIZE') {
                return ['state' => 'NEXT_STAGE', 'operation' => 'PUBLISH_CONTAINER', 'code' => 'CONTAINER_FINISHED'];
            }
            if ($operation === 'VERIFY_MEDIA_RECEIPT' && $state === 'VERIFIED' && self::validRef($receipt)) {
                return ['state' => 'CONFIRMED', 'operation' => 'COMPLETE', 'receipt' => $receipt, 'code' => 'PROVIDER_MEDIA_VERIFIED'];
            }
            if ($operation === 'PUBLISH_CONTAINER' && $state === 'PUBLISHED' && self::validRef($receipt)) {
                return ['state' => 'AWAITING_VERIFICATION', 'operation' => 'VERIFY_MEDIA_RECEIPT', 'receipt' => $receipt, 'code' => 'REMOTE_RECEIPT_REQUIRES_VERIFICATION'];
            }
        }
        if ($provider === 'tiktok') {
            if ($operation === 'INITIALIZE_VIDEO' && $state === 'INITIALIZED' && self::validRef($receipt)) {
                return ['state' => 'NEXT_STAGE', 'operation' => 'FETCH_PUBLISH_STATUS', 'receipt' => $receipt, 'code' => 'PUBLISH_REQUEST_ACCEPTED'];
            }
            if ($operation === 'VERIFY_VIDEO_RECEIPT' && $state === 'VERIFIED' && self::validRef($receipt)) {
                return ['state' => 'CONFIRMED', 'operation' => 'COMPLETE', 'receipt' => $receipt, 'code' => 'PROVIDER_VIDEO_VERIFIED'];
            }
            if ($operation === 'FETCH_PUBLISH_STATUS' && $state === 'PUBLISHED' && self::validRef($receipt)) {
                return ['state' => 'AWAITING_VERIFICATION', 'operation' => 'VERIFY_VIDEO_RECEIPT', 'receipt' => $receipt, 'code' => 'REMOTE_RECEIPT_REQUIRES_VERIFICATION'];
            }
        }

        // No YouTube completion until verified resumable Storage streaming and 308 offset recovery.
        return ['state' => 'UNCERTAIN', 'operation' => $operation, 'code' => 'UNEXPECTED_PROVIDER_STAGE_RESULT'];
    }

    private static function validRef(mixed $ref): bool
    {
        return is_string($ref) && (bool) preg_match('/^[a-zA-Z0-9_~.:-]{2,160}$/D', $ref);
    }
}
