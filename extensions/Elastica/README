MediaWiki extension: Elastica
----------------------------------
Provides basic Elasticsearch functionality used by other extensions.


What this provides
------------------
First and foremost this provides Elastica, a PHP library used to access
Elasticsearch, and the configuration to get it working with MediaWiki's
autoloader.

Secondly this provides ElasticsearchConnection, a base type from which
extensions can inherit that forms and caches connections in a MediaWiki friendly
way.  It contains support for indexes that have the concept of "type" and
"identifier" in the way that CirrusSearch uses these concepts.


"Type" and "identifier"
-----------------------
Index "type" in the CirrusSearch sense is used to the search corpus into
different "types" of index so those indecies can be configured with different
sharding and replication configuration, and so suggestions, which are based on
term frequency, are more accurate.  For example, CirrusSearch has a "content"
index type which stores just content articles and a "general" index type which
stores all other articles.

Index "identifier" in the CirrusSearch sense is just a string appended to the
un-identified name of the index.  The full name of the index includes the
identifier and that index is aliased to the un-identified name.  The applicate
refers to the un-identified index (the alias) so that the index can be rebuilt
with a different identifier and then the alias can be atomically swapped to the
new index.

This extension currently doesn't include support for building or maintaining
indices but CirrusSearch's updateOneSearchIndexConfig.php can be used as a
model.


Installation
------------
Fetch this plugin plugin into your extensions directory.
Make sure you have the curl php library installed (sudo apt-get install
php-curl in Debian.)
Fetch the elastica library by running "composer install".
Add this to LocalSettings.php:
 wfLoadExtension( 'Elastica' );


Licensing information
---------------------
CirrusSearch makes use of the Elastica library to connect to Elasticsearch
<http://elastica.io/>.
It is Apache licensed and you can read its LICENSE.txt for
more information.
