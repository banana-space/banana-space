--TEST--
Bug #16539  Headers longer than 998 characters
--SKIPIF--
--FILE--
<?php
include("Mail/mime.php");

$headers['From'] = 'aaaaaaaaaabbbbbbbbbbccccccccccddddddddddeeeeeeeeeeffffffffffgggggggggghhhhhhhhhh';
# over than 76 chars
$mime = new Mail_mime();
$hdrs = $mime->headers($headers);
print_r($hdrs['From']); 
?>
--EXPECT--
aaaaaaaaaabbbbbbbbbbccccccccccddddddddddeeeeeeeeeeffffffffffgggggggggghhhhhhhhhh
