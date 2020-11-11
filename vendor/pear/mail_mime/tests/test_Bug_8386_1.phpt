--TEST--
Bug #8386   HTML body not correctly encoded if attachments present
--SKIPIF--
--FILE--
<?php
$eol = "\n#";
include("Mail/mime.php");
$encoder = new Mail_mime(array('eol'=>$eol));
$encoder->setTXTBody('test');
$encoder->setHTMLBody('<b>test</b>');
$encoder->addAttachment('Just a test', 'application/octet-stream', 'test.txt', false);
$body = $encoder->get();
if (strpos($body, '--' . $eol . '--=')){
    print("FAILED\n");
    print("Single delimiter() between 2 parts found.\n");
    print($body);
}else{
    print("OK");
}
?>
--EXPECT--
OK
