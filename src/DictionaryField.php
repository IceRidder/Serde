<?php

declare(strict_types=1);

namespace Crell\Serde;

use Attribute;
use function Crell\fp\amapWithKeys;
use function Crell\fp\explode;
use function Crell\fp\implode;
use function Crell\fp\pipe;
use function Crell\fp\reduce;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DictionaryField implements TypeField
{
    public function __construct(
        /** Elements in this array are objects of this type. */
        public readonly ?string $arrayType = null,
        /** Scalar values of this array should be imploded to a string and exploded on deserialization. */
        public readonly ?string $implodeOn = null,
        /** The key and value of each element will be concatenated with this character when imploding. */
        public readonly ?string $joinOn = null,
        /** When exploding a string back to an array, trim() each value. Has no effect if $implodeOn is not set. */
        public readonly bool $trim = true,
    ) {}

    public function shouldImplode(): bool
    {
        return $this->implodeOn && $this->joinOn;
    }

    public function implode(array $array): string
    {
        return pipe($array,
            amapWithKeys(fn ($v, $k) => "{$k}{$this->joinOn}{$v}"),
            implode($this->implodeOn),
        );
    }

    public function explode(string $in): array
    {
        return pipe($in,
            explode($this->implodeOn),
            reduce([], $this->explodeReduce(...)),
        );
    }

    protected function explodeReduce(array $array, string $item): array
    {
        if (!$item) {
            return $array;
        }
        if (!str_contains($item, $this->joinOn)) {
            $k = $this->trim ? trim($item) : $item;
            $array[$k] = '';
            return $array;
        }

        [$k, $v] = \explode($this->joinOn, $item);
        if ($this->trim) {
            $k = trim($k);
            $v = trim($v);
        }
        $array[$k] = $v;
        return $array;
    }

    public function acceptsType(string $type): bool
    {
        return $type === 'array';
    }
}
