<?php

namespace WMDE\HamcrestHtml;

use Hamcrest\Description;
use Hamcrest\Matcher;
use Hamcrest\Util;

class TagNameMatcher extends TagMatcher
{
    /**
     * @var Matcher
     */
    private $tagNameMatcher;

    public static function withTagName($tagName) {
        return new static(Util::wrapValueWithIsEqual($tagName));
    }

    public function __construct(Matcher $tagNameMatcher)
    {
        parent::__construct();
        $this->tagNameMatcher = $tagNameMatcher;
    }

    public function describeTo(Description $description)
    {
        $description->appendText('with tag name ')
            ->appendDescriptionOf($this->tagNameMatcher);
    }

    /**
     * @param \DOMElement $item
     * @param Description $mismatchDescription
     *
     * @return bool
     */
    protected function matchesSafelyWithDiagnosticDescription($item, Description $mismatchDescription)
    {
        $mismatchDescription->appendText('tag name ');
        $this->tagNameMatcher->describeMismatch($item->tagName, $mismatchDescription);
        return $this->tagNameMatcher->matches($item->tagName);
    }
}
