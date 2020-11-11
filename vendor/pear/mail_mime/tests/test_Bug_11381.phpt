--TEST--
Bug #11381  Domain name is attached to content-id, trailing greater-than sign is not removed
--SKIPIF--
--FILE--
<?php
$from='Test User <user@from.example.com>';

require_once('Mail/mime.php');

$mime=new Mail_mime();

$body='<img src="test.gif"/>';

$mime->setHTMLBody($body);
$mime->setFrom($from);
$mime->addHTMLImage('','image/gif', 'test.gif', false);
$msg=$mime->get();

$header = preg_match('|Content-ID: <[0-9a-fA-F]+@from.example.com>|', $msg);
if (!$header){
    print("FAIL:\n");
    print($msg);
}else{
    print("OK");
}
--EXPECT--
OK
