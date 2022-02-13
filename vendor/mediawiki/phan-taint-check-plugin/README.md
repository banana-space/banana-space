MediaWiki Security Check Plugin
===============================

This is a plugin to [Phan] to try and detect security issues
(such as [XSS]). It keeps track of any time a user can modify
a variable, and checks to see that such variables are
escaped before being output as html or used as an sql query, etc.

It is primarily intended for scanning MediaWiki extensions,
however it supports a generic mode which should work with
any PHP project.

This plugin should be considered beta quality. Generic mode isn't
well tested yet.

Usage
-----

### System requirements
* php >= 7.2.0
* Phan 2.6.1
* Strongly suggested: php-ast >=1.0.1. While this is not enforced via composer,
using the fallback parser is way slower and more memory-draining than using php-ast.
See https://github.com/nikic/php-ast for instructions.
* Lots of memory. Scanning MediaWiki seems to take about 3 minutes
and use about 2 GB of memory. Running out of memory may be a real issue
if you try and scan something from within a VM that has limited
memory. Small projects do not require so much memory

### Composer

    $ `composer require --dev mediawiki/phan-taint-check-plugin`

* For MediaWiki core, add the following to composer.json:

```json
  "scripts": {
     "seccheck": "seccheck-mw"
  }
```

* For a MediaWiki extension/skin, add the following to composer.json:

```json
  "scripts": {
     "seccheck": "seccheck-mwext",
     "seccheck-fast": "seccheck-fast-mwext"
  }
```

* For a generic php project, add the following to composer.json:

```json
  "scripts": {
     "seccheck": "seccheck-generic"
  }
```

You can then run:

    $ `composer seccheck`

to run the security check. Note that false positives are disabled by default.
For MediaWiki extensions/skins, this assumes the extension/skin is installed in the
normal `extensions` or `skins` directory, and thus MediaWiki is in `../../`. If this is not
the case, then you need to specify the `MW_INSTALL_PATH` environment variable.

This plugin also provides variants seccheck-fast-mwext (Doesn't analyze
MediaWiki core. May miss some stuff related to hooks) and seccheck-slow-mwext
(Also analyzes vendor). seccheck-mwext will generally take about 3 minutes,
where seccheck-fast-mwext takes only about half a minute.

Additionally, if you want to do a really quick check, you can run the
seccheck-generic script from a mediawiki extension/skin which will ignore all
MediaWiki stuff, making the check much faster (but misses many issues).

If you want to do custom configuration (to say exclude some directories), follow the instructions below unser Manually.

### Manual

For MediaWiki mode, add MediaWikiSecurityCheckPlugin.php to the
list of plugins in your Phan config.php file.

For generic mode, add GenericSecurityCheckPlugin.php to the list
of plugins in your phan config.php file.

Then run phan as you normally would:

    $ php /path/to/phan/phan -p

### Docker
The docker image used by Wikimedia's continuous integration for scanning extensions and skins
is available at https://gerrit.wikimedia.org/r/plugins/gitiles/integration/config/+/master/dockerfiles/mediawiki-phan-seccheck .

For more information about Wikimedia's use of this plugin see
https://www.mediawiki.org/wiki/Phan-taint-check-plugin

Plugin output
-------------

The plugin will output various issue types depending on what it
detects. The issue types it outputs are:

* SecurityCheckMulti - For when there are multiple types of security issues
  involved
* SecurityCheck-XSS
* SecurityCheck-SQLInjection
* SecurityCheck-ShellInjection
* SecurityCheck-PHPSerializeInjection - For when someone does `unserialize( $_GET['d'] );`
  This issue type seems to have a high false positive rate currently.
* SecurityCheck-CUSTOM1 - To allow people to have custom taint types
* SecurityCheck-CUSTOM2 - ditto
* SecurityCheck-DoubleEscaped - Detecting that HTML is being double escaped
* SecurityCheck-OTHER - At the moment, this corresponds to things that don't
  have an escaping function to make input safe. e.g. `eval( $_GET['foo'] ); require $_GET['bar'];`
