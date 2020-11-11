--TEST--
Bug #3513   Support of RFC2231 in header fields. (ISO-8859-1)
--SKIPIF--
--FILE--
<?php
require_once('Mail/mime.php');

$test = "Fóóbær.txt";
$Mime = new Mail_Mime();
$Mime->addAttachment('testfile',"text/plain", $test, FALSE, 'base64', 'attachment', 'ISO-8859-1');

$content = $Mime->get();
$content = str_replace("\n", '', $content);

if (preg_match('/filename([^\s]+)/i', $content, $matches)) {
    echo $matches[1];
}

--EXPECT--
*=ISO-8859-1''F%F3%F3b%E6r.txt;
