--TEST--
Bug #10816  Unwanted linebreak at the end of output
--SKIPIF--
--FILE--
<?php
$eol = "#";
include("Mail/mime.php");
$encoder = new Mail_mime(array('eol'=>$eol));
$encoder->setTXTBody('test');
$encoder->setHTMLBody('<b>test</b>');
$encoder->addAttachment('Just a test', 'application/octet-stream', 'test.txt', false);
$body = $encoder->get();
$taillength = -1 * strlen($eol) * 2;
if (substr($body, $taillength) == ($eol.$eol)){
    print("FAILED\n");
    print("Body:\n");
    print("..." . substr($body, -10) . "\n");
}else{
    print("OK\n");
}
--EXPECT--
OK

