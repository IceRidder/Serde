<?php

declare(strict_types=1);

namespace Crell\Serde\PropertyHandler;

use Crell\Serde\Field;
use Crell\Serde\Formatter\Deformatter;

class EnumOnArrayPropertyReader extends EnumPropertyReader
{
    public function writeValue(Field $field, mixed $source): mixed
    {
        if ($source[$field->serializedName] ?? null instanceof \UnitEnum) {
            return $source[$field->serializedName];
        }
        return parent::writeValue($field, $source);
    }

    public function canWrite(Field $field, string $format): bool
    {
        return $field->typeCategory->isEnum() && $format === 'array';
    }
}
