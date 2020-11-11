--TEST--
Bug #3513   Support of RFC2231 in header fields. (UTF-8)
--SKIPIF--
--FILE--
<?php
require_once('Mail/mime.php');

$test = "Süper gröse tolle tolle grüße.txt";
$Mime = new Mail_Mime();
$Mime->addAttachment('testfile',"text/plain", $test, FALSE, 'base64', 'attachment', 'UTF-8', 'de');

$content = $Mime->get();
$content = str_replace("\n", '', $content);

if (preg_match_all('/filename([^\s]+)/i', $content, $matches)) {
    echo implode("\n", $matches[1]);
}

--EXPECT--
*0*=UTF-8'de'S%C3%BCper%20gr%C3%B6se%20tolle%20tolle%20gr%C3%BC;
*1*=%C3%9Fe.txt;
