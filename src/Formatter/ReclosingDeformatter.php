<?php

declare(strict_types=1);

namespace Crell\Serde\Formatter;

use Crell\AttributeUtils\ClassAnalyzer;
use Crell\Serde\Deserializer;

trait ReclosingDeformatter
{
    protected readonly ClassAnalyzer $analyzer;
    protected readonly Deserializer $deserializer;

    public function deformatterReclose(ClassAnalyzer $analyzer, Deserializer $deserializer): static
    {
        $new = clone($this);
        $new->analyzer = $analyzer;
        $new->deserializer = $deserializer;
        return $new;
    }
}
