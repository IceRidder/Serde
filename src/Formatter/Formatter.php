<?php

declare(strict_types=1);

namespace Crell\Serde\Formatter;

use Crell\Serde\ClassDef;
use Crell\Serde\Dict;
use Crell\Serde\Field;
use Crell\Serde\Sequence;
use Crell\Serde\Serializer;

interface Formatter
{
    public function format(): string;

    public function initialField(Serializer $serializer, string $type): Field;

    public function serializeInitialize(ClassDef $classDef): mixed;

    public function serializeFinalize(mixed $runningValue, ClassDef $classDef): mixed;

    public function serializeInt(mixed $runningValue, Field $field, int $next): mixed;

    public function serializeFloat(mixed $runningValue, Field $field, float $next): mixed;

    public function serializeString(mixed $runningValue, Field $field, string $next): mixed;

    public function serializeBool(mixed $runningValue, Field $field, bool $next): mixed;

    public function serializeSequence(mixed $runningValue, Field $field, Sequence $next, Serializer $serializer): mixed;

    public function serializeDictionary(mixed $runningValue, Field $field, Dict $next, Serializer $serializer): mixed;

    public function serializeObject(mixed $runningValue, Field $field, Dict $next, Serializer $serializer): mixed;
}
