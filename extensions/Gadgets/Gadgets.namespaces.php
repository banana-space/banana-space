<?php
$namespaceNames = [];

// For wikis without Gadgets installed.
if ( !defined( 'NS_GADGET' ) ) {
	define( 'NS_GADGET', 2300 );
	define( 'NS_GADGET_TALK', 2301 );
	define( 'NS_GADGET_DEFINITION', 2302 );
	define( 'NS_GADGET_DEFINITION_TALK', 2303 );
}

$namespaceNames['an'] = [
	NS_GADGET => 'Accesorio',
	NS_GADGET_TALK => 'Descusión_accesorio',
	NS_GADGET_DEFINITION => 'Accesorio_definición',
	NS_GADGET_DEFINITION_TALK => 'Descusión_definición_accesorio',
];

$namespaceNames['ar'] = [
	NS_GADGET => 'إضافة',
	NS_GADGET_TALK => 'نقاش_الإضافة',
	NS_GADGET_DEFINITION => 'تعريف_الإضافة',
	NS_GADGET_DEFINITION_TALK => 'نقاش_تعريف_الإضافة',
];

$namespaceNames['ast'] = [
	NS_GADGET => 'Accesoriu',
	NS_GADGET_TALK => 'Accesoriu_alderique',
	NS_GADGET_DEFINITION => 'Accesoriu_definición',
	NS_GADGET_DEFINITION_TALK => 'Accesoriu_definición_alderique',
];

$namespaceNames['atj'] = [
	NS_GADGET => 'Gadget',
	NS_GADGET_TALK => 'Ka_ici_aimihitonaniwok_gadget',
	NS_GADGET_DEFINITION => 'Tipatcitcikan_e_icinakok_gadget',
	NS_GADGET_DEFINITION_TALK => 'Ka_ici_aimihitonaniwok_tipatcitcikan_gadget_otci',
];

$namespaceNames['azb'] = [
	NS_GADGET => 'آلت',
	NS_GADGET_TALK => 'آلت_دانیشیغی',
	NS_GADGET_DEFINITION => 'آلت_آچیقلاماسی',
	NS_GADGET_DEFINITION_TALK => 'آلت_آچیقلاماسی_دانیشیغی',
];

$namespaceNames['ba'] = [
	NS_GADGET => 'Гаджет',
	NS_GADGET_TALK => 'Гаджет_буйынса_фекерләшеү',
	NS_GADGET_DEFINITION => 'Гаджет_билдәһе',
	NS_GADGET_DEFINITION_TALK => 'Гаджет_билдәһе_буйынса_фекерләшеү',
];

$namespaceNames['bgn'] = [
	NS_GADGET => 'وسیله_ئان',
	NS_GADGET_TALK => 'وسیله_ئان_ئی_گپ',
	NS_GADGET_DEFINITION => 'وسیله_ئانی_شرح',
	NS_GADGET_DEFINITION_TALK => 'وسیله_ئانی_شرح_ئی_گپ',
];

$namespaceNames['bn'] = [
	NS_GADGET => 'গ্যাজেট',
	NS_GADGET_TALK => 'গ্যাজেট_আলোচনা',
	NS_GADGET_DEFINITION => 'গ্যাজেট_সংজ্ঞা',
	NS_GADGET_DEFINITION_TALK => 'গ্যাজেট_সংজ্ঞার_আলোচনা',
];

$namespaceNames['ckb'] = [
	NS_GADGET => 'ئامراز',
	NS_GADGET_TALK => 'وتووێژی_ئامراز',
	NS_GADGET_DEFINITION => 'پێناسه‌ی_ئامراز',
	NS_GADGET_DEFINITION_TALK => 'وتووێژی_پێناسه‌ی_ئامراز',
];

$namespaceNames['cs'] = [
	NS_GADGET => 'Udělátko',
	NS_GADGET_TALK => 'Diskuse_k_udělátku',
	NS_GADGET_DEFINITION => 'Definice_udělátka',
	NS_GADGET_DEFINITION_TALK => 'Diskuse_k_definici_udělátka',
];

