<?php

declare(strict_types=1);

namespace Crell\Serde\PropertyHandler;

use Crell\Serde\ClassDef;
use Crell\Serde\CollectionItem;
use Crell\Serde\Dict;
use Crell\Serde\Field;
use Crell\Serde\Serializer;
use Crell\Serde\TypeCategory;
use Crell\Serde\TypeMap;
use function Crell\fp\pipe;
use function Crell\fp\reduce;
use function Crell\fp\reduceWithKeys;

class ObjectPropertyReader implements PropertyReader
{
    /**
     * @param Serializer $serializer
     * @param Field $field
     * @param object $value
     * @param mixed $runningValue
     * @return mixed
     */
    public function readValue(Serializer $serializer, Field $field, mixed $value, mixed $runningValue): mixed
    {
        // This lets us read private values without messing with the Reflection API.
        $propReader = (fn (string $prop): mixed => $this->$prop ?? null)->bindTo($value, $value);

        /** @var \Crell\Serde\Dict $dict */
        $dict = pipe(
            $serializer->analyzer->analyze($value, ClassDef::class)->properties,
            reduce(new Dict(), fn(Dict $dict, Field $f) => $this->flattenValue($dict, $f, $propReader, $serializer)),
        );

        if ($map = $serializer->typeMapper->typeMapForField($field)) {
            $f = Field::create(serializedName: $map->keyField(), phpType: 'string');
            // The type map field MUST come first so that streaming deformatters
            // can know their context.
            $dict->items = [new CollectionItem(field: $f, value: $map->findIdentifier($value::class)), ...$dict->items];
        }

        return $serializer->formatter->serializeDictionary($runningValue, $field, $dict, $serializer);
    }

    protected function flattenValue(Dict $dict, Field $field, callable $propReader, Serializer $serializer): Dict
    {
        $value = $propReader($field->phpName);
        if ($value === null) {
            return $dict;
        }

        if (!$field->flatten) {
            $dict->items[] = new CollectionItem(field: $field, value: $value);
            return $dict;
        }

        if ($field->typeCategory === TypeCategory::Array) {
            // This really wants to be explicit partial application. :-(
            $c = fn (Dict $dict, $val, $key) => $this->reduceArrayElement($dict, $val, $key, $serializer->typeMapper->typeMapForField($field));
            return reduceWithKeys($dict, $c)($value);
        }

        if ($field->typeCategory === TypeCategory::Object) {
            $subPropReader = (fn (string $prop): mixed => $this->$prop ?? null)->bindTo($value, $value);
            // This really wants to be explicit partial application. :-(
            $c = fn (Dict $dict, Field $prop) => $this->reduceObjectProperty($dict, $prop, $subPropReader, $serializer);
            $properties = $serializer->analyzer->analyze($value::class, ClassDef::class)->properties;
            $dict = reduce($dict, $c)($properties);
            if ($map = $serializer->typeMapper->typeMapForField($field)) {
                $f = Field::create(serializedName: $map->keyField(), phpType: 'string');
                // The type map field MUST come first so that streaming deformatters
                // can know their context.
                $dict->items = [new CollectionItem(field: $f, value: $map->findIdentifier($value::class)), ...$dict->items];
            }
            return $dict;
        }

        // @todo Better exception.
        throw new \RuntimeException('Invalid flattening field type');
    }

    protected function reduceArrayElement(Dict $dict, $val, $key, ?TypeMap $map): Dict
    {
        $extra = [];
        if ($map) {
            $extra[$map->keyField()] = $map->findIdentifier($val::class);
        }
        $f = Field::create(serializedName: "$key", phpType: \get_debug_type($val), extraProperties: $extra);
        $dict->items[] = new CollectionItem(field: $f, value: $val);
        return $dict;
    }

    protected function reduceObjectProperty(Dict $dict, Field $prop, callable $subPropReader, Serializer $serializer): Dict
    {
        if ($prop->flatten) {
            return $this->flattenValue($dict, $prop, $subPropReader, $serializer);
        }

        $dict->items[] = new CollectionItem(field: $prop, value: $subPropReader($prop->phpName));
        return $dict;
    }

    public function canRead(Field $field, mixed $value, string $format): bool
    {
        return $field->typeCategory === TypeCategory::Object;
    }
}
