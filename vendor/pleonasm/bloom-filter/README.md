# A Bloom Filter for PHP #

[![Build Status](https://travis-ci.org/pleonasm/bloom-filter.png?branch=master)](https://travis-ci.org/pleonasm/bloom-filter)
[![Coverage Status](https://coveralls.io/repos/pleonasm/bloom-filter/badge.png)](https://coveralls.io/r/pleonasm/bloom-filter)

This is a well tested implementation of a [Bloom Filter](http://en.wikipedia.org/wiki/Bloom_filter)
for PHP. It has the following features:

1. Efficient memory use for bit array (as efficient as PHP can be anyway).
2. A way to get a raw version of the filter for transport to other systems.
3. Ability to restore said raw filter back into a usable one.
4. Auto-calculates optimal hashing and bit array size based on desired set size
   and false-positive probability.
5. Auto-generates hashing functions as needed.

## Installation ##

Install via [Composer](http://getcomposer.org) (make sure you have composer in
your path or in your project).

Put the following in your package.json:

```javascript
{
    "require": {
        "pleonasm/bloom-filter": "*"
    }
}
```

Run `composer install`.

## Usage ##

```php
<?php
use Pleo\BloomFilter\BloomFilter;

$approximateItemCount = 100000;
$falsePositiveProbability = 0.001;

$before = memory_get_usage();
$bf = BloomFilter::init($approximateItemCount, $falsePositiveProbability);
$after = memory_get_usage();
// if this were a 100,000 item array, we would be looking at about 4MB of
// space used instead of the 200k or so this uses.
echo ($after - $before) . "\n";

$bf->add('item1');
$bf->add('item2');
$bf->add('item3');

$bf->exists('item1'); // true
$bf->exists('item2'); // true
$bf->exists('item3'); // true

// The following call will return false with a 0.1% probability of
// being true as long as the amount of items in the filter are < 100000
$bf->exists('non-existing-item'); // false

$serialized = json_encode($bf); // you can store/transfer this places!
unset($bf);

// Re-hydrate the object this way.
$bf = BloomFilter::initFromJson(json_decode($serialized, true));

$bf->exists('item1'); // still true
$bf->exists('item2'); // still true
$bf->exists('item3'); // still true
$bf->exists('non-existing-item'); // still false
```

### Warnings On Serialization ###

As a note: using `json_encode()` on a bloom filter object should work across
most systems. You can run in to trouble if are moving the filter between 64
and 32 bit systems (that will outright not work) or moving between
little-endian and big-endian systems (that should work, but I haven't tested
it).

Also note that `json_encode()` will take the binary bit array and base64
encode it. So if you have a large array, it will get about 33% bigger on
serialization.

## Requirements ##

This project requires PHP 5.4 or newer.

## License ##

You can find the license for this code in [the LICENSE file](LICENSE).
