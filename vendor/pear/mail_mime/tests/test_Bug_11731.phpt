--TEST--
Bug #11731  Full stops after soft line breaks are not encoded
--SKIPIF--
--FILE--
<?php
include("Mail/mime.php");
// Second full stop will be at the start of the second line after quoted-printable
// encoding (full stop '=2E' + 72 characters + line-continuation '=' = 76)
$text     = '.123456789012345678901234567890123456789012345678901234567890123456789012.3456';
$params   = Array(
    'content_type' => 'text/plain',
    'encoding'     => 'quoted-printable',
);    
$mimePart = new Mail_mimePart($text, $params);
$encoded  =  $mimePart->encode();
echo $encoded['body'];
    
--EXPECT--
=2E123456789012345678901234567890123456789012345678901234567890123456789012=
=2E3456
