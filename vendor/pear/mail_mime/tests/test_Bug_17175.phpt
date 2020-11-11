--TEST--
Bug #17175  Content-Description support+ecoding
--SKIPIF--
--FILE--
<?php
require_once('Mail/mime.php');

$Mime = new Mail_Mime();
$Mime->setTXTBody('Test message.');
$Mime->addAttachment('test file contents', "text/plain",
    'test.txt', FALSE, 'base64', NULL, 'UTF-8', NULL, NULL, NULL, NULL,
    'desc');
$Mime->addAttachment('test file contents', "text/plain",
    'test2.txt', FALSE, 'base64', NULL, 'UTF-8', NULL, NULL, NULL, NULL,
    'test unicode żąśź');

$body = $Mime->getMessage();
preg_match_all('/Content-Description: (.*)/', $body, $matches);
foreach ($matches[1] as $value)
    echo $value."\n";
?>
--EXPECT--
desc
=?UTF-8?Q?test_unicode_=C5=BC=C4=85=C5=9B=C5=BA?=
