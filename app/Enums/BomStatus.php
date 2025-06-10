<?php
namespace App\Enums;

enum BomStatus: int
{
    case Active = 1;
    case InActive = 0;
    case Complete = 2;

    public function label(): string
    {
        return match($this) {
            self::Active => 'Active',
            self::InActive => 'InActive',
            self::Complete => 'Complete',
        };
    }
}