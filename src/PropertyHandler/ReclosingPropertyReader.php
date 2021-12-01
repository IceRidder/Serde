<?php

declare(strict_types=1);

namespace Crell\Serde\PropertyHandler;

use Crell\AttributeUtils\ClassAnalyzer;
use Crell\Serde\Formatter\Formatter;

trait ReclosingPropertyReader
{
    protected readonly ClassAnalyzer $analyzer;

    protected readonly Formatter $formatter;

    public function readReclose(ClassAnalyzer $analyzer, Formatter $formatter): static
    {
        $new = clone($this);
        $new->analyzer = $analyzer;
        $new->formatter = $formatter;
        return $new;
    }
}
