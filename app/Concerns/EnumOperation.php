<?php

declare(strict_types=1);

namespace App\Concerns;

use BackedEnum;
use ValueError;

/**
 * Helper operations for PHP 8.1+ Enums.
 *
 * Intended to be used inside an enum:
 *
 * enum Status: string {
 *     use \App\Traits\EnumOperation;
 * }
 */
trait EnumOperation
{
    /**
     * Get all enum case names.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * Get all enum case values (or case names if pure enum).
     *
     * @return list<int|string>
     */
    public static function values(): array
    {
        if (is_subclass_of(static::class, BackedEnum::class)) {
            return array_column(self::cases(), 'value');
        }

        return self::names();
    }

    /**
     * Get enum as [NAME => VALUE] associative array.
     *
     * @return array<string, int|string>
     */
    public static function asArray(): array
    {
        return array_combine(self::names(), self::values());
    }

    /**
     * Check if a given value exists in enum values.
     */
    public static function exists(int|string $value): bool
    {
        if (is_subclass_of(static::class, BackedEnum::class)) {
            return static::tryFrom($value) !== null;
        }

        return in_array($value, self::values(), true);
    }

    /**
     * Get enum case from its exact name (case-sensitive).
     *
     * @throws ValueError if the name is invalid.
     */
    public static function fromName(string $name): static
    {
        return static::tryFromName($name)
            ?? throw new ValueError("{$name} is not a valid case name for enum ".static::class);
    }

    /**
     * Try to get enum case from its exact name (returns null on failure).
     */
    public static function tryFromName(string $name): ?static
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Get enum case from name (case-insensitive).
     *
     * @throws ValueError if the name is invalid.
     */
    public static function fromInsensitiveName(string $name): static
    {
        return static::tryFromInsensitiveName($name)
            ?? throw new ValueError("{$name} is not a valid case name for enum ".static::class);
    }

    /**
     * Try to get enum case from name (case-insensitive, returns null on failure).
     */
    public static function tryFromInsensitiveName(string $name): ?static
    {
        foreach (self::cases() as $case) {
            if (strcasecmp($case->name, $name) === 0) {
                return $case;
            }
        }

        return null;
    }
}
