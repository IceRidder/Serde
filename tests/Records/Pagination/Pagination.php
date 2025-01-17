<?php

declare(strict_types=1);

namespace Crell\Serde\Records\Pagination;

use Crell\Serde\Field;

class Pagination
{
    public function __construct(
        public int $total,
        public int $offset,
        public int $limit,
    ) {}
}
