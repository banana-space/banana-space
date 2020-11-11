--TEST--
Bug #14779  Proper header-body separator for empty attachment
--SKIPIF--
--FILE--
<?php
include "Mail/mime.php";

$m = new Mail_mime();
$m->addAttachment('', "text/plain", 'file.txt', FALSE, 'base64', 'attachment');
$result = $m->get();

if (preg_match('/(Content.*)--=.*/s', $result, $matches)) {
    print_r($matches[1]."END");
}

?>
--EXPECT--
Content-Transfer-Encoding: base64
Content-Type: text/plain;
 name=file.txt
Content-Disposition: attachment;
 filename=file.txt


END
