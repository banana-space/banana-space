<?php

$cfg = require __DIR__ . '/../../vendor/mediawiki/mediawiki-phan-config/src/config.php';

// Phan gets confused because DOMNode::setAttribute doesn't
// exist, it's DOMElement::setAttribute, and some functions
// are documented to return DOMNode but they actually return
// DOMElement.
$cfg['suppress_issue_types'][] = 'PhanUndeclaredMethod';

return $cfg;
