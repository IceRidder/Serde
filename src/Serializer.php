<?php

declare(strict_types=1);

namespace Crell\Serde;

use Crell\AttributeUtils\ClassAnalyzer;
use Crell\Serde\Formatter\Formatter;
use Crell\Serde\PropertyHandler\PropertyReader;
use Crell\Serde\PropertyHandler\PropertyWriter;
use function Crell\fp\amap;
use function Crell\fp\pipe;

// This exists mainly just to create a closure over the formatter.
// But that does simplify a number of functions.
class Serializer
{
    /**
     * Used for circular reference loop detection.
     */
    protected array $seenObjects = [];

    /** @var PropertyReader[] */
    protected readonly array $readers;

    protected readonly Formatter $formatter;

    public function __construct(
        protected readonly ClassAnalyzer $analyzer,
        /** @var PropertyReader[]  */
        array $readers,
        Formatter $formatter,
    ) {
        $this->formatter = $formatter->formatterReclose($this->analyzer, $this);

        $readerReclose = fn(PropertyReader $r) => $r->readReclose($this->analyzer, $this->formatter);

        $this->readers = array_map($readerReclose, $readers);
    }

    public function serialize(mixed $value, mixed $runningValue, Field $field): mixed
    {
        // Had we partial application, we could easily factor the loop detection
        // out to its own method. Sadly it's needlessly convoluted to do otherwise.
        if (is_object($value)) {
            if (in_array($value, $this->seenObjects, true)) {
                throw CircularReferenceDetected::create($value);
            }
            $this->seenObjects[] = $value;
        }

        $reader = $this->findReader($field, $value);
        $result = $reader->readValue($field, $value, $runningValue);

        if (is_object($value)) {
            array_pop($this->seenObjects);
        }

        return $result;
    }

    protected function findReader(Field $field, mixed $value): PropertyReader
    {
        $format = $this->formatter->format();
        foreach ($this->readers as $r) {
            if ($r->canRead($field, $value, $format)) {
                return $r;
            }
        }

        throw NoReaderFound::create($field->phpType, $format);
    }
}
