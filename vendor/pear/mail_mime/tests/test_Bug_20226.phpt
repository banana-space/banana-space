--TEST--
Bug #20226  Mail_mimePart::encodeHeader() and ISO-2022-JP encoding
--SKIPIF--
--FILE--
<?php
include("Mail/mimePart.php");

$subject = base64_decode("GyRCJT8lJCVIJWsbKEI=");
$mime    = new Mail_mimePart();

echo $mime->encodeHeader('subject', $subject, 'ISO-2022-JP', 'base64');
?>
--EXPECT--
=?ISO-2022-JP?B?GyRCJT8lJCVIJWsbKEI=?=
