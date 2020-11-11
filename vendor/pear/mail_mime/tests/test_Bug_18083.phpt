--TEST--
Bug #18083  Separate charset for attachment's content and headers
--SKIPIF--
--FILE--
<?php
include "Mail/mime.php";
$Mime = new Mail_mime();

$Mime->addAttachment('testfile', "text/plain",
    base64_decode("xZtjaWVtYQ=="), FALSE,
    'base64', 'attachment', 'ISO-8859-1', 'pl', '',
    'quoted-printable', 'base64', '', 'UTF-8');

$content = $Mime->get();
$content = str_replace("\n", '', $content);

if (preg_match_all('/(name|filename)=([^\s]+)/i', $content, $matches)) {
    echo implode("\n", $matches[2]);
}
?>
--EXPECT--
"=?UTF-8?Q?=C5=9Bciema?="
"=?UTF-8?B?xZtjaWVtYQ==?=";
