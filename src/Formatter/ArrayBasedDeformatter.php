<?php

declare(strict_types=1);

namespace Crell\Serde\Formatter;

use Crell\AttributeUtils\ClassAnalyzer;
use Crell\Serde\ClassDef;
use Crell\Serde\Field;
use Crell\Serde\NoTypeMapDefinedForKey;
use Crell\Serde\SerdeError;
use Crell\Serde\TypeCategory;
use Crell\Serde\TypeMap;
use function Crell\fp\reduceWithKeys;

/**
 * Utility implementations for array-based formats.
 *
 * Formats that work by first converting the serialized format to/from an
 * array can use this trait to handle creating the array bits, then
 * implement the initialize/finalize logic specific for that format.
 */
trait ArrayBasedDeformatter
{
    public function deserializeInt(mixed $decoded, Field $field): int|SerdeError
    {
        return $decoded[$field->serializedName] ?? SerdeError::Missing;
    }

    public function deserializeFloat(mixed $decoded, Field $field): float|SerdeError
    {
        return $decoded[$field->serializedName] ?? SerdeError::Missing;
    }

    public function deserializeBool(mixed $decoded, Field $field): bool|SerdeError
    {
        return $decoded[$field->serializedName] ?? SerdeError::Missing;
    }

    public function deserializeString(mixed $decoded, Field $field): string|SerdeError
    {
        return $decoded[$field->serializedName] ?? SerdeError::Missing;
    }

    public function deserializeSequence(mixed $decoded, Field $field, callable $recursor): array|SerdeError
    {
        if (!isset($decoded[$field->serializedName])) {
            return SerdeError::Missing;
        }

        return $this->upcastArray($decoded[$field->serializedName], $recursor, $field);
    }

    public function deserializeDictionary(mixed $decoded, Field $field, callable $recursor): array|SerdeError
    {
        if (!isset($decoded[$field->serializedName])) {
            return SerdeError::Missing;
        }
        // @todo Still unsure if this should be an exception instead.
        if (!is_array($decoded[$field->serializedName])) {
            return SerdeError::FormatError;
        }

        return $this->upcastArray($decoded[$field->serializedName], $recursor, $field);
    }

    /**
     * Deserializes all elements of an array, through the recursor.
     */
    protected function upcastArray(array $data, callable $recursor, Field $field): array
    {
        $upcast = function (array $ret, mixed $v, int|string $k) use ($recursor, $data, $field) {
            $f = $field->forType(serializedName: "$k", phpType: $field->listType($v));
            $ret[$k] = $recursor($data, $f);
            return $ret;
        };

        return reduceWithKeys([], $upcast)($data);
    }

    /**
     * @param mixed $decoded
     * @param Field $field
     * @param callable $recursor
     * @param TypeMap|null $typeMap
     * @return array|SerdeError
     */
    public function deserializeObject(mixed $decoded, Field $field, callable $recursor, ?TypeMap $typeMap): array|SerdeError
    {
        if (!isset($decoded[$field->serializedName])) {
            return SerdeError::Missing;
        }
        // @todo Still unsure if this should be an exception instead.
        if (!is_array($decoded[$field->serializedName])) {
            return SerdeError::FormatError;
        }

        // Now that we have an array of the raw data, some values need to be
        // recursively upcast to objects themselves, based on the information
        // in the object metadata for the target object.

        $data = $decoded[$field->serializedName];

        $usedNames = [];
        $collectingArray = null;
        /** @var Field[] $collectingObjects */
        $collectingObjects = [];

        $ret = [];

        foreach ($field->properties($data) as $propField) {
            $usedNames[] = $propField->serializedName;
            if ($propField->flatten && $propField->typeCategory === TypeCategory::Array) {
                $collectingArray = $propField;
            } elseif ($propField->flatten && $propField->typeCategory === TypeCategory::Object) {
                $collectingObjects[] = $propField;
            } else {
                $ret[$propField->serializedName] = ($propField->typeCategory->isEnum() || $propField->typeCategory->isCompound())
                    ? $recursor($data, $propField)
                    : $data[$propField->serializedName] ?? SerdeError::Missing;
            }
        }

        // Any other values are for a collecting field, if any,
        // but may need to be upcast themselves.

        // First upcast any values that will become properties of a collecting object.
        $remaining = $this->getRemainingData($data, $usedNames);
        foreach ($collectingObjects as $collectingField) {
            $remaining = $this->getRemainingData($remaining, $usedNames);
            $nestedProps = $collectingField->properties($remaining);
            foreach ($nestedProps as $propField) {
                $ret[$propField->serializedName] = ($propField->typeCategory->isEnum() || $propField->typeCategory->isCompound())
                    ? $recursor($data, $propField)
                    : $remaining[$propField->serializedName] ?? SerdeError::Missing;
                $usedNames[] = $propField->serializedName;
            }
        }

        // Then IF the remaining data is going to be collected to an array,
        // and that array has a type map, upcast all elements of that array to
        // the appropriate type.
        $remaining = $this->getRemainingData($remaining, $usedNames);
        if ($collectingArray?->typeMap) {
            foreach ($remaining as $k => $v) {
                $ret[$k] = $recursor($remaining, $collectingArray->forArrayType(serializedName: "$k", dict: $v));
            }
        } else {
            // Otherwise, just tack on whatever is left to the processed data.
            $ret = [...$ret, ...$remaining];
        }

        return $ret;
    }

    /**
     * Gets the property list for a given object.
     *
     * We need to know the object properties to deserialize to.
     * However, that list may be modified by the type map, as
     * the type map is in the incoming data.
     * The key field is kept in the data so that the property writer
     * can also look up the right type.
     */
    protected function propertyList(Field $field, ?TypeMap $map, array $data): array
    {
        $class = $field->getTargetClass($data);

        return $class ?
            $this->getAnalyzer()->analyze($class, ClassDef::class)->properties
            : [];
    }

    protected function getTargetClass(Field $field, ?TypeMap $map, array $dict): ?string
    {
        if (!$map) {
            return $field->phpType;
        }

        if (! $key = ($dict[$map->keyField()] ?? null)) {
            return null;
        }

        if (!$class = $map->findClass($key)) {
            return null;
        }

        return $class;
    }

    public function getRemainingData(mixed $source, array $used): array
    {
        return array_diff_key($source, array_flip($used));
    }

    /**
     * Returns a class analyzer.
     *
     * Classes using this trait must provide a class analyzer via this method.
     *
     * @return ClassAnalyzer
     */
    abstract protected function getAnalyzer(): ClassAnalyzer;
}
