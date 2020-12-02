<?php
/**
 * @copyright 2013,2017 Matthew Nagi
 * @license http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause License
 */

namespace Pleo\BloomFilter;

use JsonSerializable;

/**
 * Represents a bloom filter
 */
class BloomFilter implements JsonSerializable
{
    const HASH_ALGO = 'sha1';

    /**
     * @var BitArray
     */
    private $ba;

    /**
     * @var HasherList
     */
    private $hashers;

    /**
     * @param array $data
     * @return BloomFilter
     */
    public static function initFromJson(array $data)
    {
        return new static(BitArray::initFromJson($data['bit_array']), HasherList::initFromJson($data['hashers']));
    }

    /**
     * @param int $approxSize
     * @param float $falsePosProb
     * @return BloomFilter
     */
    public static function init($approxSize, $falsePosProb)
    {
        $baSize = self::optimalBitArraySize($approxSize, $falsePosProb);
        $ba = BitArray::init($baSize);
        $hasherAmt = self::optimalHasherCount($approxSize, $baSize);

        $hashers = new HasherList(static::HASH_ALGO, $hasherAmt, $baSize);

        return new static($ba, $hashers);
    }

    /**
     * @param int $approxSetSize
     * @param float $falsePositiveProbability
     * @return int
     */
    private static function optimalBitArraySize($approxSetSize, $falsePositiveProbability)
    {
        return (int) round((($approxSetSize * log($falsePositiveProbability)) / pow(log(2), 2)) * -1);
    }

    /**
     * @param int $approxSetSize
     * @param int $bitArraySize
     * @return int
     */
    private static function optimalHasherCount($approxSetSize, $bitArraySize)
    {
        return (int) round(($bitArraySize / $approxSetSize) * log(2));
    }

    /**
     * In general, do not use the constructor directly
     *
     * @param BitArray $ba
     * @param HasherList $hashers
     */
    public function __construct(BitArray $ba, HasherList $hashers)
    {
        $this->ba = $ba;
        $this->hashers = $hashers;
    }

    /**
     * @param string $item
     * @return void
     */
    public function add($item)
    {
        $vals = $this->hashers->hash($item);
        foreach ($vals as $bitLoc) {
            $this->ba[$bitLoc] = true;
        }
    }

    /**
     * @param string $item
     * @return bool
     */
    public function exists($item)
    {
        $exists = true;
        $vals = $this->hashers->hash($item);
        foreach ($vals as $bitLoc) {
            if (!$this->ba[$bitLoc]) {
                $exists = false;
                break;
            }
        }
        return $exists;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'bit_array' => $this->ba,
            'hashers' => $this->hashers,
        ];
    }
}
