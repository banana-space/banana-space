--TEST--
Bug #9722   quotedPrintableEncode does not encode dot at start of line on Windows platform
--SKIPIF--
--FILE--
<?php
include("Mail/mimePart.php");
$text = "This
is a
test
...
    It is 
//really fun//
to make :(";

print_r(Mail_mimePart::quotedPrintableEncode($text, 76, "\n"));

--EXPECT--
This
is a
test
=2E..
    It is=20
//really fun//
to make :(
