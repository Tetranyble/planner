<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use App\Models\Organisation;
use App\Services\Content\VerifyCanonicalStorageIntegrity;
use Illuminate\Console\Command;

/** Explicit operator-initiated checksum probe, never scheduled or externally exposed. */
final class VerifyPlannerStorageMedia extends Command
{
    protected $signature = 'planner:verify-storage-media {organisation_id} {media_asset_id} {--max-mib=256} {--json}';

    protected $description = 'Verify canonical Storage v3 readStream byte integrity without sending to a social provider';

    public function handle(VerifyCanonicalStorageIntegrity $service): int
    {
        $maximum = max(1, min(1024, (int) $this->option('max-mib'))) * 1024 * 1024;
        if (! config('planner.storage_v3_adapter_verified', false)) {
            $result = ['passed' => false, 'code' => 'STORAGE_ADAPTER_NOT_RELEASED'];
        } else {
            try {
                $org = Organisation::query()->findOrFail((int) $this->argument('organisation_id'));
                $asset = MediaAsset::query()->where('organisation_id', $org->id)
                    ->findOrFail((int) $this->argument('media_asset_id'));
                $result = $service->check($org, $asset, $maximum);
            } catch (\Throwable) {
                $result = ['passed' => false, 'code' => 'CANONICAL_MEDIA_NOT_AVAILABLE'];
            }
        }
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $this->line(($result['passed'] ? 'PASS ' : 'BLOCKED ').$result['code']);
        }

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
