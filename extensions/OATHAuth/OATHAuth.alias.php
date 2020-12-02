<?php
/**
 * Aliases for OATHAuth's special pages
 *
 * @file
 * @ingroup Extensions
 */

$specialPageAliases = [];

/** English (English) */
$specialPageAliases['en'] = [
	'DisableOATHForUser' => [ 'DisableOATHForUser' ],
	'OATHManage' => [ 'Manage_Two-factor_authentication', 'OATH_Manage', 'OATHManage',
		'OATH', 'Two-factor_authentication', 'OATHAuth' ],
	'VerifyOATHForUser' => [ 'VerifyOATHForUser' ],
];

/** Arabic (العربية) */
$specialPageAliases['ar'] = [
	'OATHManage' => [ 'أواث', 'أواث_أوث' ],
];

/** Egyptian Arabic (مصرى) */
$specialPageAliases['arz'] = [
	'OATHManage' => [ 'اواث', 'اواث_اوث' ],
];

/** Czech (čeština) */
$specialPageAliases['cs'] = [
	'OATHManage' => [ 'Spravovat_dvoufaktorové_ověření', 'Dvoufaktorové_ověření' ],
];

/** Spanish (Español) */
$specialPageAliases['es'] = [
	'DisableOATHForUser' => [
		'Desactivar_la_autenticación_de_dos_factores_de_un_usuario',
		'Desactivar_OATH_de_un_usuario'
	],
	'OATHManage' => [
		'Autenticación_de_dos_factores',
		'Gestionar_la_autenticación_de_dos_factores',
		'Gestionar_OATH'
	]
];

/** Galician (Galego) */
$specialPageAliases['gl'] = [
	'DisableOATHForUser' => [
		'Desactivar_a_autenticación_de_dous_factores_dun_usuario',
		'Desactivar_OATH_dun_usuario'
	],
	'OATHManage' => [
		'Autenticación_de_dous_factores',
		'Xestionar_a_autenticación_de_dous_factores',
		'Xestionar_OATH'
	]
];

/** Northern Luri (لۊری شومالی) */
$specialPageAliases['lrc'] = [
	'OATHManage' => [ 'قأسأم' ],
];

/** Polish (polski) */
$specialPageAliases['pl'] = [
	'DisableOATHForUser' => [
		'Wyłącz_OATH_użytkownika',
		'Wyłącz_weryfikację_dwuetapową_użytkownika'
	],
	'OATHManage' => [
		'Zarządzanie_weryfikacją_dwuetapową',
		'Zarządzanie_OATH',
		'Weryfikacja_dwuetapowa'
	]
];

/** Serbian Cyrillic (српски (ћирилица)) */
$specialPageAliases['sr-ec'] = [
	'DisableOATHForUser' => [ 'Онемогућавање_двофакторске_потврде_идентитета' ],
	'OATHManage' => [ 'Двофакторска_потврда_идентитета' ],
];

/** Serbian Latin (srpski (latinica)) */
$specialPageAliases['sr-el'] = [
	'DisableOATHForUser' => [ 'Onemogućavanje_dvofaktorske_potvrde_identiteta' ],
	'OATHManage' => [ 'Dvofaktorska_potvrda_identiteta' ],
];

/** Urdu (اردو) */
$specialPageAliases['ur'] = [
	'OATHManage' => [ 'حلف_نامہ' ],
];

/** Simplified Chinese (中文（简体）‎) */
$specialPageAliases['zh-hans'] = [
	'OATHManage' => [ 'OATH验证' ],
];

/** Traditional Chinese (中文（繁體）‎) */
$specialPageAliases['zh-hant'] = [
	'OATHManage' => [ 'OATH_認證' ],
];
