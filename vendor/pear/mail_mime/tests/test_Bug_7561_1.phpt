--TEST--
Bug #7561   Mail_mimePart::quotedPrintableEncode() misbehavior with mbstring overload
--INI--
mbstring.language=Neutral
mbstring.func_overload=6
mbstring.internal_encoding=UTF-8
mbstring.http_output=UTF-8
--SKIPIF--
<?php
include "PEAR.php";
if (!extension_loaded('mbstring')){
    if (!PEAR::loadExtension('mbstring')){
        print('SKIP could not load mbstring module');
    }
}
--FILE--
<?php
include("Mail/mimePart.php");
// string is UTF-8 encoded
$input = "Micha\xC3\xABl \xC3\x89ric St\xC3\xA9phane";
$rv = Mail_mimePart::quotedPrintableEncode($input, 76, "\n");
echo $rv, "\n";
--EXPECT--
Micha=C3=ABl =C3=89ric St=C3=A9phane