* SecurityCheck-LikelyFalsePositive - A potential issue, but probably not.
  Mostly happens when the plugin gets confused.

The severity field is usually marked as `Issue::SEVERITY_NORMAL (5)`. False
positives get `Issue::SEVERITY_LOW (0)`. Issues that may result in server
compromise (as opposed to just end user compromise) such as shell or sql
injection are marked as `Issue::SEVERITY_CRITICAL (10)`.
SerializationInjection would normally be "critical" but its currently denoted
as a severity of NORMAL because the check seems to have a high false positive
rate at the moment.

You can use the `-y` command line option of Phan to filter by severity.

Limitations
-----------
If you need to suppress a false positive, you can put `@suppress NAME-OF-WARNING`
in the docblock for a function/method. Alternatively, you can use other types of
suppression, like `@phan-suppress-next-line`. See phan's readme for a complete
list.
The @param-taint and @return-taint (see "Customizing" section) are also very useful
with dealing with false positives.

There's much more than listed here, but some notable limitations/bugs:


## General limitations

* When an issue is output, the plugin tries to include details about what line
  originally caused the issue. Usually it works, but sometimes it gives
  misleading/wrong information
* Command line scripts cause XSS false positives
* The plugin won't recognize things that do custom escaping. If you have
  custom escaping methods, you must add annotations to its docblock so
  that the plugin can recognize it. See the Customizing section.
* The plugin is not capable of determining which branch is taken
  even in cases where it seems like it would be easy to determine statically.
  Thus it can fall to false positves like:
  ```
  $a = $_GET['evil'];
  if ( foo() ) {
  	$a = htmlspecialchars( $a );
  } else {
  	$a = htmlspecialchars( $a );
  }
  echo $a;
  ```
  Will give a warning, since the plugin is not smart enough to realize that
  all possible branches will result in the value being escaped. Generally false
  positives related to this can be avoided by avoiding reusing variable names,
  for values that have different amounts of escaping, which in the author's opinion
  is best practise anyways.
* Arrays are considered as a single unit. The taint of any member of an array is
  the union of the taint that all the members should have. For example:
  ```
  	$stuff = [
		$_GET['evil'],
		htmlspecialchars( $_GET['foo'] ),
	];
   	echo $stuff[1];
	echo htmlspecialchars( $stuff[0] );
  ```
  This will give both a double escaped warning and an XSS warning, as the
  plugin only tracks $stuff, not $stuff[0] vs $stuff[1].

## MediaWiki specific limitations
* With pass by reference parameters to MediaWiki hooks,
  sometimes the line number is the hook call in MediaWiki core, instead of
  the hook subscriber in the extension that caused the issue.
* The plugin can only validate the fifth ($options) and sixth ($join_cond)
  of MediaWiki's IDatabase::select() if its provided directly as an array
  literal, or directly returned as an array literal from a getQueryInfo()
  method.
* Checking of HTMLForm field specifiers only works if they are specified
  as array literals and may also misidentify things which aren't really HTMLForms

Customizing
-----------
The plugin supports being customized, by subclassing the [SecurityCheckPlugin]
class. For a complex example of doing so, see [MediaWikiSecurityCheckPlugin].

Sometimes you have methods in your codebase that alter the taint of a
variable. For example, a custom html escaping function should clear the
html taint bit. Similarly, sometimes phan-taint-check can get confused and
you want to override the taint calculated for a specific function.

You can do this by adding a taint directive in a docblock comment. For example:

```
/**
 * My function description
 *
 * @param string $html the text to be escaped
 * @param-taint $html escapes_html
 */
function escapeHtml( $html ) {
}
```

Taint directives are prefixed with either `@param-taint $parametername` or `@return-taint`. If there are multiple directives they can be separated by a comma. @param-taint is used for either marking how taint is transmited from the parameter to the methods return value, or when used with `exec_` directives, to mark places where parameters are outputted/executed. `@return-taint` is used to adjust the return value's taint regardless of the input parameters.

