<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Enums;

enum LineKind: string
{
    case Part = 'part';
    case Consumable = 'consumable';
    case Freight = 'freight';
    case Tax = 'tax';
    case CoreCharge = 'core_charge';
    case CoreCredit = 'core_credit';
    case Discount = 'discount';
    case Fee = 'fee';
    case Service = 'service';
    case Other = 'other';

    /**
     * A stock line is the only kind of line that gets a vendor item, matching
     * and flags.
     */
    public function isStock(): bool
    {
        return $this === self::Part || $this === self::Consumable;
    }
}
