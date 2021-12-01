<?php

declare(strict_types=1);

namespace Crell\Serde\PropertyHandler;

use Crell\AttributeUtils\ClassAnalyzer;
use Crell\Serde\Formatter\Deformatter;
use Crell\Serde\Formatter\Formatter;

trait ReclosingPropertyWriter
{
    protected readonly ClassAnalyzer $analyzer;

    protected readonly Deformatter $deformatter;

    public function writeReclose(ClassAnalyzer $analyzer, Deformatter $deformatter): static
    {
        $new = clone($this);
        $new->analyzer = $analyzer;
        $new->deformatter = $deformatter;
        return $new;
    }
}
