<?php

namespace WMDE\HamcrestHtml;

use Hamcrest\Description;
use Hamcrest\Matcher;
use Hamcrest\Util;

class ClassMatcher extends TagMatcher
{
    /**
     * @var Matcher
     */
    private $classMatcher;

    public static function withClass($class) {
        return new static(Util::wrapValueWithIsEqual($class));
    }

    public function __construct(Matcher $class)
    {
        parent::__construct();
        $this->classMatcher = $class;
    }

    public function describeTo(Description $description)
    {
        $description->appendText('with class ')->appendDescriptionOf($this->classMatcher);
    }

    /**
     * @param \DOMElement $item
     * @param Description $mismatchDescription
     *
     * @return bool
     */
    protected function matchesSafelyWithDiagnosticDescription($item, Description $mismatchDescription)
    {
        $classAttribute = $item->getAttribute('class');

        $classes = preg_split('/\s+/u', $classAttribute);
        foreach ($classes as $class) {
            if ($this->classMatcher->matches($class)) {
                return true;
            }
        }

        return false;
    }
}
