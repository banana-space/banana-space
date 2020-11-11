--TEST--
Bug #13032  Proper (different) boundary for nested parts
--SKIPIF--
--FILE--
<?php
include "Mail/mime.php";
$mime = new Mail_mime("\r\n");
$mime->setHTMLBody('html');
$mime->setTXTBody('text');
$mime->addAttachment('file.pdf', 'application/pdf', 'file.pdf', false, 'base64', 'inline');
$msg = $mime->getMessage();

if (preg_match_all('/boundary="([^"]+)"/', $msg, $matches)) {
    if (count($matches) == 2 && count($matches[1]) == 2 &&
        $matches[1][0] != $matches[1][1]) {
            print('OK');
    }
}
?>
--EXPECT--
OK