The type of directives include:
* `exec_$TYPE` - If a parameter is marked as exec_$TYPE then feeding that parameter a value with $TYPE taint will result in a warning triggered. Typically you would use this when a function that outputs or executes its parameter
* `escapes_$TYPE` - Used for parameters where the function escapes and then returns the parameter. So `escapes_sql` would clear the sql taint bit, but leave other taint bits alone.
* `onlysafefor_$TYPE` - For use in `@return-taint`, marks the return type as safe for a specific $TYPE but unsafe for the other types.
* `$TYPE` - if just the type is specified in a parameter, it is bitwised AND with the input variable's taint. Normally you wouldn't want to do this, but can be useful when $TYPE is `none` to specify that the parameter is not used to generate the return value. In an @return this could be used to enumerate which taint flags the return value has, which is usually only useful when specified as `tainted` to say it has all flags.
* `array_ok` - special purpose flag to say ignore tainted arguments if they are in an array.
* `allow_override` - Special purpose flag to specify that that taint annotation should be overriden by phan-taint-check if it can detect a specific taint.

The value for $TYPE can be one of htmlnoent, html, sql, shell, serialize, custom1, custom2, misc, sql_numkey, escaped, none, tainted, raw_param. Most of these are taint categories, except:
* htmlnoent - like html but disable double escaping detection that gets used with html. When escapes\_html is specified, escaped automatically gets added to @return, and exec_escaped is added to @param. Similarly onlysafefor_html is equivalent to onlysafefor_htmlnoent, escaped.
* none - Means no taint
* tainted - Means all taint categories except special categories (equivalent to SecurityCheckPlugin::YES_TAINT)
* escaped - Is used to mean the value is already escaped (To track double escaping)
* sql_numkey - Is fairly special purpose for MW. It ignores taint in arrays if they are for associative keys.
* raw_param - To be used in conjunction with other taint types. Means that the parameter's value is considered raw, hence all escaping should have already taken place, because it's not meant to happen afterwards. It behaves as if the taint of the parameter would immediately be EXEC'ed

The default value for @param-taint is `tainted` if its a string (or other dangerous type), and `none` if its something like an integer. The default value for @return-taint is `allow_override` (Which is equivalent to none unless something better can be autodetected).

Instead of annotating methods in your codebase, you can also customize
phan-taint-check to have builtin knowledge of method taints. In addition
you can extend the plugin to have fairly arbitrary behaviour.

To do this, you override the `getCustomFuncTaints()` method. This method
returns an associative array of fully qualified method names to an array
describing how the taint of the return value of the function in terms of its
arguments. The numeric keys correspond to the number of an argument, and an
'overall' key adds taint that is not present in any of the arguments.
Basically for each argument, the plugin takes the taint of the argument,
bitwise AND's it to its entry in the array, and then bitwise OR's the overall
key. If any of the keys in the array have an EXEC flags, then an issue is
immediately raised if the corresponding taint is fed the function (For
example, an output function).

For example, [htmlspecialchars] which removes html taint but leaves other taint
would look like

    'htmlspecialchars' => [
        self::YES_TAINT & ~self::HTML_TAINT,
        'overall' => self::NO_TAINT,
    ];

Environment variables
---------------------

The following environment variables affect the plugin. Normally you would not
have to adjust these.

* `SECURITY_CHECK_EXT_PATH` - Path to directory containing
  extension.json/skin.json when in MediaWiki mode.
  If not set assumes the project root directory.
* `SECCHECK_DEBUG` - File to output extra debug information (If running from
  shell, /dev/stderr is convenient)

License
-------

[GNU General Public License, version 2 or later]

[Phan]: https://github.com/phan/phan
[XSS]: https://en.wikipedia.org/wiki/Cross-site_scripting
[SecurityCheckPlugin]: src/SecurityCheckPlugin.php
[MediaWikiSecurityCheckPlugin]: MediaWikiSecurityCheckPlugin.php
[htmlspecialchars]: https://secure.php.net/htmlspecialchars
[GNU General Public License, version 2 or later]: COPYING
