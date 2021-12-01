<?php

declare(strict_types=1);

namespace Crell\Serde;

use Attribute;
use Crell\AttributeUtils\ClassAnalyzer;
use Crell\AttributeUtils\Excludable;
use Crell\AttributeUtils\FromReflectionProperty;
use Crell\AttributeUtils\HasSubAttributes;
use Crell\Serde\Renaming\LiteralName;
use Crell\Serde\Renaming\RenamingStrategy;
use function Crell\fp\indexBy;
use function Crell\fp\pipe;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Field implements FromReflectionProperty, HasSubAttributes, Excludable
{
    use Evolvable;

    /**
     * The type map, if any, that applies to this field.
     */
    public readonly ?TypeMap $typeMap;

    /**
     * The type field, if any, that applies to this field.
     *
     * This property holds any type-specific information defined.
     */
    public readonly ?TypeField $typeField;

    /**
     * The native PHP type, as the reflection system defines it.
     */
    public readonly string $phpType;

    /**
     * The property name, not to be confused with the desired serialized $name.
     */
    public readonly string $phpName;

    /**
     * The category of type this Field refers to.
     */
    public readonly TypeCategory $typeCategory;

    /**
     * The serialized name of this field.
     */
    public readonly string $serializedName;

    /**
     * The default value this field should be assigned, if any.
     */
    public readonly mixed $defaultValue;

    /**
     * Whether or not to use the code-defined default on deserialization if a value is not provided.
     */
    public readonly bool $shouldUseDefault;

    /**
     * The renaming mechanism used for this field.
     *
     * This property is unset after the analysis phase to minimize
     * the serialized size of this object.
     */
    protected ?RenamingStrategy $rename;

    /**
     * Additional key/value pairs to be included with an object.
     *
     * Only viable on object properties, and really not something
     * you should use yourself.
     *
     * @internal
     */
    public readonly array $extraProperties;

    /**
     * Assigned at runtime.
     */
    protected readonly ClassAnalyzer $analyzer;

    /**
     * Assigned at runtime.
     */
    protected readonly array $typeMaps;

    public const TYPE_NOT_SPECIFIED = '__NO_TYPE__';

    public function __construct(
        /** A custom name to use for this field */
        ?string $serializedName = null,
        /** Specify a field renaming strategy. Usually you can use the Cases enum. */
        ?RenamingStrategy $renameWith = null,
        /** Use this default value if none is specified. */
        mixed $default = null,
        /** True to use the default value on deserialization. False to skip setting it entirely. */
        protected readonly bool $useDefault = true,
        /** True to flatten an array on serialization and collect into it when deserializing. */
        public readonly bool $flatten = false,
        /** Set true to exclude this field from serialization entirely. */
        public readonly bool $exclude = false,
    ) {
        if ($default) {
            $this->defaultValue = $default;
        }
        // Upcast the literal serialized name to a converter if appropriate.
        $this->rename ??=
            $renameWith
            ?? ($serializedName ? new LiteralName($serializedName) : null);
    }

    public function fromReflection(\ReflectionProperty $subject): void
    {
        $this->phpName = $subject->name;
        $this->phpType ??= $this->getNativeType($subject);

        $constructorDefault = $this->getDefaultValueFromConstructor($subject);

        $this->shouldUseDefault
            ??= $this->useDefault
            && ($subject->hasDefaultValue() || $constructorDefault !== SerdeError::NoDefaultValue)
        ;

        if ($this->shouldUseDefault) {
            $this->defaultValue
                ??= $subject->getDefaultValue()
                ?? $constructorDefault
            ;
        }

        $this->finalize();
    }

    protected function getDefaultValueFromConstructor(\ReflectionProperty $subject): mixed
    {
        /** @var array<string, \ReflectionParameter> $params */
        $params = pipe($subject->getDeclaringClass()?->getConstructor()?->getParameters() ?? [],
            indexBy(fn(\ReflectionParameter $p) => $p->getName()),
        );

        $param = $params[$subject->getName()] ?? null;

        return $param?->isDefaultValueAvailable()
            ? $param->getDefaultValue()
            : SerdeError::NoDefaultValue;
    }

    protected function finalize(): void
    {
        // We cannot compute these until we have the PHP type,
        // but they can still be determined entirely at analysis time
        // and cached.
        $this->typeCategory ??= $this->deriveTypeCategory();
        $this->serializedName ??= $this->deriveSerializedName();

        // Ensure a type-safe default.
        $this->extraProperties ??= [];

        // We don't need this object anymore, so clear it to minimize
        // the serialized size of this object.
        unset($this->rename);
    }

    protected function enumType(string $phpType): TypeCategory
    {
        return match ((new \ReflectionEnum($phpType))?->getBackingType()?->getName()) {
            'int' => TypeCategory::IntEnum,
            'string' => TypeCategory::StringEnum,
            null => TypeCategory::UnitEnum,
        };
    }

    public function subAttributes(): array
    {
        return [
            TypeMap::class => 'fromTypeMap',
            TypeField::class => 'fromTypeField',
        ];
    }

    public function fromTypeMap(?TypeMap $map): void
    {
        // This may assign to null, which is OK as that will
        // evaluate to false when we need it to.
        $this->typeMap = $map;
    }

    public function fromTypeField(?TypeField $typeField): void
    {
        if ($typeField && !$typeField?->acceptsType($this->phpType)) {
            throw FieldTypeIncompatible::create($typeField::class, $this->phpType);
        }
        // This may assign to null, which is OK as that will
        // evaluate to false when we need it to.
        $this->typeField = $typeField;
    }

    public static function createRoot(
        ClassAnalyzer $analyzer,
        array $typeMaps = [],
        string $serializedName = null,
        string $phpType = null,
    ): static
    {
        $new = new static();
        $new->serializedName = $serializedName;
        $new->phpName ??= $serializedName;
        $new->phpType = $phpType;
        $new->typeField = null;

        $new->analyzer = $analyzer;
        $new->typeMaps = $typeMaps;

        $new->typeMap = $analyzer->analyze($phpType, ClassDef::class)?->typeMap;

        $new->finalize();
        return $new;
    }

    public function forType(string $serializedName, string $phpType, array $extraProperties = []): static
    {
        $new = new static();
        $new->serializedName = $serializedName;
        $new->phpName ??= $serializedName;
        $new->phpType = $phpType;
        $new->typeField = null;
        $new->extraProperties = $extraProperties;


        $new->analyzer = $this->analyzer;
        $new->typeMaps = $this->typeMaps;

        $new->finalize();

        if ($new->typeCategory === TypeCategory::Object) {
            $new->typeMap = $this->analyzer->analyze($phpType, ClassDef::class)?->typeMap;
        }

        return $new;
    }

    public function forArrayType(string $serializedName, array $dict): static
    {
        if ($this->typeCategory !== TypeCategory::Array) {
            // @todo Better exception
            throw new \RuntimeException('Only works on array fields.');
        }

        $class = $this->typeMap->findClass($dict[$this->typeMap->keyField()]);

        return $this->forType($serializedName, $class);
    }

    public function propertiesForValue(object $value): iterable
    {
        if ($this->typeCategory !== TypeCategory::Object) {
            // @todo Better exception
            throw new \RuntimeException('Cannot get properties on non-object');
        }

        $class = $value::class;

        $props = $this->analyzer->analyze($class, ClassDef::class)->properties;
        foreach ($props as $p) {
            // Because objects are passed by handle, it is possible that
            // this isn't the first time we've retrieved these fields.
            // That means the enhancements may already have been done.
            // @todo This could be fatal, because who else might be using
            // these cached attributes?  Ah crap.
            $p->analyzer ??= $this->analyzer;
            $p->typeMaps ??= $this->typeMaps;
        }
        return $props;
    }

    /**
     * @return Field[]
     */
    public function properties(?array $dict = null): iterable
    {
        if ($this->typeCategory !== TypeCategory::Object) {
            // @todo Better exception
            throw new \RuntimeException('Cannot get properties on non-object');
        }

        $class = $dict
            ? $this->getTargetClass($dict)
            : $this->phpType;

        if (!$class) {
            return [];
        }

        $props = $this->analyzer->analyze($class, ClassDef::class)->properties;
        foreach ($props as $p) {
            // Because objects are passed by handle, it is possible that
            // this isn't the first time we've retrieved these fields.
            // That means the enhancements may already have been done.
            // @todo This could be fatal, because who else might be using
            // these cached attributes?  Ah crap.
            $p->analyzer ??= $this->analyzer;
            $p->typeMaps ??= $this->typeMaps;
        }
        return $props;
    }

    public function listType(mixed $value): string
    {
        // If there is a type specified, use that.
        $type = $this?->typeField?->arrayType;

        // If there is no type at all, just get the type of the data itself.
        if (!$type) {
            return \get_debug_type($value);
        }

        // Check if the target type has a type map.  If so, use that instead.
        $classDef = $this->analyzer->analyze($type, ClassDef::class);
        return $classDef->typeMap?->findClass($value[$classDef->typeMap->keyField()]) ?? $type;
    }

    protected function getTargetClass(array $dict): ?string
    {
        $map = $this->typeMap();

        if (!$map) {
            return $this->phpType;
        }

        if (! $key = ($dict[$map->keyField()] ?? null)) {
            return null;
        }

        if (!$class = $map->findClass($key)) {
            return null;
        }

        return $class;
    }

    public function typeMap(): ?TypeMap
    {
        foreach ($this->typeMaps as $class => $map) {
            if (is_a($this->phpType, $class, true)) {
                return $map;
            }
        }

        return $this?->typeMap;
    }

    /**
     * @internal
     *
     * This method is to allow the serializer to create new pseudo-Fields
     * for nested values when flattening and collecting. Do not call it directly.
     */
    public static function create(
        ?string $serializedName = null,
        string $phpType = null,
        array $extraProperties = [],
    ): static
    {
        $new = new static();
        $new->serializedName = $serializedName;
        $new->phpName ??= $serializedName;
        $new->phpType = $phpType;
        $new->typeMap = null;
        $new->typeField = null;
        $new->extraProperties = $extraProperties;
        $new->finalize();
        return $new;
    }

    protected function deriveTypeCategory(): TypeCategory
    {
        return match (true) {
            in_array($this->phpType, ['int', 'float', 'bool', 'string'], true) => TypeCategory::Scalar,
            $this->phpType === 'array' => TypeCategory::Array,
            \enum_exists($this->phpType) => $this->enumType($this->phpType),
            $this->phpType === 'object', \class_exists($this->phpType), \interface_exists($this->phpType) => TypeCategory::Object,
            default => throw UnsupportedType::create($this->phpType),
        };
    }

    protected function getNativeType(\ReflectionProperty $property): string
    {
        // @todo Support easy unions, like int|float.
        $rType = $property->getType();
        return match(true) {
            $rType instanceof \ReflectionUnionType => throw UnionTypesNotSupported::create($property),
            $rType instanceof \ReflectionIntersectionType => throw IntersectionTypesNotSupported::create($property),
            $rType instanceof \ReflectionNamedType => $rType->getName(),
            default => static::TYPE_NOT_SPECIFIED,
        };
    }

    public function deriveSerializedName(): string
    {
        return $this->rename?->convert($this->phpName)
            ?? $this->phpName;
    }

    public function exclude(): bool
    {
        return $this->exclude;
    }
}
