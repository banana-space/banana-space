--TEST--
Multi-test for headers encoding using base64 and quoted-printable
--SKIPIF--
<?php
if (!function_exists('mb_substr') || !function_exists('mb_strlen')) {
    die "skip mbstring functions not found!";
}
?>
--FILE--
<?php
include("Mail/mime.php");
$mime = new Mail_mime();

$headers = array(
array('From', '<adresse@adresse.de>'),
array('From', 'adresse@adresse.de'),
array('From', 'Frank Do <adresse@adresse.de>'),
array('To', 'Frank Do <adresse@adresse.de>, James Clark <james@domain.com>'),
array('From', '"Frank Do" <adresse@adresse.de>'),
array('Cc', '"Frank Do" <adresse@adresse.de>, "James Clark" <james@domain.com>'),
array('Cc', ' <adresse@adresse.de>, "Kuśmiderski Jan Krzysztof Janusz Długa nazwa" <cris@domain.com>'),
array('From', '"adresse@adresse.de" <addresse@adresse>'),
array('From', 'adresse@adresse.de <addresse@adresse>'),
array('From', '"German Umlauts öäü" <adresse@adresse.de>'),
array('Subject', 'German Umlauts öäü <adresse@adresse.de>'),
array('Subject', 'Short ASCII subject'),
array('Subject', 'Long ASCII subject - multiline space separated words - too long for one line'),
array('Subject', 'Short Unicode ż subject'),
array('Subject', 'Long Unicode subject - zażółć gęślą jaźń - too long for one line'),
array('References', '<hglvja$jg7$1@nemesis.news.neostrada.pl>  <4b2e87ac$1@news.home.net.pl> <hgm5b1$3a7$1@atlantis.news.neostrada.pl>'),
array('To', '"Frank Do" <adresse@adresse.de>,, "James Clark" <james@domain.com>'),
array('To', '"Frank \\" \\\\Do" <adresse@adresse.de>'),
array('To', 'Frank " \\Do <adresse@adresse.de>'),
array('Subject', "A REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY /REALLY/ LONG test"),
array('Subject', "TEST Süper gröse tolle grüße von mir Süper gröse tolle grüße von mir Süper gröse tolle grüße von mir Süper gröse tolle grüße von mir Süper gröse tolle grüße von mir Süper gröse tolle grüße von mir Süper gröse tolle grüße von mir Süper gröse tolle grüße von mir Süper gröse tolle grüße von mir!!!?"),
array('Subject', "Update: Microsoft Windows-Tool zum Entfernen bösartiger Software 3.6"),
array('From', "test@nàme <user@domain.com>"),
array('From', "Test <\"test test\"@domain.com>"),
array('From', "\"test test\"@domain.com"),
array('From', "<\"test test\"@domain.com>"),
array('From', "Doe<test@domain.com>"),
array('From', "\"John Doe\"<test@domain.com>"),
array('Mail-Reply-To', 'adresse@adresse.de <addresse@adresse>'),
array('Mail-Reply-To', '"öäü" <adresse@adresse.de>'),
);

$i = 1;
foreach ($headers as $header) {
    $hdr = $mime->encodeHeader($header[0], $header[1], 'UTF-8', 'base64');
    printf("[%02d] %s: %s\n", $i, $header[0], $hdr);
    $hdr = $mime->encodeHeader($header[0], $header[1], 'UTF-8', 'quoted-printable');
    printf("[%02d] %s: %s\n", $i, $header[0], $hdr);
    $i++;
}
?>
--EXPECT--
[01] From: <adresse@adresse.de>
[01] From: <adresse@adresse.de>
[02] From: adresse@adresse.de
[02] From: adresse@adresse.de
[03] From: Frank Do <adresse@adresse.de>
[03] From: Frank Do <adresse@adresse.de>
[04] To: Frank Do <adresse@adresse.de>, James Clark <james@domain.com>
[04] To: Frank Do <adresse@adresse.de>, James Clark <james@domain.com>
[05] From: "Frank Do" <adresse@adresse.de>
[05] From: "Frank Do" <adresse@adresse.de>
[06] Cc: "Frank Do" <adresse@adresse.de>, "James Clark" <james@domain.com>
[06] Cc: "Frank Do" <adresse@adresse.de>, "James Clark" <james@domain.com>
[07] Cc: <adresse@adresse.de>, =?UTF-8?B?S3XFm21pZGVyc2tpIEphbiBLcnp5c3p0b2Yg?=
 =?UTF-8?B?SmFudXN6IETFgnVnYSBuYXp3YQ==?= <cris@domain.com>
