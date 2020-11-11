--TEST--
Bug #21255  Boundary gets added twice
--SKIPIF--
--FILE--
<?php
include "Mail/mime.php";

$mime = new Mail_mime("\r\n");
$mime->setHTMLBody('html');
$mime->setTXTBody('text');
$mime->setContentType('multipart/alternative', array('boundary' => 'something'));

$msg = $mime->getMessage();

echo substr_count($msg, 'boundary=');

?>
--EXPECT--
1
