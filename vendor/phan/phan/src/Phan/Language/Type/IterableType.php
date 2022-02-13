<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Phan's representation of `iterable`
 * @see GenericIterableType for the representation of `iterable<KeyType,ValueType>`
 * @phan-pure
 */
class IterableType extends NativeType
{
    /** @phan-override */
    public const NAME = 'iterable';

    public function isIterable(): bool
    {
        return true;
    }

    public function canCastToDeclaredType(CodeBase $unused_code_base, Context $unused_context, Type $other): bool
    {
        // TODO: Check if $other is final and non-iterable
        return $other instanceof IterableType || $other instanceof CallableDeclarationType || $other->isPossiblyObject();
    }

    public function asIterable(CodeBase $_): ?Type
    {
        return $this->withIsNullable(false);
    }

    public function isPrintableScalar(): bool
    {
        return false;
    }

    public function isValidBitwiseOperand(): bool
    {
        return false;
    }

    public function isPossiblyObject(): bool
    {
        return true;  // can be Traversable, which is an object
    }

    public function asObjectType(): ?Type
    {
        return Type::traversableInstance();
    }

    public function asArrayType(): ?Type
    {
        return ArrayType::instance(false);
    }

    public function isAlwaysTruthy(): bool
    {
        return false;
    }

    public function isPossiblyTruthy(): bool
    {
        return true;
    }

    public function isPossiblyFalsey(): bool
    {
        return true;
    }

    /**
     * Returns the types of the elements
     */
    public function genericArrayElementUnionType(): UnionType
    {
        // TODO getElementUnionType in subclasses is redundant where implemented?
        return UnionType::empty();
    }

    public function isAlwaysFalsey(): bool
    {
        return false;
    }

    public function hasStaticOrSelfTypesRecursive(CodeBase $code_base): bool
    {
        $union_type = $this->iterableValueUnionType($code_base);
        if (!$union_type) {
            return false;
        }
        foreach ($union_type->getTypeSet() as $type) {
            if ($type !== $this && $type->hasStaticOrSelfTypesRecursive($code_base)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns `GenericArrayType::KEY_*` for the union type of this iterable's keys.
     * e.g. for `iterable<string, stdClass>`, returns KEY_STRING.
     *
     * Overridden in subclasses.
     */
    public function getKeyType(): int
    {
        return GenericArrayType::KEY_MIXED;
    }
}
// Trigger autoloader for subclass before make() can get called.
\class_exists(GenericIterableType::class);
\class_exists(ArrayType::class);