[07] Cc: <adresse@adresse.de>, =?UTF-8?Q?Ku=C5=9Bmiderski_Jan_Krzysztof_Janusz?=
 =?UTF-8?Q?_D=C5=82uga_nazwa?= <cris@domain.com>
[08] From: "adresse@adresse.de" <addresse@adresse>
[08] From: "adresse@adresse.de" <addresse@adresse>
[09] From: "adresse@adresse.de" <addresse@adresse>
[09] From: "adresse@adresse.de" <addresse@adresse>
[10] From: =?UTF-8?B?R2VybWFuIFVtbGF1dHMgw7bDpMO8?= <adresse@adresse.de>
[10] From: =?UTF-8?Q?German_Umlauts_=C3=B6=C3=A4=C3=BC?= <adresse@adresse.de>
[11] Subject: =?UTF-8?B?R2VybWFuIFVtbGF1dHMgw7bDpMO8IDxhZHJlc3NlQGFkcmVzc2Uu?=
 =?UTF-8?B?ZGU+?=
[11] Subject: =?UTF-8?Q?German_Umlauts_=C3=B6=C3=A4=C3=BC_=3Cadresse=40adresse?=
 =?UTF-8?Q?=2Ede=3E?=
[12] Subject: Short ASCII subject
[12] Subject: Short ASCII subject
[13] Subject: Long ASCII subject - multiline space separated words - too long for
 one line
[13] Subject: Long ASCII subject - multiline space separated words - too long for
 one line
[14] Subject: =?UTF-8?B?U2hvcnQgVW5pY29kZSDFvCBzdWJqZWN0?=
[14] Subject: =?UTF-8?Q?Short_Unicode_=C5=BC_subject?=
[15] Subject: =?UTF-8?B?TG9uZyBVbmljb2RlIHN1YmplY3QgLSB6YcW8w7PFgsSHIGfEmcWb?=
 =?UTF-8?B?bMSFIGphxbrFhCAtIHRvbyBsb25nIGZvciBvbmUgbGluZQ==?=
[15] Subject: =?UTF-8?Q?Long_Unicode_subject_-_za=C5=BC=C3=B3=C5=82=C4=87_g?=
 =?UTF-8?Q?=C4=99=C5=9Bl=C4=85_ja=C5=BA=C5=84_-_too_long_for_one_line?=
[16] References: <hglvja$jg7$1@nemesis.news.neostrada.pl>
 <4b2e87ac$1@news.home.net.pl> <hgm5b1$3a7$1@atlantis.news.neostrada.pl>
[16] References: <hglvja$jg7$1@nemesis.news.neostrada.pl>
 <4b2e87ac$1@news.home.net.pl> <hgm5b1$3a7$1@atlantis.news.neostrada.pl>
[17] To: "Frank Do" <adresse@adresse.de>, "James Clark" <james@domain.com>
[17] To: "Frank Do" <adresse@adresse.de>, "James Clark" <james@domain.com>
[18] To: "Frank \" \\Do" <adresse@adresse.de>
[18] To: "Frank \" \\Do" <adresse@adresse.de>
[19] To: "Frank \" \\Do" <adresse@adresse.de>
[19] To: "Frank \" \\Do" <adresse@adresse.de>
[20] Subject: A REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY
 REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY
 REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY
 REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY /REALLY/ LONG test
[20] Subject: A REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY
 REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY
 REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY
 REALLY REALLY REALLY REALLY REALLY REALLY REALLY REALLY /REALLY/ LONG test
