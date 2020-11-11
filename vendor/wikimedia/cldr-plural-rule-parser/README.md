CLDRPluralRuleParser
=============

CLDRPluralRuleParser is a PHP library for parsing
[http://cldr.unicode.org/index/cldr-spec/plural-rules](plural rules) specified in the
[http://cldr.unicode.org/index](CLDR project).

This library does not contain the rules from the CLDR project, you have to get them yourself.

Here is how you use it:

<pre lang="php">
use CLDRPluralRuleParser\Evaluator;

// Example for English
$rules = ['i = 1 and v = 0'];
$forms = ['syntax error', 'syntax errors'];

for ( $i = 0; $i < 3; $i++ ) {
	$index = Evaluator::evaluate( $i, $rules );
	echo "This code has $i {$forms[$i]}\n";
}

// This code has 0 syntax errors
// This code has 1 syntax error
// This code has 2 syntax errors
</pre>

License
-------

The project is licensed under the GPL license 2 or later.
