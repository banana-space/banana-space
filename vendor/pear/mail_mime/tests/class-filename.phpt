--TEST--
Test class filename (bug #24)
--SKIPIF--
<?php
echo "skip This will be broken until Mail_Mime2";
?>
--FILE--
<?php
@include('Mail/Mime.php');
echo class_exists('Mail_Mime') ? 'Include OK' : 'Include failed';
?>
--EXPECT--
Include OK
