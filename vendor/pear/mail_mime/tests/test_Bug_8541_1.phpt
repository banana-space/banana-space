--TEST--
Bug #8541   mimePart.php line delimiter is \r
--SKIPIF--
--FILE--
<?php
$mime = file_get_contents('Mail/mime.php', 1);
$mimePart = file_get_contents('Mail/mimePart.php', 1);
if (strpos($mime, "\r")){
    print("\\r found in mime.php\n");
}elseif (strpos($mime, "\t")){
    print("\\t found in mime.php\n");
}elseif (strpos($mimePart, "\r")){
    print("\\r found in mimePart.php\n");
}elseif (strpos($mimePart, "\t")){
    print("\\t found in mimePart.php\n");
}
print('OK');
--EXPECT--
OK
