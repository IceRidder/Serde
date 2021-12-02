<?php

declare(strict_types=1);

namespace Crell\Serde;

use Crell\AttributeUtils\ClassAnalyzer;
use Crell\Serde\Formatter\Deformatter;
use Crell\Serde\PropertyHandler\PropertyReader;
use Crell\Serde\PropertyHandler\PropertyWriter;

// This exists mainly just to create a closure over the format and formatter.
// But that does simplify a number of functions.
class Deserializer
{
    /** @var PropertyWriter[]  */
    protected readonly array $writers;

    protected readonly Deformatter $deformatter;

    public function __construct(
        protected readonly ClassAnalyzer $analyzer,
        /** @var PropertyWriter[] */
        array $writers,
        Deformatter $deformatter,
    ) {
        $this->deformatter = $deformatter->deformatterReclose($this->analyzer, $this);

        $writerReclose = fn(PropertyWriter $r) => $r->writeReclose($this->analyzer, $this->deformatter);

        $this->writers = array_map($writerReclose, $writers);
    }

    public function deserialize(mixed $decoded, Field $field): mixed
    {
        $writer = $this->findWriter($field);
        $result = $writer->writeValue($field, $decoded);

        return $result;
    }

    protected function findWriter(Field $field): PropertyWriter
    {
        $format = $this->deformatter->format();
        foreach ($this->writers as $w) {
            if ($w->canWrite($field, $format)) {
                return $w;
            }
        }

        throw NoWriterFound::create($field->phpType, $format);
    }
}
