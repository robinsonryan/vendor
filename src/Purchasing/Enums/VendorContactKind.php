<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Enums;

/**
 * What RecordVendorContact can record. The first four are the intake contract's
 * `contacts[].kind`; `account_number` carries the contract's `vendor.account_number`
 * down the same path.
 */
enum VendorContactKind: string
{
    case RemitTo = 'remit_to';
    case Phone = 'phone';
    case ApEmail = 'ap_email';
    case SalesRep = 'sales_rep';
    case AccountNumber = 'account_number';
}
