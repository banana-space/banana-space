<?php

$cfg = require __DIR__ . '/../../vendor/mediawiki/mediawiki-phan-config/src/config.php';
// Namespace constants
$cfg['file_list'][] = 'Gadgets.namespaces.php';

return $cfg;
