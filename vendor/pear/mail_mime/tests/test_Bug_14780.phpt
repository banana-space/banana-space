--TEST--
Bug #14780  Invalid Content-Type when headers() is called before get()
--SKIPIF--
--FILE--
<?php
include("Mail/mime.php");

$mime = new Mail_mime();
$mime->setTXTBody("test");
$mime->setHTMLBody("test");

$head1 = $mime->headers();
$body = $mime->get();
$head2 = $mime->headers();

if ($head1 === $head2) {
    echo "OK";
}

?>
--EXPECT--
OK
