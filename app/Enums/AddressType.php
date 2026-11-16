<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum AddressType: string
{
    use EnumOperation;
    case HOME = 'HOME';
    case HEAD_QUARTER = 'HEAD_QUARTER';
    case BRANCH = 'BRANCH';
    case WORK = 'WORK';
    case BILLING = 'BILLING';
    case SHIPPING = 'SHIPPING';
    case OFFICE = 'OFFICE';
    case MOBILE = 'MOBILE';

    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::HOME => 'Home',
            self::WORK => 'Work',
            self::BILLING => 'Billing',
            self::SHIPPING => 'Shipping',
            self::OTHER => 'Other',
            self::OFFICE => 'Office',
            self::HEAD_QUARTER => 'Headquarters',
            self::BRANCH => 'Branch',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::HOME => 'Primary residential address',
            self::WORK => 'Business or work location',
            self::BILLING => 'Address for financial correspondence',
            self::SHIPPING => 'Delivery destination address',
            self::OTHER => 'Additional address not categorized',
            self::OFFICE => 'Office address',
            self::HEAD_QUARTER => 'Headquarters address',
            self::BRANCH => 'Branch address',
        };
    }

    public function isBusinessRelated(): bool
    {
        return match ($this) {
            self::WORK, self::BILLING, self::SHIPPING => true,self::OFFICE => true,self::HEAD_QUARTER => true,self::BRANCH => true,
            default => false,
        };
    }
}
