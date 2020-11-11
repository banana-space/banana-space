--TEST--
Bug #20564  Unsetting headers
--SKIPIF--
--FILE--
<?php
include("Mail/mime.php");

$mime = new Mail_mime;
$mime->setSubject('test');

$headers = $mime->headers(array('Subject' => null), true);
echo array_key_exists('Subject', $headers) ? '1' : '0';
--EXPECT--
0