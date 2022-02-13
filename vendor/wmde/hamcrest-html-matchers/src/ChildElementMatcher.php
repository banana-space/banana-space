<?php

namespace WMDE\HamcrestHtml;

use Hamcrest\Description;
use Hamcrest\Matcher;
use Hamcrest\TypeSafeDiagnosingMatcher;

class ChildElementMatcher extends TypeSafeDiagnosingMatcher
{
    /**
     * @var Matcher|null
     */
    private $matcher;

    public static function havingChild(Matcher $elementMatcher = null) {
        return new static($elementMatcher);
    }

    public function __construct(Matcher $matcher = null)
    {
        parent::__construct(\DOMNode::class);
        $this->matcher = $matcher;
    }

    public function describeTo(Description $description)
    {
        $description->appendText('having child ');
        if ($this->matcher) {
            $description->appendDescriptionOf($this->matcher);
        }
    }

    /**
     * @param \DOMDocument|\DOMNode $item
     * @param Description $mismatchDescription
     *
     * @return bool
     */
    protected function matchesSafelyWithDiagnosticDescription($item, Description $mismatchDescription)
    {
        if ($item instanceof \DOMDocument) {
            $directChildren = iterator_to_array($item->documentElement->childNodes);

            $body = array_shift($directChildren);
            $directChildren = $body->childNodes;
        } else {
            $directChildren = $item->childNodes;
        }

        if ($directChildren->length === 0) {
            $mismatchDescription->appendText('having no children');
            return false;
        }

        if (!$this->matcher) {
            return $directChildren->length > 0;
        }

        foreach (new XmlNodeRecursiveIterator($directChildren) as $child) {
            if ($this->matcher && $this->matcher->matches($child)) {
                return true;
            }
        }

        $mismatchDescription->appendText('having no children ')->appendDescriptionOf($this->matcher);
        return false;
    }
}
