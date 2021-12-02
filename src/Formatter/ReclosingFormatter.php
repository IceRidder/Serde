<?php

declare(strict_types=1);

namespace Crell\Serde\Formatter;

use Crell\AttributeUtils\ClassAnalyzer;

trait ReclosingFormatter
{
    protected readonly ClassAnalyzer $analyzer;

    public function formatterReclose(ClassAnalyzer $analyzer): static
    {
        $new = clone($this);
        $new->analyzer = $analyzer;
        return $new;
    }
}
