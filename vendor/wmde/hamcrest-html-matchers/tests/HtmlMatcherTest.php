<?php

namespace WMDE\HamcrestHtml\Test;

use WMDE\HamcrestHtml\HtmlMatcher;

/**
 * @covers WMDE\HamcrestHtml\HtmlMatcher
 */
class HtmlMatcherTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     * @dataProvider dataProvider_HtmlTagNamesIntroducedInHtml5
     */
    public function considersValidHtml_WhenUnknownForHtmlParserTagIsGiven($tagIntroducedInHtml5)
    {
        $html = "<$tagIntroducedInHtml5></$tagIntroducedInHtml5>";

        assertThat($html, is(HtmlMatcher::htmlPiece()));
    }

    public function dataProvider_HtmlTagNamesIntroducedInHtml5()
    {
        return [
            'article' => ['article'],
            'aside' => ['aside'],
            'bdi' => ['bdi'],
            'details' => ['details'],
            'dialog' => ['dialog'],
            'figcaption' => ['figcaption'],
            'figure' => ['figure'],
            'footer' => ['footer'],
            'header' => ['header'],
            'main' => ['main'],
            'mark' => ['mark'],
            'menuitem' => ['menuitem'],
            'meter' => ['meter'],
            'nav' => ['nav'],
            'progress' => ['progress'],
            'rp' => ['rp'],
            'rt' => ['rt'],
            'ruby' => ['ruby'],
            'section' => ['section'],
            'summary' => ['summary'],
            'time' => ['time'],
            'wbr' => ['wbr'],
            'datalist' => ['datalist'],
            'keygen' => ['keygen'],
            'output' => ['output'],
            'canvas' => ['canvas'],
            'svg' => ['svg'],
            'audio' => ['audio'],
            'embed' => ['embed'],
            'source' => ['source'],
            'track' => ['track'],
            'video' => ['video'],
        ];
    }

    /**
     * @test
     */
    public function considersValidHtml_WHtmlContainsScriptTagWithHtmlContents()
    {
        $html = "<div>
<script type='x-template'>
	<span></span>
</script>
</div>";

        assertThat($html, is(HtmlMatcher::htmlPiece()));
    }

    /**
     * @test
     */
    public function addsSpecificTextInsideTheSciptTagsInsteadOfItsContents()
    {
        $html = "<div>
<script type='x-template'>
	<span></span>
</script>
</div>";

        assertThat($html, is(htmlPiece(havingChild(
            both(withTagName('script'))
                ->andAlso(havingTextContents(HtmlMatcher::SCRIPT_BODY_REPLACEMENT))))));
    }

    /**
     * @test
     */
    public function doesNotTouchScriptTagAttributes()
    {
        $html = "<div>
<script type='x-template' attr1='value1'>
	<span></span>
</script>
</div>";

        assertThat($html, is(htmlPiece(havingChild(
            allOf(
                withTagName('script'),
                withAttribute('type')->havingValue('x-template'),
                withAttribute('attr1')->havingValue('value1')
            )))));
    }

}
