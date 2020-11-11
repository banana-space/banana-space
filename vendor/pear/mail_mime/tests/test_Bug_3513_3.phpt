--TEST--
Bug #3513   Support of RFC2231 in header fields. (ISO-2022-JP)
--SKIPIF--
--FILE--
<?php
mb_internal_encoding('ISO-2022-JP');
$testEncoded="GyRCRnxLXDhsGyhCLnR4dA==";
$test = base64_decode($testEncoded); // Japanese filename in ISO-2022-JP charset.
require_once('Mail/mime.php');

$Mime = new Mail_Mime();
$Mime->addAttachment('testfile',"text/plain", $test, FALSE, 'base64', 'attachment', 'iso-2022-jp', '');

$content = $Mime->get();
$content = str_replace("\n", '', $content);

if (preg_match('/filename([^\s]+)/i', $content, $matches)) {
    echo $matches[1];
}
?>
--EXPECT--
*=iso-2022-jp''%1B$BF|K%5C8l%1B%28B.txt;

