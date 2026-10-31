<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Enums;

enum DocumentKind: string
{
    case Invoice = 'invoice';
    case CardReceipt = 'card_receipt';
    case CreditMemo = 'credit_memo';
    case SalesOrder = 'sales_order';
}
