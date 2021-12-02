<?php

declare(strict_types=1);

namespace Crell\Serde\Formatter;

use Crell\AttributeUtils\ClassAnalyzer;
use Crell\Serde\Serializer;

trait ReclosingFormatter
{
    protected readonly ClassAnalyzer $analyzer;
    protected readonly Serializer $serializer;

    public function formatterReclose(ClassAnalyzer $analyzer, Serializer $serializer): static
    {
        $new = clone($this);
        $new->analyzer = $analyzer;
        $new->serializer = $serializer;
        return $new;
    }
}
