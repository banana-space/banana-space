<?php

namespace WMDE\HamcrestHtml\Test;

use Hamcrest\AssertionError;
use Hamcrest\Matcher;

class FunctionsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function havingRootElement_MultipleRootTags_ThrowsException()
    {
        //TODO Does it make sense?
        $html = '<p></p><p></p>';

        $this->setExpectedException(AssertionError::class);
        assertThat($html, is(htmlPiece(havingRootElement())));
    }

    /**
     * @test
     * @dataProvider dataProvider_ElementExists
     */
    public function matcherCanFindElement($html, $matcher) {
        assertThat($html, is(htmlPiece($matcher)));
    }

    /**
     * @test
     * @dataProvider dataProvider_ElementDoesNotExist
     */
    public function matcherCantFindElement($html, $matcher, Matcher $messageMatcher) {
        $thrownException = null;
        try {
            assertThat($html, is(htmlPiece($matcher)));
        } catch (\Exception $e) {
            $thrownException = $e;
        }

        assertThat($thrownException, is(anInstanceOf(AssertionError::class)));
        assertThat($thrownException->getMessage(), $messageMatcher);
    }

    public function dataProvider_ElementExists()
    {
        return [
            'htmlPiece - simple case' => [
                '<p></p>',
                null
            ],
            'havingRootElement - has root element' => [
                '<p></p>',
                havingRootElement()
            ],
            'withTagName - simple case' => [
                '<p><b></b></p>',
                havingRootElement(withTagName('p'))
            ],
            'havingDirectChild - without qualifier' => [
                '<p></p>',
                havingDirectChild()
            ],
            'havingDirectChild - nested structure' => [
                '<p><b></b></p>',
                havingDirectChild(havingDirectChild(withTagName('b')))
            ],
            'havingChild - target tag is not first' => [
                '<i></i><b></b>',
                havingChild(withTagName('b'))
            ],
            'havingChild - target tag is nested' => [
                '<p><b><i></i></b></p>',
                havingChild(withTagName('i'))
            ],
            'withAttribute - select element by attribute name only' => [
                '<p><input name="something"/></p>',
                havingChild(withAttribute('name'))
            ],
            'withAttribute - select element by attribute name and value' => [
                '<p><input name="something"/></p>',
                havingChild(withAttribute('name')->havingValue('something'))
            ],
            'withClass - exact match' => [
                '<p class="test-class"></p>',
                havingChild(withClass('test-class'))
            ],
            'withClass - one of the classes' => [
                '<p class="class1 class2 class3"></p>',
                havingChild(withClass('class2'))
            ],
            'withClass - classes separated with tab' => [
                "<p class='class1\tclass2\tclass3'></p>",
                havingChild(withClass('class2'))
            ],
            'havingTextContents' => [
                '<p>this is some text</p>',
                havingChild(havingTextContents(containsString('some text')))
            ],
            'havingTextContents - unicode text' => [
                '<p>какой-то текст</p>',
                havingChild(havingTextContents(containsString('какой-то текст')))
            ],
            'tagMatchingOutline' => [
                '<form><input id="ip-password" class="pretty important" name="password"></form>',
                havingChild(tagMatchingOutline('<input name="password" class="important">'))
            ],
        ];
    }

    public function dataProvider_ElementDoesNotExist()
    {
        return [
            'htmlPiece - messed up tags' => [
                '<p><a></p></a>',
                null,
                allOf(containsString('html piece'), containsString('there was parsing error'))
            ],
            'htmlPiece - prints passed html on failure' => [
                '<p><a></a></p>',
                havingRootElement(withTagName('b')),
                containsString('<p><a></a></p>')
            ],
            'withTagName - simple case' => [
                '<p><b></b></p>',
                havingRootElement(withTagName('b')),
                allOf(containsString('having root element'),
                    containsString('with tag name "b"'),
                    containsString('root element tag name was "p"')),
            ],
            'havingDirectChild - no direct child' => [
                '<p></p>',
                havingDirectChild(havingDirectChild()),
                allOf(containsString('having direct child'),
                    containsString('with direct child with no direct children')),
            ],
            'havingDirectChild - single element' => [
                '<p></p>',
                havingDirectChild(withTagName('b')),
                allOf(containsString('having direct child'),
                    containsString('with tag name "b"')),
            ],
            'havingDirectChild - nested matcher' => [
                '<p><b></b></p>',
                havingDirectChild(havingDirectChild(withTagName('p'))),
                both(containsString('having direct child having direct child with tag name "p"'))
                    ->andAlso(containsString('direct child with direct child tag name was "b"'))
            ],
            'havingChild - no children' => [
                '<p></p>',
                havingDirectChild(havingChild()),
                both(containsString('having direct child having child'))
                    ->andAlso(containsString('having no children'))
            ],
            'havingChild - target tag is absent' => [
                '<p><b><i></i></b></p>',
                havingChild(withTagName('br')),
                both(containsString('having child with tag name "br"'))
                    ->andAlso(containsString('having no children with tag name "br"'))
            ],

            'withAttribute - select element by attribute name only' => [
                '<p><input name="something"/></p>',
                havingChild(withAttribute('value')),
                both(containsString('having child with attribute "value"'))
                    ->andAlso(containsString('having no children with attribute "value"'))
            ],
            'withAttribute - select element by attribute name and value' => [
                '<p><input name="something-else"/></p>',
                havingChild(withAttribute('name')->havingValue('something')),
                both(containsString('having child with attribute "name" having value "something"'))
                    ->andAlso(containsString('having no children with attribute "name" having value "something"'))
            ],
            'withClass - no class' => [
                '<p></p>',
                havingChild(withClass('test-class')),
                both(containsString('having child with class "test-class"'))
                    ->andAlso(containsString('having no children with class "test-class"'))
            ],
            'havingTextContents' => [
                '<div><p>this is some text</p></div>',
                havingChild(havingTextContents('this is another text')),
                both(containsString('having child having text contents "this is another text"'))
                    ->andAlso(containsString('no children having text contents "this is another text"'))
            ],
            'havingTextContents - does not respect text in comments;' => [
                '<div><!--commented text--></div>',
                havingChild(havingTextContents('commented text')),
                anything()
            ],
            'tagMatchingOutline' => [
                '<input id="ip-password" class="pretty">',
                havingRootElement(tagMatchingOutline('<input name="password" class="important">')),
                both(containsString('matching outline `<input name="password" class="important">`'))
                    ->andAlso(containsString('was `<input id="ip-password" class="pretty">`'))
            ],
        ];
    }
}