$namespaceNames['de'] = [
	NS_GADGET => 'Gadget',
	NS_GADGET_TALK => 'Gadget_Diskussion',
	NS_GADGET_DEFINITION => 'Gadget-Definition',
	NS_GADGET_DEFINITION_TALK => 'Gadget-Definition_Diskussion',
];

$namespaceNames['din'] = [
	NS_GADGET => 'Muluuitet',
	NS_GADGET_TALK => 'Jam_wɛ̈t_ë_muluuitet',
	NS_GADGET_DEFINITION => 'Wɛ̈tdic_ë_muluuitet',
	NS_GADGET_DEFINITION_TALK => 'Jam_wɛ̈t_ë_wɛ̈tdic_ë_muluuitet',
];

$namespaceNames['diq'] = [
	NS_GADGET => 'Halet',
	NS_GADGET_TALK => 'Halet_vaten',
	NS_GADGET_DEFINITION => 'Halet_şınasnayış',
	NS_GADGET_DEFINITION_TALK => 'Halet_şınasnayış_vaten',
];

$namespaceNames['dty'] = [
	NS_GADGET => 'ग्याजेट',
	NS_GADGET_TALK => 'ग्याजेट_कुरणि',
	NS_GADGET_DEFINITION => 'ग्याजेट_परिभाषा',
	NS_GADGET_DEFINITION_TALK => 'ग्याजेट_परिभाषा_कुरणि',
];

$namespaceNames['en'] = [
	NS_GADGET => 'Gadget',
	NS_GADGET_TALK => 'Gadget_talk',
	NS_GADGET_DEFINITION => 'Gadget_definition',
	NS_GADGET_DEFINITION_TALK => 'Gadget_definition_talk',
];

$namespaceNames['es'] = [
	NS_GADGET => 'Accesorio',
	NS_GADGET_TALK => 'Accesorio_discusión',
	NS_GADGET_DEFINITION => 'Accesorio_definición',
	NS_GADGET_DEFINITION_TALK => 'Accesorio_definición_discusión',
];

$namespaceNames['et'] = [
	NS_GADGET => 'Tööriist',
	NS_GADGET_TALK => 'Tööriista_arutelu',
	NS_GADGET_DEFINITION => 'Tööriista_määratlus',
	NS_GADGET_DEFINITION_TALK => 'Tööriista_määratluse_arutelu',
];

$namespaceNames['fa'] = [
	NS_GADGET => 'ابزار',
	NS_GADGET_TALK => 'بحث_ابزار',
	NS_GADGET_DEFINITION => 'توضیحات_ابزار',
	NS_GADGET_DEFINITION_TALK => 'بحث_توضیحات_ابزار',
];

$namespaceNames['fr'] = [
	NS_GADGET => 'Gadget',
	NS_GADGET_TALK => 'Discussion_gadget',
	NS_GADGET_DEFINITION => 'Définition_de_gadget',
	NS_GADGET_DEFINITION_TALK => 'Discussion_définition_de_gadget',
];

$namespaceNames['he'] = [
	NS_GADGET => 'גאדג\'ט',
	NS_GADGET_TALK => 'שיחת_גאדג\'ט',
	NS_GADGET_DEFINITION => 'הגדרת_גאדג\'ט',
	NS_GADGET_DEFINITION_TALK => 'שיחת_הגדרת_גאדג\'ט',
];

$namespaceNames['inh'] = [
	NS_GADGET => 'Гаджет',
	NS_GADGET_TALK => 'Гаджет_ювцар',
	NS_GADGET_DEFINITION => 'Гаджета_къоастадар',
	NS_GADGET_DEFINITION_TALK => 'Гаджета_къоастадар_дувцар',
];

$namespaceNames['is'] = [
	NS_GADGET => 'Smától',
	NS_GADGET_TALK => 'Smátólaspjall',
	NS_GADGET_DEFINITION => 'Smátóla_skilgreining',
	NS_GADGET_DEFINITION_TALK => 'Smátóla_skilgreiningarspjall',
];

