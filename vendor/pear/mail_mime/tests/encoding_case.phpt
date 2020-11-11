--TEST--
Bug #2364   Tabs in Mail_mimePart::quotedPrintableEncode()
--SKIPIF--
--FILE--
<?php
$test = "Here's\t\na tab\n";
require_once('Mail/mimePart.php');
print Mail_mimePart::quotedPrintableEncode($test, 7);
?>
--EXPECT--
Here's=
=09
a tab
