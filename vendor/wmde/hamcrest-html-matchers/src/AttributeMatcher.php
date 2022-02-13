<?php

namespace WMDE\HamcrestHtml;

use Hamcrest\Description;
use Hamcrest\Matcher;
use Hamcrest\Util;

class AttributeMatcher extends TagMatcher
{
    /**
     * @var Matcher
     */
    private $attributeNameMatcher;

    /**
     * @var Matcher|null
     */
    private $valueMatcher;

    public static function withAttribute($attributeName) {
        return new static(Util::wrapValueWithIsEqual($attributeName));
    }

    /**
     * AttributeMatcher constructor.
     * @param \Hamcrest\Matcher $attributeNameMatcher
     */
    public function __construct(Matcher $attributeNameMatcher)
    {
        parent::__construct();

        $this->attributeNameMatcher = $attributeNameMatcher;
    }

    /**
     * @param Matcher|mixed $value
     * @return AttributeMatcher
     */
    public function havingValue($value)
    {
        //TODO: Throw exception if value is set
        $result = clone $this;
        $result->valueMatcher = Util::wrapValueWithIsEqual($value);

        return $result;
    }

    public function describeTo(Description $description)
    {
        $description->appendText('with attribute ')
            ->appendDescriptionOf($this->attributeNameMatcher);
        if ($this->valueMatcher) {
            $description->appendText(' having value ')
                ->appendDescriptionOf($this->valueMatcher);
        }
    }

    /**
     * @param \DOMElement $item
     * @param Description $mismatchDescription
     *
     * @return bool
     */
    protected function matchesSafelyWithDiagnosticDescription($item, Description $mismatchDescription)
    {
        /** @var \DOMAttr $attribute */
        foreach ($item->attributes as $attribute) {
            if ($this->valueMatcher) {
                if (
                    $this->attributeNameMatcher->matches($attribute->name)
                    && $this->valueMatcher->matches($attribute->value)
                ) {
                    return true;
                }
            } else {
                if ($this->attributeNameMatcher->matches($attribute->name)) {
                    return true;
                }
            }
        }

        return false;
    }
}
