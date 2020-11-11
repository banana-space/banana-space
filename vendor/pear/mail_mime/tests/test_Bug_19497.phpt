--TEST--
Bug #19497  Attachment filenames with a slash character
--SKIPIF--
--FILE--
<?php
include "Mail/mime.php";
$Mime = new Mail_mime();

$filename = "test/file.txt";
$Mime->addAttachment('testfile', "text/plain", $filename, FALSE,
    'base64', 'attachment', 'ISO-8859-1', '', '', 'quoted-printable', 'base64');

$content = $Mime->get();
$content = str_replace("\n", '', $content);

if (preg_match_all('/(name|filename)=([^\s]+)/i', $content, $matches)) {
    echo implode("\n", $matches[2]);
}
?>
--EXPECT--
"test/file.txt"
"test/file.txt";
