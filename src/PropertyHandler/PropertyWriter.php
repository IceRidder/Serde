<?php

declare(strict_types=1);

namespace Crell\Serde\PropertyHandler;

use Crell\AttributeUtils\ClassAnalyzer;
use Crell\Serde\Field;
use Crell\Serde\Formatter\Deformatter;

interface PropertyWriter
{
    public function writeValue(Field $field, mixed $source): mixed;

    public function canWrite(Field $field, string $format): bool;

    public function writeReclose(ClassAnalyzer $analyzer, Deformatter $deformatter): static;
}
