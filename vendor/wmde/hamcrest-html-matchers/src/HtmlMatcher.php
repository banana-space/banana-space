<?php

namespace WMDE\HamcrestHtml;

use Hamcrest\Description;
use Hamcrest\DiagnosingMatcher;
use Hamcrest\Matcher;

class HtmlMatcher extends DiagnosingMatcher
{
	/**
     * @link http://www.xmlsoft.org/html/libxml-xmlerror.html#xmlParserErrors
     * @link https://github.com/Chronic-Dev/libxml2/blob/683f296a905710ff285c28b8644ef3a3d8be9486/include/libxml/xmlerror.h#L257
     */
    const XML_UNKNOWN_TAG_ERROR_CODE = 801;

    const SCRIPT_BODY_REPLACEMENT = 'Contents were removed by HtmlMatcher';

    /**
     * @var Matcher
     */
    private $elementMatcher;

    /**
     * @param Matcher $elementMatcher
     *
     * @return HtmlMatcher
     */
    public static function htmlPiece(Matcher $elementMatcher = null)
    {
        return new static($elementMatcher);
    }

    private function __construct(Matcher $elementMatcher = null)
    {
        $this->elementMatcher = $elementMatcher;
    }

    public function describeTo(Description $description)
    {
        $description->appendText('valid html piece ');
        if ($this->elementMatcher) {
            $description->appendDescriptionOf($this->elementMatcher);
        }
    }

    protected function matchesWithDiagnosticDescription($html, Description $mismatchDescription)
    {
        $internalErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument();

        $html = $this->stripScriptsContents($html);

        if (!@$document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'))) {
            $mismatchDescription->appendText('there was some parsing error');
            return false;
        }

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        $result = true;
        /** @var \LibXMLError $error */
        foreach ($errors as $error) {
            if ($this->isUnknownTagError($error)) {
                continue;
            }

            $mismatchDescription->appendText('there was parsing error: ')
                ->appendText(trim($error->message))
                ->appendText(' on line ')
                ->appendText($error->line);
            $result = false;
        }

        if ($result === false) {
            return $result;
        }
        $mismatchDescription->appendText('valid html piece ');

        if ($this->elementMatcher) {
            $result = $this->elementMatcher->matches($document);
            $this->elementMatcher->describeMismatch($document, $mismatchDescription);
        }

        $mismatchDescription->appendText("\nActual html:\n")->appendText($html);

        return $result;
    }

    private function isUnknownTagError(\LibXMLError $error)
    {
        return $error->code === self::XML_UNKNOWN_TAG_ERROR_CODE;
    }

    /**
     * @param string $html
     * @return string
     */
    private function stripScriptsContents($html)
    {
        preg_match_all("#(<script.*>).*</script>#sU", $html, $scripts);
        foreach ($scripts[0] as $index => $script) {
            $openTag = $scripts[1][$index];
            $replacement = $openTag . self::SCRIPT_BODY_REPLACEMENT . '</script>';
            $html = str_replace($script, $replacement, $html);
        }
        return $html;
    }
}