$namespaceNames['it'] = [
	NS_GADGET => 'Accessorio',
	NS_GADGET_TALK => 'Discussioni_accessorio',
	NS_GADGET_DEFINITION => 'Definizione_accessorio',
	NS_GADGET_DEFINITION_TALK => 'Discussioni_definizione_accessorio',
];

$namespaceNames['ko'] = [
	NS_GADGET => '소도구',
	NS_GADGET_TALK => '소도구토론',
	NS_GADGET_DEFINITION => '소도구정의',
	NS_GADGET_DEFINITION_TALK => '소도구정의토론',
];

$namespaceNames['lrc'] = [
	NS_GADGET => 'گأجئت',
	NS_GADGET_TALK => 'چأک_چئنە_گأجئت',
	NS_GADGET_DEFINITION => 'توضییا_گأجئت',
	NS_GADGET_DEFINITION_TALK => 'چأک_چئنە_توضییا_گأجئت',
];

$namespaceNames['mwl'] = [
	NS_GADGET => 'Gadget',
	NS_GADGET_TALK => 'Cumbersa_gadget',
	NS_GADGET_DEFINITION => 'Defeniçon_gadget',
	NS_GADGET_DEFINITION_TALK => 'Cumbersa_defeniçon_gadget',
];

$namespaceNames['mzn'] = [
	NS_GADGET => 'گجت',
	NS_GADGET_TALK => 'گجت_گپ',
	NS_GADGET_DEFINITION => 'گجت_توضیحات',
	NS_GADGET_DEFINITION_TALK => 'گجت_توضیحات_گپ',
];

$namespaceNames['nl'] = [
	NS_GADGET => 'Uitbreiding',
	NS_GADGET_TALK => 'Overleg_uitbreiding',
	NS_GADGET_DEFINITION => 'Uitbreidingsdefinitie',
	NS_GADGET_DEFINITION_TALK => 'Overleg_uitbreidingsdefinitie',
];

$namespaceNames['or'] = [
	NS_GADGET => 'ଗ୍ୟାଜେଟ',
	NS_GADGET_TALK => 'ଗ୍ୟାଜେଟ_ଆଲୋଚନା',
	NS_GADGET_DEFINITION => 'ଗ୍ୟାଜେଟ_ସଂଜ୍ଞା',
	NS_GADGET_DEFINITION_TALK => 'ଗ୍ୟାଜେଟ_ସଂଜ୍ଞା_ଆଲୋଚନା',
];

$namespaceNames['pl'] = [
	NS_GADGET => 'Gadżet',
	NS_GADGET_TALK => 'Dyskusja_gadżetu',
	NS_GADGET_DEFINITION => 'Definicja_gadżetu',
	NS_GADGET_DEFINITION_TALK => 'Dyskusja_definicji_gadżetu',
];

$namespaceNames['pnb'] = [
	NS_GADGET => 'آلہ',
	NS_GADGET_TALK => 'آلہ_گل_بات',
	NS_GADGET_DEFINITION => 'آلہ_تعریف',
	NS_GADGET_DEFINITION_TALK => 'آلہ_تعریف_گل_بات',
];

$namespaceNames['sd'] = [
	NS_GADGET => 'گيجيٽ',
	NS_GADGET_TALK => 'گيجيٽ_بحث',
	NS_GADGET_DEFINITION => 'گيجيٽ_وصف',
	NS_GADGET_DEFINITION_TALK => 'گيجيٽ_وصف_بحث',
];

$namespaceNames['ur'] = [
	NS_GADGET => 'آلہ',
	NS_GADGET_TALK => 'تبادلۂ_خیال_آلہ',
	NS_GADGET_DEFINITION => 'تعریف_آلہ',
	NS_GADGET_DEFINITION_TALK => 'تبادلۂ_خیال_تعریف_آلہ',
];

$namespaceNames['vi'] = [
	NS_GADGET => 'Tiện_ích',
	NS_GADGET_TALK => 'Thảo_luận_Tiện_ích',
	NS_GADGET_DEFINITION => 'Định_nghĩa_tiện_ích',
	NS_GADGET_DEFINITION_TALK => 'Thảo_luận_Định_nghĩa_tiện_ích',
];