[21] Subject: =?UTF-8?B?VEVTVCBTw7xwZXIgZ3LDtnNlIHRvbGxlIGdyw7zDn2Ugdm9uIG1p?=
 =?UTF-8?B?ciBTw7xwZXIgZ3LDtnNlIHRvbGxlIGdyw7zDn2Ugdm9uIG1pciBTw7xwZXIg?=
 =?UTF-8?B?Z3LDtnNlIHRvbGxlIGdyw7zDn2Ugdm9uIG1pciBTw7xwZXIgZ3LDtnNlIHRv?=
 =?UTF-8?B?bGxlIGdyw7zDn2Ugdm9uIG1pciBTw7xwZXIgZ3LDtnNlIHRvbGxlIGdyw7w=?=
 =?UTF-8?B?w59lIHZvbiBtaXIgU8O8cGVyIGdyw7ZzZSB0b2xsZSBncsO8w59lIHZvbiBt?=
 =?UTF-8?B?aXIgU8O8cGVyIGdyw7ZzZSB0b2xsZSBncsO8w59lIHZvbiBtaXIgU8O8cGVy?=
 =?UTF-8?B?IGdyw7ZzZSB0b2xsZSBncsO8w59lIHZvbiBtaXIgU8O8cGVyIGdyw7ZzZSB0?=
 =?UTF-8?B?b2xsZSBncsO8w59lIHZvbiBtaXIhISE/?=
[21] Subject: =?UTF-8?Q?TEST_S=C3=BCper_gr=C3=B6se_tolle_gr=C3=BC=C3=9Fe_von_m?=
 =?UTF-8?Q?ir_S=C3=BCper_gr=C3=B6se_tolle_gr=C3=BC=C3=9Fe_von_mir_S=C3=BCp?=
 =?UTF-8?Q?er_gr=C3=B6se_tolle_gr=C3=BC=C3=9Fe_von_mir_S=C3=BCper_gr=C3=B6?=
 =?UTF-8?Q?se_tolle_gr=C3=BC=C3=9Fe_von_mir_S=C3=BCper_gr=C3=B6se_tolle_gr?=
 =?UTF-8?Q?=C3=BC=C3=9Fe_von_mir_S=C3=BCper_gr=C3=B6se_tolle_gr=C3=BC?=
 =?UTF-8?Q?=C3=9Fe_von_mir_S=C3=BCper_gr=C3=B6se_tolle_gr=C3=BC=C3=9Fe_von?=
 =?UTF-8?Q?_mir_S=C3=BCper_gr=C3=B6se_tolle_gr=C3=BC=C3=9Fe_von_mir_S?=
 =?UTF-8?Q?=C3=BCper_gr=C3=B6se_tolle_gr=C3=BC=C3=9Fe_von_mir!!!=3F?=
[22] Subject: =?UTF-8?B?VXBkYXRlOiBNaWNyb3NvZnQgV2luZG93cy1Ub29sIHp1bSBFbnRm?=
 =?UTF-8?B?ZXJuZW4gYsO2c2FydGlnZXIgU29mdHdhcmUgMy42?=
[22] Subject: =?UTF-8?Q?Update=3A_Microsoft_Windows-Tool_zum_Entfernen_b=C3=B6?=
 =?UTF-8?Q?sartiger_Software_3=2E6?=
[23] From: =?UTF-8?B?dGVzdEBuw6BtZQ==?= <user@domain.com>
[23] From: =?UTF-8?Q?test=40n=C3=A0me?= <user@domain.com>
[24] From: Test <"test test"@domain.com>
[24] From: Test <"test test"@domain.com>
[25] From: "test test"@domain.com
[25] From: "test test"@domain.com
[26] From: <"test test"@domain.com>
[26] From: <"test test"@domain.com>
[27] From: Doe <test@domain.com>
[27] From: Doe <test@domain.com>
[28] From: "John Doe" <test@domain.com>
[28] From: "John Doe" <test@domain.com>
[29] Mail-Reply-To: "adresse@adresse.de" <addresse@adresse>
[29] Mail-Reply-To: "adresse@adresse.de" <addresse@adresse>
[30] Mail-Reply-To: =?UTF-8?B?w7bDpMO8?= <adresse@adresse.de>
[30] Mail-Reply-To: =?UTF-8?Q?=C3=B6=C3=A4=C3=BC?= <adresse@adresse.de>
