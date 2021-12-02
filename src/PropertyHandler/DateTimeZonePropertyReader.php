<?php

declare(strict_types=1);

namespace Crell\Serde\PropertyHandler;

use Crell\Serde\Field;
use Crell\Serde\Formatter\Deformatter;
use Crell\Serde\Formatter\Formatter;
use Crell\Serde\PropertyHandler\ReclosingPropertyReader;
use Crell\Serde\SerdeError;

class DateTimeZonePropertyReader implements PropertyReader, PropertyWriter
{
    use ReclosingPropertyReader;
    use ReclosingPropertyWriter;

    /**
     * @param Field $field
     * @param \DateTimeZone $value
     * @param mixed $runningValue
     * @return mixed
     */
    public function readValue(Field $field, mixed $value, mixed $runningValue): mixed
    {
        $string = $value->getName();
        return $this->formatter->serializeString($runningValue, $field, $string);
    }

    public function canRead(Field $field, mixed $value, string $format): bool
    {
        return $field->phpType === \DateTimeZone::class;
    }

    public function writeValue(Field $field, mixed $source): mixed
    {
        $string = $this->deformatter->deserializeString($source, $field);

        if ($string === SerdeError::Missing) {
            return null;
        }

        return new \DateTimeZone($string);
    }

    public function canWrite(Field $field, string $format): bool
    {
        return $field->phpType === \DateTimeZone::class;
    }
}
