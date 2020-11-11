--TEST--
Bug #18772  Text/calendar message
--SKIPIF--
--FILE--
<?php
include "Mail/mime.php";

$mime = new Mail_mime;
$mime->setSubject('test');

// A message with text/calendar only
$mime->setCalendarBody('VCALENDAR');

echo $mime->getMessage();
echo "\n---\n";

// A message with alternative text
$mime->setTXTBody('vcalendar');
$msg = $mime->getMessage();

echo preg_replace('/=_[0-9a-z]+/', '*', $msg);
--EXPECT--
MIME-Version: 1.0
Content-Type: text/calendar; method=request; charset=UTF-8
Content-Transfer-Encoding: quoted-printable
Subject: test

VCALENDAR
---
MIME-Version: 1.0
Content-Type: multipart/alternative;
 boundary="*"
Content-Transfer-Encoding: quoted-printable
Subject: test

--*
Content-Transfer-Encoding: quoted-printable
Content-Type: text/plain; charset=ISO-8859-1

vcalendar
--*
Content-Transfer-Encoding: quoted-printable
Content-Type: text/calendar; method=request; charset=UTF-8

VCALENDAR
--*--
