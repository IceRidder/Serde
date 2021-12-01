<?php

declare(strict_types=1);

namespace Crell\Serde\PropertyHandler;

use Crell\AttributeUtils\ClassAnalyzer;
use Crell\Serde\Field;
use Crell\Serde\Formatter\Formatter;

interface PropertyReader
{
    public function readValue(
        callable $recursor,
        Field $field,
        mixed $value,
        mixed $runningValue
    ): mixed;

    public function canRead(Field $field, mixed $value, string $format): bool;

    public function readReclose(ClassAnalyzer $analyzer, Formatter $formatter): static;
}
