--TEST--
Bug #15320  Charset parameter in Content-Type of mail parts
--SKIPIF--
--FILE--
<?php
include "Mail/mime.php";

$Mime = new Mail_mime();
$Mime->addAttachment('testfile', "text/plain", 'file.txt', FALSE, 'base64', 'attachment', 'ISO-8859-1');

$content = $Mime->get();
//$content = str_replace("\n", '', $content);

if (preg_match('/Content-type:([^\n]+)/i', $content, $matches)) {
    echo $matches[1];
}

?>
--EXPECT--
text/plain; charset=ISO-8859-1;

