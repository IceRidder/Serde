<?php

declare(strict_types=1);

namespace Crell\Serde;

use Crell\Serde\Formatter\ArrayFormatter;
use Crell\Serde\PropertyHandler\EnumOnArrayPropertyReader;
use Crell\Serde\Records\BackedSize;
use Crell\Serde\Records\LiteralEnums;
use Crell\Serde\Records\Size;

class ArrayFormatterTest extends ArrayBasedFormatterTest
{
    public function setUp(): void
    {
        parent::setUp();
        $this->formatters = [new ArrayFormatter()];
        $this->format = 'array';
        $this->emptyData = [];
    }

    protected function arrayify(mixed $serialized): array
    {
        return $serialized;
    }

    /**
     * @test
     */
    public function literal_enums(): void
    {
        $s = new SerdeCommon(handlers: [new EnumOnArrayPropertyReader()], formatters: $this->formatters);

        $serialized = [
            'size' => Size::Medium,
            'backedSize' => BackedSize::Small,
        ];

        $result = $s->deserialize($serialized, from: 'array', to: LiteralEnums::class);

        $expected = new LiteralEnums(size: Size::Medium, backedSize: BackedSize::Small);

        self::assertEquals($expected, $result);
    }
}
