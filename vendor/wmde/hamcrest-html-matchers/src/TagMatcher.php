<?php

namespace WMDE\HamcrestHtml;

use Hamcrest\TypeSafeDiagnosingMatcher;

abstract class TagMatcher extends TypeSafeDiagnosingMatcher
{
    public function __construct()
    {
        parent::__construct(self::TYPE_OBJECT, \DOMElement::class);
    }
}
