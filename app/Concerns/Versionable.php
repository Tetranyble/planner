<?php

namespace App\Concerns;

use App\Enums\StatusEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

trait Versionable
{
    /**
     * Real PHP property (NOT an Eloquent attribute) to guard against recursion.
     */
    protected bool $versioningSkip = false;

    public function versionParentColumn(): string
    {
        return 'id';
    }

    /**
     * Columns that must NOT be copied to the new versioned row.
     * - id/uuid: regenerate
     * - timestamps: reset
     * - status/effective_to: managed by the trait
     * - deleted_at: new row must start undeleted
     */
    public function versionExcludedColumns(): array
    {
        return [
            'id',
            'uuid',
            'created_at',
            'updated_at',
            'deleted_at',
            'effective_to',
            'status',
        ];
    }

    protected static function bootVersionable(): void
    {
        static::updating(function (Model $model) {
            if ($model->versioningSkip) {
                return true;
            }

            return $model->forkVersion();
        });
    }

    /**
     * Deactivate current row, create a fresh copy, and re-hydrate `$this`
     * so the caller is left pointing at the new version.
     *
     * Returns false to cancel the original UPDATE.
     */
    protected function forkVersion(): bool
    {
        $now = now();

        // 1. Snapshot intended state BEFORE touching the old row
        $newAttributes = $this->prepareVersionAttributes($now);

        // Capture caller's dirty changes to restore later
        $intendedDirty = $this->getDirty();

        // 2. Deactivate old row with ONLY status/effective_to changed
        $this->versioningSkip = true;
        $this->rollbackChanges($intendedDirty);
        $this->status = StatusEnum::INACTIVE->value;
        $this->effective_to = $now;
        $this->save();
        $this->versioningSkip = false;

        // 3. Restore caller's intended dirty values on $this (harmless here)
        $this->applyChanges($intendedDirty);

        // 4. Link to parent
        $newAttributes = $this->resolveParent($newAttributes);

        // 5. Create new version
        $new = static::query()->create($newAttributes);

        // 6. Rehydrate
        $this->setRawAttributes($new->getAttributes(), true);
        $this->exists = true;
        $this->syncOriginal();

        return false;
    }

    public function resolveParent(array $newAttributes): array
    {
        $newAttributes[$this->versionParentColumn()] = $this->getKey();

        return $newAttributes;
    }

    public function applyChanges(array $intendedDirty): void
    {
        foreach ($intendedDirty as $key => $value) {
            if (! in_array($key, ['status', 'effective_to'])) {
                $this->setAttribute($key, $value);
            }
        }
    }

    /**
     * @return mixed[]
     */
    public function prepareVersionAttributes(CarbonInterface $now): array
    {
        $newAttributes = array_merge($this->getAttributes(), $this->getDirty());
        foreach ($this->versionExcludedColumns() as $column) {
            unset($newAttributes[$column]);
        }
        $newAttributes['created_at'] = $now;
        $newAttributes['updated_at'] = $now;
        $newAttributes['effective_from'] = $now;
        $newAttributes['status'] = StatusEnum::ACTIVE->value;

        return $newAttributes;
    }

    public function rollbackChanges(array $intendedDirty): void
    {
        foreach (array_keys($intendedDirty) as $key) {
            if (! in_array($key, ['status', 'effective_to'])) {
                $this->setAttribute($key, $this->getRawOriginal($key));
            }
        }
    }
}
