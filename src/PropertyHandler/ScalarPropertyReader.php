<?php

declare(strict_types=1);

namespace Crell\Serde\PropertyHandler;

use Crell\Serde\Field;
use Crell\Serde\Formatter\Deformatter;
use Crell\Serde\Formatter\Formatter;
use Crell\Serde\TypeCategory;

class ScalarPropertyReader implements PropertyReader, PropertyWriter
{
    use ReclosingPropertyReader;
    use ReclosingPropertyWriter;

    public function readValue(callable $recursor, Field $field, mixed $value, mixed $runningValue): mixed
    {
        return match ($field->phpType) {
            'int' => $this->formatter->serializeInt($runningValue, $field, $value),
            'float' => $this->formatter->serializeFloat($runningValue, $field, $value),
            'bool' => $this->formatter->serializeBool($runningValue, $field, $value),
            'string' => $this->formatter->serializeString($runningValue, $field, $value),
        };
    }

    public function writeValue(callable $recursor, Field $field, mixed $source): mixed
    {
        return match ($field->phpType) {
            'int' => $this->deformatter->deserializeInt($source, $field),
            'float' => $this->deformatter->deserializeFloat($source, $field),
            'bool' => $this->deformatter->deserializeBool($source, $field),
            'string' => $this->deformatter->deserializeString($source, $field),
        };
    }

    public function canRead(Field $field, mixed $value, string $format): bool
    {
        return $field->typeCategory === TypeCategory::Scalar;
    }

    public function canWrite(Field $field, string $format): bool
    {
        return $field->typeCategory === TypeCategory::Scalar;
    }
}
