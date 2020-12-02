<?php
/**
 * @copyright 2013,2017 Matthew Nagi
 * @license http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause License
 */

namespace Pleo\BloomFilter;

use ArrayAccess;
use Countable;
use JsonSerializable;
use RangeException;
use UnexpectedValueException;

/**
 * Provides PHP access semantics to an arbitrary length array of bits
 */
class BitArray implements ArrayAccess, Countable, JsonSerializable
{
    const BITS_IN_BYTE = 8;

    /**
     * @var int
     */
    private $length;

    /**
     * @var string
     */
    private $data;

    /**
     * @param array $decodedJson Should be passed the return from
     *    $this->jsonSerialize() to re-create the object.
     * @return BitArray
     */
    public static function initFromJson(array $decodedJson)
    {
        return new static(base64_decode($decodedJson['arr']), $decodedJson['len']);
    }

    /**
     * @param int $length The length in bits of the bit array
     * @return BitArray
     */
    public static function init($length)
    {
        static::checkPositiveInt($length);
        $lengthInBytes = (int) ceil($length / static::BITS_IN_BYTE);
        $data = str_repeat(chr(0), $lengthInBytes);
        return new static($data, $length);
    }

    /**
     * @param mixed $val
     */
    private static function checkPositiveInt($val)
    {
        if (!is_int($val)) {
            throw new UnexpectedValueException('Value must be an integer.');
        }

        if ($val < 0) {
            throw new RangeException('Value must be greater than zero.');
        }
    }

    /**
     * @param string $data The raw bytes of the bit array
     * @param int $bitLength
     */
    public function __construct($data, $bitLength)
    {
        // need to check string here
        // need to check or truncate to $bitlength
        $this->length = $bitLength;
        $this->data = $data;
    }

    /**
     * @param int $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        if (!is_int($offset)) {
            return false;
        }

        if ($offset < 0) {
            return false;
        }

        if ($offset > $this->length - 1) {
            return false;
        }

        return true;
    }

    /**
     * @param int $offset
     * @throws UnexpectedValueException
     * @throws RangeException
     * @return bool
     */
    public function offsetGet($offset)
    {
        $this->isValidOffset($offset);

        $byte = $this->offsetToByte($offset);
        $byte = ord($this->data[$byte]);
        $bit = (bool) ($this->finalBitPos($offset) & $byte);

        return $bit;
    }

    /**
     * @param int $offset
     * @param bool $value
     * @throws UnexpectedValueException
     * @throws RangeException
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->isValidOffset($offset);
        $value = (bool) $value;

        $obyte = $this->offsetToByte($offset);
        $byte = ord($this->data[$obyte]);
        $pos = $this->finalBitPos($offset);

        if ($value) {
            $byte |= $pos;
        } else {
            $byte &= 0xFF ^ $pos;
        }

        $this->data[$obyte] = chr($byte);
    }

    /**
     * @param int $offset
     * @throws UnexpectedValueException
     * @throws RangeException
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->offsetSet($offset, false);
    }

    /**
     * Returns the length (amount of bits) of the bit array
     *
     * @return int Returns the total length in bits of the array
     */
    public function count()
    {
        return $this->length;
    }

    /**
     * @return int Returns the total byte length of the bit array
     */
    public function byteLength()
    {
        return strlen($this->data);
    }

    /**
     * @param mixed $val
     * @throws RangeException
     * @throws UnexpectedValueException
     * @return void
     */
    private function isValidOffset($val)
    {
        static::checkPositiveInt($val);

        if ($val >= $this->length) {
            throw new RangeException('Value must be less than array length - 1.');
        }
    }

    /**
     * @param int $offset
     * @return int
     */
    private function offsetToByte($offset)
    {
        return (int) floor($offset / self::BITS_IN_BYTE);
    }

    /**
     * @param int $offset
     * @return int
     */
    private function finalBitPos($offset)
    {
        return (int) pow(2, $offset % self::BITS_IN_BYTE);
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'len' => $this->length,
            'arr' => base64_encode($this->data)
        ];
    }
}
