--TEST--
Bug #21098  Handling of empty plain text parts
--SKIPIF--
--FILE--
<?php
include "Mail/mime.php";

$mime = new Mail_mime();
$mime->setTxtBody('');
$mime->setHTMLBody('<html></html>');

$headers1 = $mime->txtHeaders();
$body     = $mime->get();
$headers2 = $mime->txtHeaders();
print strpos($headers1, 'text/html') && strpos($headers2, 'text/html') ? 'OK' : 'NOT OK';
--EXPECT--
OK
