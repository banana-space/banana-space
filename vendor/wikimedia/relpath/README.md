RelPath
=======

RelPath is a small PHP library for computing a relative filepath to some path,
either from the current directory or from an optional start directory.

Here is how you use it:

<pre lang="php">
$relPath = \Wikimedia\RelPath::getRelativePath( '/srv/mediawiki/resources/src/startup.js', '/srv/mediawiki' );
// Result: string(24) "resources/src/startup.js"

$fullPath = \Wikimedia\RelPath::joinPath( '/srv/mediawiki', 'resources/src/startup.js' );
// Result: string(39) "/srv/mediawiki/resources/src/startup.js"
</pre>

License
-------

MIT
