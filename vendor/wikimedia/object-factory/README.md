Wikimedia ObjectFactory
=======================

Construct objects from configuration instructions.

ObjectFactory is used to convert a specification array into a live object. The
specification array must contain a `class` key with string value that
specifies the class name to instantiate or a `factory` key with a callable
(is_callable() === true). It can optionally contain an `args` key that
provides arguments to pass to the constructor/callable.

Values in the arguments collection which are Closure instances will be
expanded by invoking them with no arguments before passing the resulting value
on to the constructor/callable. This can be used to pass live objects to the
constructor/callable. This behavior can be suppressed by adding
`closure_expansion => false` to the specification.

The specification may also contain a `calls` key that describes method calls
to make on the newly created object before returning it. This pattern is often
known as "setter injection". The value of this key is expected to be an
associative array with method names as keys and argument lists as values. The
argument list will be expanded (or not) in the same way as the `args` key for
the main object.

Installation
------------

```
$ composer require wikimedia/object-factory
```

Usage
-----

```
<?php

$specs = [
	// Simple constructor based injection
	'testDB' => [
		'class' => PDO::class,
			'args' => [
				'mysql:dbname=testdb;host=127.0.0.1',
				'dbuser',
				'dbpass',
			],
	],
];

$db = ObjectFactory::getObjectFromSpec( $specs['testDB'] ):
```

License
-------
Wikimedia ObjectFactory is licensed under the GNU General Public License,
version 2 and any later version (GPL-2.0-or-later). See the
[`COPYING`](COPYING) file for more details.
