# Remex 2.2.0 (2020-04-29)
* Update dependencies.
* Fix warnings emitted by PHP 7.4.
* Bug fix in TreeBuilder\ForeignAttributes::offsetGet().
* Drop PHP 7.0/7.1 and HHVM support; require PHPUnit 8.

# Remex 2.1.0 (2019-09-16)
* Call the non-standard \DOMElement::setIdAttribute() method by default.
* Add scriptingFlag option to Tokenizer, and make it true by default.
* Attributes bug fixes.
* Added RelayTreeHandler and RelayTokenHandler for subclassing convenience.
* Normalize text nodes during tree building, to match HTML parsing spec.

# Remex 2.0.3 (2019-05-10)
* Don't decode char refs if ignoreCharRefs is set, even if they are simple.
  (This fixes a regression introduced in 2.0.2.)
* Performance improvements to character entity decoding and tokenizer
  preprocessing.

# Remex 2.0.2 (2019-03-13)
* Performance improvements to tokenization and tree building.
* Provide an option to suppress namespace for HTML elements, working around
  a performance bug in PHP's dom_reconcile_ns (T217708).

# Remex 2.0.1 (2018-10-15)
* Don't double-decode HTML entities when running on PHP (not HHVM) (T207088).

# Remex 2.0.0 (2018-08-13)
* Drop support for PHP < 7.0.
* Remove descendant nodes when we get an endTag() event (T200827).
* Improved tracing.
* Added NullTreeHandler and NullTokenHandler.

# Remex 1.0.3 (2018-02-28)
* Drop support for PHP < 5.5.

# Remex 1.0.2 (2018-01-01)
* Fix linked list manipulation in CachedScopeStack (T183379).

# Remex 1.0.1 (2017-03-14)
* Fix missing breaks in switch statements.

# Remex 1.0.0 (2017-02-24)
* Initial release.
