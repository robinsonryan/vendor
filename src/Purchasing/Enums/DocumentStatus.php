<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Enums;

enum DocumentStatus: string
{
    case Complete = 'complete';
    case LinesUnsettled = 'lines_unsettled';
}
