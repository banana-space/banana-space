<?php
use Hamcrest\Matcher;

if (!function_exists('htmlPiece')) {
    /**
     * @param mixed $elementMatcher
     *
     * @return \WMDE\HamcrestHtml\HtmlMatcher
     */
    function htmlPiece(Matcher $elementMatcher = null) {
        return \WMDE\HamcrestHtml\HtmlMatcher::htmlPiece($elementMatcher);
    }
}

if (!function_exists('havingRootElement')) {
    function havingRootElement(Matcher $matcher = null) {
        return \WMDE\HamcrestHtml\RootElementMatcher::havingRootElement($matcher);
    }
}

if (!function_exists('havingDirectChild')) {
    function havingDirectChild(Matcher $elementMatcher = null) {
        return \WMDE\HamcrestHtml\DirectChildElementMatcher::havingDirectChild($elementMatcher);
    }
}

if (!function_exists('havingChild')) {
    function havingChild(Matcher $elementMatcher = null) {
        return \WMDE\HamcrestHtml\ChildElementMatcher::havingChild($elementMatcher);
    }
}

if (!function_exists('withTagName')) {
    /**
     * @param Matcher|string $tagName
     *
     * @return \WMDE\HamcrestHtml\TagNameMatcher
     */
    function withTagName($tagName) {
        return \WMDE\HamcrestHtml\TagNameMatcher::withTagName($tagName);
    }
}

if (!function_exists('withAttribute')) {
    /**
     * @param Matcher|string $attributeName
     *
     * @return \WMDE\HamcrestHtml\AttributeMatcher
     */
    function withAttribute($attributeName) {
        return \WMDE\HamcrestHtml\AttributeMatcher::withAttribute($attributeName);
    }
}

if (!function_exists('withClass')) {
    /**
     * @param Matcher|string $class
     *
     * @return \WMDE\HamcrestHtml\ClassMatcher
     */
    function withClass($class) {
        //TODO don't allow to call with empty string

        return \WMDE\HamcrestHtml\ClassMatcher::withClass($class);
    }
}

if (!function_exists('havingTextContents')) {
    /**
     * @param Matcher|string $text
     *
     * @return \WMDE\HamcrestHtml\TextContentsMatcher
     */
    function havingTextContents($text) {
        return \WMDE\HamcrestHtml\TextContentsMatcher::havingTextContents($text);
    }
}

if (!function_exists('tagMatchingOutline')) {
    /**
     * @param string $htmlOutline
     *
     * @return \WMDE\HamcrestHtml\ComplexTagMatcher
     */
    function tagMatchingOutline($htmlOutline) {
        return \WMDE\HamcrestHtml\ComplexTagMatcher::tagMatchingOutline($htmlOutline);
    }
}
