# zest.php

__zest.php__ is a fast, lightweight, extensible CSS selector engine for PHP.

Zest was designed to be very concise while still supporting CSS3/CSS4
selectors and remaining fast.

This is a port to PHP of the [zest.js](https://github.com/chjj/zest)
selector library.  Since that project hasn't been updated in a while,
bugfixes have been taken from the copy of zest included in the
[domino](https://github.com/fgnass/domino/pulls) DOM library.

Report issues on [Phabricator](https://phabricator.wikimedia.org/maniphest/task/edit/form/1/?projects=Parsoid&title=Zest:%20).

## Usage

```php
use Wikimedia\Zest\Zest;

$els = Zest::find('section! > div[title="hello" i] > :local-link /href/ h1', $doc);
```

## Install

This package is [available on Packagist](https://packagist.org/packages/wikimedia/zest-css):

```bash
$ composer require wikimedia/zest-css
```

## API


#### `Zest::find( string $selector, DOMNode $context ): array`</dt>
This is equivalent to the standard
DOM method [`ParentNode#querySelectorAll()`](https://developer.mozilla.org/en-US/docs/Web/API/ParentNode/querySelectorAll).

#### `Zest::matches( DOMNode $element, string $selector ): bool`
This is equivalent to the standard
DOM method [`Element#matches()`](https://developer.mozilla.org/en-US/docs/Web/API/Element/matches).

Since the PHP implementations of
[`DOMDocument::getElementById`](http://php.net/manual/en/domdocument.getelementbyid.php)
and
[`DOMDocument#getElementsByTagName`](http://php.net/manual/en/domdocument.getelementsbytagname.php)
have some performance and spec-compliance issues, Zest also exports useful
performant and correct versions of these:

#### `Zest::getElementsById( DOMNode $contextNode, string $id ): array`
This is equivalent to the standard DOM method
[`Document#getElementById()`](https://developer.mozilla.org/en-US/docs/Web/API/Document/getElementById)
(although you can use any context node, not just the top-level document).

#### `Zest::getElementsByTagName( DOMNode $contextNode, string $tagName ): DOMNodeList`
This is equivalent to the standard DOM method [`Element#getElementsByTagName()`](https://developer.mozilla.org/en-US/docs/Web/API/Element/getElementsByTagName).

## Extension

It is possible to add your own selectors, operators, or combinators.
These are added to an instance of `ZestInst`, so they don't affect other
instances of Zest or the static `Zest::find`/`Zest::matches` methods.
The `ZestInst` class has non-static versions of all the static methods
available on `Zest`.

### Adding a simple selector

Adding simple selectors is fairly straight forward. Only the addition of pseudo
classes and attribute operators is possible. (Adding your own "style" of
selector would require changes to the core logic.)

Here is an example of a custom `:name` selector which will match for an
element's `name` attribute: e.g. `h1:name(foo)`. Effectively an alias
for `h1[name=foo]`.

``` php
use Wikimedia\Zest\ZestInst;

$z = new ZestInst;
$z->addSelector1( ':name', function( string $param ):callable {
  return function ( DOMNode $el ) use ( $param ):bool {
    if ($el->getAttribute('name') === $param) return true;
    return false;
  };
} );

// Use it!
$z->find( 'h1:name(foo)', $document );
```

__NOTE__: if your pseudo-class does not take a parameter, use `addSelector0`.

### Adding an attribute operator

``` php
$z = new ZestInst;
// `$attr` is the attribute
// `$val` is the value to match
$z->addOperator( '!=', function( string $attr, string $val ):bool {
  return $attr !== $val;
} );

// Use it!
$z->find( 'h1[name != "foo"]', $document );
```

### Adding a combinator

Adding a combinator is a bit trickier. It may seem confusing at first because
the logic is upside-down. Zest interprets selectors from right to left.

Here is an example how a parent combinator could be implemented:

``` js
$z = new ZestInst;
$z->addCombinator( '<', function( callable $test ): callable {
  return function( DOMNode $el ) use ( $test ): ?DOMNode {
    // `$el` is the current element
    $el = $el->firstChild;
    while ($el) {
      // return the relevant element
      // if it passed the test
      if ($el->nodeType === 1 && call_user_func($test, $el)) {
        return $el;
      }
      $el = $el->nextSibling;
    }
    return null;
  };
} );

// Use it!
$z->find( 'h1 < section', $document );
```

The `$test` function tests whatever simple selectors it needs to look for, but
it isn't important what it does. The most important part is that you return
the relevant element once it's found.


## Tests

```bash
$ composer test
```

## License and Credits

The original zest codebase is
(c) Copyright 2011-2012, Christopher Jeffrey.

The port to PHP was initially done by C. Scott Ananian and is
(c) Copyright 2019 Wikimedia Foundation.

Both the original zest codebase and this port are distributed under
the MIT license; see LICENSE for more info.
