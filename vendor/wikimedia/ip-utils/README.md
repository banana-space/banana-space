[![Latest Stable Version]](https://packagist.org/packages/wikimedia/ip-utils) [![License]](https://packagist.org/packages/wikimedia/ip-utils)

IPUtils
=====================

A series of utilities for working with IP addresses, both IPv4 and IPv6.

Additional documentation about the library can be found on
[MediaWiki.org](https://www.mediawiki.org/wiki/IPUtils).


Usage
-----

<pre lang="php">
use Wikimedia\IPUtils;

IPUtils::isIPAddress( '::1' );
IPUtils::isIPv4( '124.24.52.13' );
</pre>


Running tests
-------------

    composer install --prefer-dist
    composer test


History
-------

This library was first introduced in [MediaWiki 1.7][] ([r15572][]). It was
split out of the MediaWiki codebase and published as an independent library
during the [MediaWiki 1.34][] development cycle.


---
[MediaWiki 1.7]: https://www.mediawiki.org/wiki/MediaWiki_1.7
[r15572]: https://www.mediawiki.org/wiki/Special:Code/MediaWiki/15572
[MediaWiki 1.34]: https://www.mediawiki.org/wiki/MediaWiki_1.34
[Latest Stable Version]: https://poser.pugx.org/wikimedia/ip-utils/v/stable.svg
[License]: https://poser.pugx.org/wikimedia/ip-utils/license.svg

