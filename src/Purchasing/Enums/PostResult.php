<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Enums;

enum PostResult: string
{
    case Created = 'created';
    case Replaced = 'replaced';
    case Unchanged = 'unchanged';
}
