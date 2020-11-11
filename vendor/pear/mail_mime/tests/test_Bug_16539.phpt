--TEST--
Bug #16539  Headers longer than 998 characters
--SKIPIF--
--FILE--
<?php
include("Mail/mime.php");
$mime = new Mail_mime();

$headers = array(
'To' => 'jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com',
'Subject' => 'jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com',
);

echo $mime->txtHeaders($headers, true, true);
?>
--EXPECT--
MIME-Version: 1.0
To: jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com, jskibbie@schawk.com,
 jskibbie@schawk.com, jskibbie@schawk.com
Subject: jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.co
 m,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com,jskibbie@schawk.com
