--TEST--
Bug #10596  Incorrect handling of text and html '0' bodies
--SKIPIF--
--FILE--
<?php
include("Mail/mime.php");
$mime = new Mail_mime();
$mime->setTxtBody('0');
$mime->setHTMLBody('0');
$body = $mime->get();
if ($body){
    print("OK");
}else{
    print("NO BODY FOUND");
}
--EXPECT--
OK
