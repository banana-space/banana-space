--TEST--
Bug #21206  Handling quoted strings
--SKIPIF--
--FILE--
<?php
require_once('Mail/mimePart.php');
class X extends Mail_mimePart {
    public static function explodeQuotedString($delimiter, $string){
        return Mail_mimePart::explodeQuotedString($delimiter, $string);
    }
}

$tests = [
    '"a" <a@a.a>, b <b@b.b>',
    '"c\\\\" <c@c.c>, d <d@d.d>',
];
foreach ($tests as $test) {
    $addrs = X::explodeQuotedString('[\t,]', $test);
    foreach ($addrs as $addr) {
        print trim($addr) . PHP_EOL;
    }
}
?>
--EXPECT--
"a" <a@a.a>
b <b@b.b>
"c\\" <c@c.c>
d <d@d.d>
