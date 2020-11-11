--TEST--
Bug #3488   Sleep/Wakeup EOL Consistency - Part 2
--SKIPIF--
if (!is_readable('sleep_wakeup_data')) {
    echo "skip No data. Part 1 must run first.\n";
}
--FILE--
<?php
require_once('Mail/mime.php');
$filename = 'sleep_wakeup_data';
$fp = fopen($filename, 'r');
$smm = fread($fp, filesize($filename));
fclose($fp);
@unlink($filename);

$mmData = unserialize($smm);
$mmData['mm']->get();
$x = $mmData['mm']->headers();

list($h1) = explode("\n", $mmData['header']);
list($h2) = explode("\n", $x['Content-Type']);

echo ($h1 == $h2) ? "Match" : "No Match";

?>
--EXPECT--
Match
