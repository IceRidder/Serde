<?php

declare(strict_types=1);

namespace Crell\Serde\PropertyHandler;

use Crell\Serde\Field;
use Crell\Serde\Formatter\Deformatter;
use Crell\Serde\Formatter\Formatter;
use Crell\Serde\SerdeError;
use Crell\Serde\TypeCategory;

class EnumPropertyReader implements PropertyReader, PropertyWriter
{
    use ReclosingPropertyReader;
    use ReclosingPropertyWriter;

    public function readValue(Field $field, mixed $value, mixed $runningValue): mixed
    {
        $scalar = $value->value ?? $value->name;

        return match (true) {
            is_int($scalar) => $this->formatter->serializeInt($runningValue, $field, $scalar),
            is_string($scalar) => $this->formatter->serializeString($runningValue, $field, $scalar),
        };
    }

    public function canRead(Field $field, mixed $value, string $format): bool
    {
        return $field->typeCategory->isEnum();
    }

    public function writeValue(Field $field, mixed $source): mixed
    {
        // It's kind of amusing that both of these work, but they work.
        $val = match ($field->typeCategory) {
            TypeCategory::UnitEnum => $this->deformatter->deserializeString($source, $field),
            TypeCategory::IntEnum => $this->deformatter->deserializeInt($source, $field),
            TypeCategory::StringEnum => $this->deformatter->deserializeString($source, $field),
        };

        if ($val === SerdeError::Missing) {
            return $val;
        }

        // It's kind of amusing that both of these work, but they work.
        return match ($field->typeCategory) {
            TypeCategory::UnitEnum => (new \ReflectionEnum($field->phpType))->getCase($val)->getValue(),
            TypeCategory::IntEnum, TypeCategory::StringEnum => $field->phpType::from($val),
        };
    }

    public function canWrite(Field $field, string $format): bool
    {
        return $field->typeCategory->isEnum();
    }

}
