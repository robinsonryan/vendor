<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Enums;

enum DocumentSource: string
{
    case Scan = 'scan';
    case Email = 'email';
    case Download = 'download';
    case Manual = 'manual';
    case PurchaseOrder = 'purchase_order';
}
