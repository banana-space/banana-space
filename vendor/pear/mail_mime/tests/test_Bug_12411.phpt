--TEST--
Bug #12411  RFC2047 encoded attachment filenames
--SKIPIF--
--FILE--
<?php
include "Mail/mime.php";
$Mime = new Mail_mime();

// some text with polish Unicode letter at the beginning
$filename = base64_decode("xZtjaWVtYQ==");
$Mime->addAttachment('testfile', "text/plain", $filename, FALSE,
    'base64', 'attachment', 'ISO-8859-1', 'pl', '',
    'quoted-printable', 'base64');

$content = $Mime->get();
$content = str_replace("\n", '', $content);

if (preg_match_all('/(name|filename)=([^\s]+)/i', $content, $matches)) {
    echo implode("\n", $matches[2]);
}

?>
--EXPECT--
"=?ISO-8859-1?Q?=C5=9Bciema?="
"=?ISO-8859-1?B?xZtjaWVtYQ==?=";
