[![Latest Stable Version]](https://packagist.org/packages/wikimedia/timestamp) [![License]](https://packagist.org/packages/wikimedia/timestamp)

Convertible Timestamp for PHP
===========================

This library provides a convenient wrapper around DateTime to
create, parse, and format timestamps.

Additional documentation about the library can be found on
[MediaWiki.org](https://www.mediawiki.org/wiki/Timestamp).


Usage
-----

    $ts = new ConvertibleTimestamp( '2012-07-31T19:01:08Z' );
    $formatted = $ts->getTimestamp( TS_UNIX );

    // Shorthand
    $formatted = ConvertibleTimestamp::convert(
        TS_UNIX, '2012-07-31T19:01:08Z'
    );


Running tests
-------------

    composer install --prefer-dist
    composer test


---
[Latest Stable Version]: https://poser.pugx.org/wikimedia/timestamp/v/stable.svg
[License]: https://poser.pugx.org/wikimedia/timestamp/license.svg
