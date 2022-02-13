<?php

namespace WMDE\HamcrestHtml;

use Hamcrest\Core\AllOf;
use Hamcrest\Core\IsEqual;
use InvalidArgumentException;
use Hamcrest\Description;
use Hamcrest\Matcher;

class ComplexTagMatcher extends TagMatcher
{
    /**
     * @link http://www.xmlsoft.org/html/libxml-xmlerror.html#xmlParserErrors
     * @link https://github.com/Chronic-Dev/libxml2/blob/683f296a905710ff285c28b8644ef3a3d8be9486/include/libxml/xmlerror.h#L257
     */
    const XML_UNKNOWN_TAG_ERROR_CODE = 801;

    /**
     * @var string
     */
    private $tagHtmlOutline;

    /**
     * @var Matcher
     */
    private $matcher;

    /**
     * @param string $htmlOutline
     * @return self
     */
    public static function tagMatchingOutline($htmlOutline)
    {
        return new self($htmlOutline);
    }

    public function __construct($tagHtmlRepresentation)
    {
        parent::__construct();

        $this->tagHtmlOutline = $tagHtmlRepresentation;
        $this->matcher = $this->createMatcherFromHtml($tagHtmlRepresentation);
    }

    public function describeTo(Description $description)
    {
        $description->appendText('tag matching outline `')
            ->appendText($this->tagHtmlOutline)
            ->appendText('` ');
    }

    /**
     * @param \DOMElement $item
     * @param Description $mismatchDescription
     *
     * @return bool
     */
    protected function matchesSafelyWithDiagnosticDescription($item, Description $mismatchDescription)
    {
        $result = $this->matcher->matches($item);
        if (!$result) {
            $mismatchDescription->appendText('was `')
                ->appendText($this->elementToString($item))
                ->appendText('`');
        }
        return $result;
    }

    private function createMatcherFromHtml($htmlOutline)
    {
        $document = $this->parseHtml($htmlOutline);
        $targetTag = $this->getSingleTagFromThe($document);

        $this->assertTagDoesNotContainChildren($targetTag);

        $attributeMatchers = $this->createAttributeMatchers($htmlOutline, $targetTag);
        $classMatchers = $this->createClassMatchers($targetTag);

        return AllOf::allOf(
            new TagNameMatcher(IsEqual::equalTo($targetTag->tagName)),
            call_user_func_array([AllOf::class, 'allOf'], $attributeMatchers),
            call_user_func_array([AllOf::class, 'allOf'], $classMatchers)
        );
    }

    private function isUnknownTagError(\LibXMLError $error)
    {
        return $error->code === self::XML_UNKNOWN_TAG_ERROR_CODE;
    }

    private function isBooleanAttribute($inputHtml, $attributeName)
    {
        $quotedName = preg_quote($attributeName, '/');

        $attributeHasValueAssigned = preg_match("/\b{$quotedName}\s*=/ui", $inputHtml);
        return !$attributeHasValueAssigned;
    }

    /**
     * @param $html
     *
     * @return \DOMDocument
     * @throws \InvalidArgumentException
     */
    private function parseHtml($html)
    {
        $internalErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument();

        if (!@$document->loadHTML($html)) {
            throw new \InvalidArgumentException("There was some parsing error of `$html`");
        }

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        /** @var \LibXMLError $error */
        foreach ($errors as $error) {
            if ($this->isUnknownTagError($error)) {
                continue;
            }

            throw new \InvalidArgumentException(
                'There was parsing error: ' . trim($error->message) . ' on line ' . $error->line
            );
        }

        return $document;
    }

    /**
     * @param \DOMDocument $document
     *
     * @return \DOMElement
     * @throws \InvalidArgumentException
     */
    private function getSingleTagFromThe(\DOMDocument $document)
    {
        $directChildren = iterator_to_array($document->documentElement->childNodes);

        $body = array_shift($directChildren);
        $directChildren = iterator_to_array($body->childNodes);

        if (count($directChildren) !== 1) {
            throw new InvalidArgumentException('Expected exactly 1 tag description, got ' . count($directChildren));
        }

        return $directChildren[0];
    }

    private function assertTagDoesNotContainChildren(\DOMElement $targetTag)
    {
        if ($targetTag->childNodes->length > 0) {
            throw new InvalidArgumentException('Nested elements are not allowed');
        }
    }

    /**
     * @param string $inputHtml
     * @param $targetTag
     * @return AttributeMatcher[]
     */
    private function createAttributeMatchers($inputHtml, \DOMElement $targetTag)
    {
        $attributeMatchers = [];
        /** @var \DOMAttr $attribute */
        foreach ($targetTag->attributes as $attribute) {
            if ($attribute->name === 'class') {
                continue;
            }

            $attributeMatcher = new AttributeMatcher(IsEqual::equalTo($attribute->name));
            if (!$this->isBooleanAttribute($inputHtml, $attribute->name)) {
                $attributeMatcher = $attributeMatcher->havingValue(IsEqual::equalTo($attribute->value));
            }

            $attributeMatchers[] = $attributeMatcher;
        }
        return $attributeMatchers;
    }

    /**
     * @param \DOMElement $targetTag
     * @return ClassMatcher[]
     */
    private function createClassMatchers($targetTag)
    {
        $classMatchers = [];
        $classValue = $targetTag->getAttribute('class');
        foreach (explode(' ', $classValue) as $expectedClass) {
            if ($expectedClass === '') {
                continue;
            }
            $classMatchers[] = new ClassMatcher(IsEqual::equalTo($expectedClass));
        }
        return $classMatchers;
    }

    private function elementToString(\DOMElement $element)
    {
        $newDocument = new \DOMDocument();
        $cloned = $element->cloneNode(true);
        $newDocument->appendChild($newDocument->importNode($cloned, true));
        return trim($newDocument->saveHTML());
    }
}
