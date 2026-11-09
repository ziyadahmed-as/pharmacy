<?php

namespace App\Enums;

/**
 * Application roles (AGENTS.md section 3 / BRD 4.3).
 * PS (use case file) and PP (BRD) are the same role: pharmacy_staff.
 */
enum Role: string
{
    case SystemAdmin = 'system_admin';
    case Owner = 'owner';
    case PharmacyManager = 'pharmacy_manager';
    case PharmacyStaff = 'pharmacy_staff';

    public function code(): string
    {
        return match ($this) {
            self::SystemAdmin => 'SA',
            self::Owner => 'OW',
            self::PharmacyManager => 'PM',
            self::PharmacyStaff => 'PS',
        };
    }

    public function label(): string
    {
        return __('roles.'.$this->value);
    }

    /** MFA is optional for these roles only (UC-33 step 6). */
    public function allowsMfa(): bool
    {
        return $this !== self::PharmacyStaff;
    }

    /** SA never reaches patient, prescription, sales, cost or financial data (AGENTS section 5). */
    public function hasBusinessDataAccess(): bool
    {
        return $this !== self::SystemAdmin;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
