--TEST--
Bug #3488   Sleep/Wakeup EOL Consistency - Part 1
--SKIPIF--
--FILE--
<?php
require_once('Mail/mime.php');
$mm = new Mail_mime("\n");
$mm->setHTMLBody('<html></html>');
$mm->setTxtBody('Blah blah');

if (version_compare(phpversion(), "5.0.0", '<')) {
    $mmCopy = $mm;
} else {
    $mmCopy = clone($mm);
}

$mm->get();
$x = $mm->headers();

$smm = serialize(array('mm' => $mmCopy, 'header' => $x['Content-Type']));
$fp = fopen('sleep_wakeup_data', 'w');
fwrite($fp, $smm);
fclose($fp);

echo "Data written";
?>
--EXPECT--
Data written
