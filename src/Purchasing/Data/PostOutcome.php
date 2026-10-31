<?php

declare(strict_types=1);

namespace RobinsonRyan\Vendor\Purchasing\Data;

use RobinsonRyan\Vendor\Purchasing\Enums\PostResult;
use RobinsonRyan\Vendor\Purchasing\Models\VendorDocument;

final readonly class PostOutcome
{
    public function __construct(
        public VendorDocument $document,
        public PostResult $result,
    ) {}
}
