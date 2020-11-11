--TEST--
Bug #14529  basename() workaround
--SKIPIF--
--FILE--
<?php
include "Mail/mime.php";

$Mime = new Mail_mime();
// some text with polish Unicode letter at the beginning
$filename = base64_decode("xZtjaWVtYQ==");
$Mime->addAttachment('testfile', "text/plain", $filename, FALSE, 'base64', 'attachment', 'ISO-8859-1');

$content = $Mime->get();
$content = str_replace("\n", '', $content);

if (preg_match('/filename([^\s]+)/i', $content, $matches)) {
    echo $matches[1];
}
?>
--EXPECT--
*=ISO-8859-1''%C5%9Bciema;
