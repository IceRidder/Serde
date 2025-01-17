<?php

declare(strict_types=1);

namespace Crell\Serde;

use Crell\AttributeUtils\Analyzer;
use Crell\AttributeUtils\ClassAnalyzer;
use Crell\AttributeUtils\MemoryCacheAnalyzer;
use Crell\Serde\Formatter\ArrayFormatter;
use Crell\Serde\Formatter\Deformatter;
use Crell\Serde\Formatter\Formatter;
use Crell\Serde\Formatter\JsonFormatter;
use Crell\Serde\Formatter\YamlFormatter;
use Crell\Serde\PropertyHandler\DateTimePropertyReader;
use Crell\Serde\PropertyHandler\DateTimeZonePropertyReader;
use Crell\Serde\PropertyHandler\DictionaryPropertyReader;
use Crell\Serde\PropertyHandler\EnumPropertyReader;
use Crell\Serde\PropertyHandler\NativeSerializePropertyReader;
use Crell\Serde\PropertyHandler\ObjectPropertyReader;
use Crell\Serde\PropertyHandler\ObjectPropertyWriter;
use Crell\Serde\PropertyHandler\PropertyReader;
use Crell\Serde\PropertyHandler\PropertyWriter;
use Crell\Serde\PropertyHandler\ScalarPropertyReader;
use Crell\Serde\PropertyHandler\SequencePropertyReader;
use Symfony\Component\Yaml\Yaml;
use function Crell\fp\afilter;
use function Crell\fp\indexBy;
use function Crell\fp\pipe;

/**
 * All-in serializer for most common cases.
 *
 * If you're not sure what to do, use this class. It comes pre-loaded
 * with all standard readers, writers, and formatters, but you can also
 * provide additional ones as needed.  In most cases you will only need
 * to provide an analyzer instance, or just accept the default.
 */
class SerdeCommon extends Serde
{
    /** @var PropertyReader[]  */
    protected readonly array $readers;

    /** @var PropertyWriter[] */
    protected readonly array $writers;

    /** @var Formatter[] */
    protected readonly array $formatters;

    /** @var Deformatter[] */
    protected readonly array $deformatters;

    protected readonly TypeMapper $typeMapper;

    /**
     * @param ClassAnalyzer $analyzer
     * @param array<int, PropertyReader|PropertyWriter> $handlers
     * @param array<int, Formatter|Deformatter> $formatters
     */
    public function __construct(
        protected readonly ClassAnalyzer $analyzer = new MemoryCacheAnalyzer(new Analyzer()),
        array $handlers = [],
        array $formatters = [],
        /** @var array<class-string, TypeMap> */
        array $typeMaps = [],
    ) {
        $this->typeMapper = new TypeMapper($typeMaps, $this->analyzer);

        // Slot any custom handlers in before the generic object reader.
        $handlers = [
            new ScalarPropertyReader(),
            new SequencePropertyReader(),
            new DictionaryPropertyReader(),
            new DateTimePropertyReader(),
            new DateTimeZonePropertyReader(),
            ...$handlers,
            new EnumPropertyReader(),
            new NativeSerializePropertyReader(),
            new ObjectPropertyReader(),
            new ObjectPropertyWriter(),
        ];

        // Add the common formatters.
        $formatters[] = new JsonFormatter();
        $formatters[] = new ArrayFormatter();
        if (class_exists(Yaml::class)) {
            $formatters[] = new YamlFormatter();
        }

        $this->readers = array_filter($handlers, static fn ($handler): bool => $handler instanceof PropertyReader);
        $this->writers = array_filter($handlers, static fn ($handler): bool => $handler instanceof PropertyWriter);

        $this->formatters = pipe(
            $formatters,
            afilter(static fn ($formatter): bool => $formatter instanceof Formatter),
            indexBy(static fn (Formatter $formatter): string => $formatter->format()),
        );

        $this->deformatters = pipe(
            $formatters,
            afilter(static fn ($formatter): bool => $formatter instanceof Deformatter),
            indexBy(static fn (Deformatter $formatter): string => $formatter->format()),
        );
    }
}
