<?php

namespace App\Enums;

enum UserRole: string
{
    case PHARMACY_STAFF = 'pharmacy_staff';
    case PHARMACY_MANAGER = 'pharmacy_manager';
    case OWNER = 'owner';
    case SYSTEM_ADMINISTRATOR = 'system_administrator';

    public function code(): string
    {
        return match ($this) {
            self::PHARMACY_STAFF => 'PS',
            self::PHARMACY_MANAGER => 'PM',
            self::OWNER => 'OW',
            self::SYSTEM_ADMINISTRATOR => 'SA',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PHARMACY_STAFF => 'Pharmacy Staff',
            self::PHARMACY_MANAGER => 'Pharmacy Manager',
            self::OWNER => 'Owner',
            self::SYSTEM_ADMINISTRATOR => 'System Administrator',
        };
    }

    public function allowsMfa(): bool
    {
        return in_array($this, [self::PHARMACY_MANAGER, self::SYSTEM_ADMINISTRATOR], true);
    }

    public function hasBusinessDataAccess(): bool
    {
        return $this !== self::SYSTEM_ADMINISTRATOR;
    }
}
