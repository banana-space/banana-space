<?php
namespace MediaWiki\Skins\Vector\Tests\Integration;

use GlobalVarConfig;
use MediaWikiIntegrationTestCase;
use RequestContext;
use TemplateParser;
use Title;
use VectorTemplate;
use Wikimedia\TestingAccessWrapper;

/**
 * Class VectorTemplateTest
 * @package MediaWiki\Skins\Vector\Tests\Unit
 * @group Vector
 * @group Skins
 *
 * @coversDefaultClass \VectorTemplate
 */
class VectorTemplateTest extends MediaWikiIntegrationTestCase {

	/**
	 * @return \VectorTemplate
	 */
	private function provideVectorTemplateObject() {
		$template = new VectorTemplate(
			GlobalVarConfig::newInstance(),
			new TemplateParser(),
			true
		);
		$template->set( 'skin', new \SkinVector() );
		return $template;
	}

	/**
	 * @param string $nodeString an HTML of the node we want to verify
	 * @param string $tag Tag of the element we want to check
	 * @param string $attribute Attribute of the element we want to check
	 * @param string $search Value of the attribute we want to verify
	 * @return bool
	 */
	private function expectNodeAttribute( $nodeString, $tag, $attribute, $search ) {
		$node = new \DOMDocument();
		$node->loadHTML( $nodeString );
		$element = $node->getElementsByTagName( $tag )->item( 0 );
		if ( !$element ) {
			return false;
		}

		$values = explode( ' ', $element->getAttribute( $attribute ) );
		return in_array( $search, $values );
	}

	/**
	 * @covers ::getMenuData
	 */
	public function testMakeListItemRespectsCollapsibleOption() {
		$vectorTemplate = $this->provideVectorTemplateObject();
		$template = TestingAccessWrapper::newFromObject( $vectorTemplate );
		$listItemClass = 'my_test_class';
		$options = [ 'vector-collapsible' => true ];
		$item = [ 'class' => $listItemClass ];
		$propsCollapsible = $template->getMenuData(
			'foo',
			[
				'bar' => $item,
			],
			0,
			$options
		);
		$propsNonCollapsible = $template->getMenuData(
			'foo',
			[
				'bar' => $item,
			],
			0,
			[]
		);
		$nonCollapsible = $propsNonCollapsible['html-items'];
		$collapsible = $propsCollapsible['html-items'];

		$this->assertTrue(
			$this->expectNodeAttribute( $collapsible, 'li', 'class', 'collapsible' ),
			'The collapsible element has to have `collapsible` class'
		);
		$this->assertFalse(
			$this->expectNodeAttribute( $nonCollapsible, 'li', 'class', 'collapsible' ),
			'The non-collapsible element should not have `collapsible` class'
		);
		$this->assertTrue(
			$this->expectNodeAttribute( $nonCollapsible, 'li', 'class', $listItemClass ),
			'The non-collapsible element should preserve item class'
		);
	}

	/**
	 * @covers ::getMenuProps
	 */
	public function testGetMenuProps() {
		$title = Title::newFromText( 'SkinTemplateVector' );
		$context = RequestContext::getMain();
		$context->setTitle( $title );
		$context->setLanguage( 'fr' );
		$vectorTemplate = $this->provideVectorTemplateObject();
		// used internally by getPersonalTools
		$vectorTemplate->set( 'personal_urls', [] );
		$this->setMwGlobals( 'wgHooks', [
			'SkinTemplateNavigation' => [
				function ( &$skinTemplate, &$content_navigation ) {
					$content_navigation = [
						'actions' => [],
						'namespaces' => [],
						'variants' => [],
						'views' => [],
					];
				}
			]
		] );
		$openVectorTemplate = TestingAccessWrapper::newFromObject( $vectorTemplate );

		$props = $openVectorTemplate->getMenuProps();
		$views = $props['data-page-actions'];
		$namespaces = $props['data-namespace-tabs'];

		$this->assertSame( $views, [
			'id' => 'p-views',
			'label-id' => 'p-views-label',
			'label' => $context->msg( 'views' )->text(),
			'list-classes' => 'vector-menu-content-list',
			'html-items' => '',
			'is-dropdown' => false,
			'html-tooltip' => '',
			'html-after-portal' => '',
			'class' => 'vector-menu-empty emptyPortlet vector-menu vector-menu-tabs vectorTabs',
		] );

		$variants = $props['data-variants'];
		$actions = $props['data-page-actions-more'];
		$this->assertSame( $namespaces['class'],
			'vector-menu-empty emptyPortlet vector-menu vector-menu-tabs vectorTabs' );
		$this->assertSame( $variants['class'],
			'vector-menu-empty emptyPortlet vector-menu vector-menu-dropdown vectorMenu' );
		$this->assertSame( $actions['class'],
			'vector-menu-empty emptyPortlet vector-menu vector-menu-dropdown vectorMenu' );
		$this->assertSame( $props['data-personal-menu']['class'],
			'vector-menu-empty emptyPortlet vector-menu' );
	}

}
