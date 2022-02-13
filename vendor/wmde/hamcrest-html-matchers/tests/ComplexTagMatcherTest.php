<?php

namespace WMDE\HamcrestHtml\Test;

use Hamcrest\AssertionError;
use WMDE\HamcrestHtml\ComplexTagMatcher;

/**
 * @covers WMDE\HamcrestHtml\ComplexTagMatcher
 */
class ComplexTagMatcherTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function assertPasses_WhenTagInHtmlHasSameTagName() {
        $html = '<p></p>';

        assertThat($html, is(htmlPiece(havingChild(ComplexTagMatcher::tagMatchingOutline('<p/>')))));
    }

    /**
     * @test
     */
    public function assertFails_WhenTagInHtmlIsDiffersFromGivenTagName() {
        $html = '<a></a>';

        $this->setExpectedException(AssertionError::class);
        assertThat($html, is(htmlPiece(havingChild(ComplexTagMatcher::tagMatchingOutline('<p/>')))));
    }

    /**
     * @test
     */
    public function canNotCreateMatcherWithEmptyDescription() {
        $this->setExpectedException(\Exception::class);
        ComplexTagMatcher::tagMatchingOutline('');
    }

    /**
     * @test
     */
    public function canNotCreateMatcherExpectingTwoElements() {
        $this->setExpectedException(\Exception::class);
        ComplexTagMatcher::tagMatchingOutline('<p></p><b></b>');
    }

    /**
     * @test
     */
    public function canNotCreateMatcherWithChildElement() {
        $this->setExpectedException(\Exception::class);
        ComplexTagMatcher::tagMatchingOutline('<p><b></b></p>');
    }

    /**
     * @test
     */
    public function assertFails_WhenTagInHtmlDoesNotHaveExpectedAttribute() {
        $html = '<p></p>';

        $this->setExpectedException(AssertionError::class);
        assertThat($html, is(htmlPiece(havingChild(
            ComplexTagMatcher::tagMatchingOutline('<p id="some-id"/>')))));
    }

    /**
     * @test
     */
    public function assertPasses_WhenTagInHtmlHasExpectedAttribute() {
        $html = '<p id="some-id"></p>';

        assertThat($html, is(htmlPiece(havingChild(
            ComplexTagMatcher::tagMatchingOutline('<p id="some-id"/>')))));
    }

    /**
     * @test
     */
    public function assertFails_WhenTagInHtmlDoesNotHaveAllExpectedAttribute() {
        $html = '<p id="some-id"></p>';

        $this->setExpectedException(AssertionError::class);
        assertThat($html, is(htmlPiece(havingChild(
            ComplexTagMatcher::tagMatchingOutline('<p id="some-id" onclick="void();"/>')))));
    }

    /**
     * @test
     */
    public function assertPasses_WhenExpectBooleanAttributeButItIsThereWithSomeValue() {
        $html = '<input required="anything">';

        assertThat($html, is(htmlPiece(havingChild(
            ComplexTagMatcher::tagMatchingOutline('<input required/>')))));
    }

    /**
     * @test
     */
    public function assertFails_WhenExpectAttributeWithEmptyValueButItIsNotEmpty() {
        $html = '<input attr1="something">';

        $this->setExpectedException(AssertionError::class);
        assertThat($html, is(htmlPiece(havingChild(
            ComplexTagMatcher::tagMatchingOutline('<input attr1=""/>')))));
    }

    /**
     * @test
     */
    public function assertPasses_WhenGivenTagHasExpectedClass() {
        $html = '<input class="class1 class2">';

        assertThat($html, is(htmlPiece(havingChild(
            ComplexTagMatcher::tagMatchingOutline('<input class="class2"/>')))));
    }

    /**
     * @test
     */
    public function assertFails_WhenGivenTagDoesNotHaveExpectedClass() {
        $html = '<input class="class1 class2">';

        $this->setExpectedException(AssertionError::class);
        assertThat($html, is(htmlPiece(havingChild(
            ComplexTagMatcher::tagMatchingOutline('<input class="class3"/>')))));
    }

    /**
     * @test
     */
    public function toleratesExtraSpacesInClassDescription() {
        $html = '<input class="class1">';

        assertThat($html, is(htmlPiece(havingChild(
            ComplexTagMatcher::tagMatchingOutline('<input class="   class1   "/>')))));
    }
}
