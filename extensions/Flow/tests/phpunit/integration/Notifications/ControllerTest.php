<?php

namespace Flow\Tests\Notifications;

use Flow\Container;
use Flow\Model\UUID;
use Flow\Notifications\Controller;
use Wikimedia\TestingAccessWrapper;

// See also NotitifiedUsersTest
/**
 * @covers \Flow\Notifications\Controller
 *
 * @group Flow
 */
class ControllerTest extends \MediaWikiTestCase {

	/**
	 * @var Controller
	 */
	protected $notificationController;

	protected function setUp() : void {
		$this->notificationController = Container::get( 'controller.notification' );

		parent::setUp();
	}

	public static function getDeepestCommonRootProvider() {
		return [
			[
				UUID::create( 't2f2m1hexpxgi9oy' ),
				'Siblings, same length',
				[
					't2f2m1hexpxgi9oy' => [
						UUID::create( 't2et4aiiijstihea' ),
						UUID::create( 't2et4aiwjnpnk5ua' ),
						UUID::create( 't2et6t35psmz1ac2' ),
						UUID::create( 't2f2m1hexpxgi9oy' ),
						UUID::create( 't2f2n66t66w0j636' ),
					],
					't2f2mruymt1k4wia' => [
						UUID::create( 't2et4aiiijstihea' ),
						UUID::create( 't2et4aiwjnpnk5ua' ),
						UUID::create( 't2et6t35psmz1ac2' ),
						UUID::create( 't2f2m1hexpxgi9oy' ),
						UUID::create( 't2f2nio0fzrqybo2' )
					],
				]
			],
			[
				UUID::create( 't2et6t35psmz1ac2' ),
				'First shorter',
				[
					't2f2mdjbw1dcfkia' => [
						UUID::create( 't2et4aiiijstihea' ),
						UUID::create( 't2et4aiwjnpnk5ua' ),
						UUID::create( 't2et6t35psmz1ac2' ),
						UUID::create( 't2f2mdjbw1dcfkia' ),
					],
					't2f2n66t66w0j636' => [
						UUID::create( 't2et4aiiijstihea' ),
						UUID::create( 't2et4aiwjnpnk5ua' ),
						UUID::create( 't2et6t35psmz1ac2' ),
						UUID::create( 't2f2m1hexpxgi9oy' ),
						UUID::create( 't2f2n66t66w0j636' )
					],
				],
			],
			[
				UUID::create( 't2f2re4e901we1zm' ),
				'First longer, second truncated version of first',
				[
					't2feoifdgpa2rt02' => [
						UUID::create( 't2et4aiiijstihea' ),
						UUID::create( 't2et4aiwjnpnk5ua' ),
						UUID::create( 't2et6t35psmz1ac2' ),
						UUID::create( 't2f2mdjbw1dcfkia' ),
						UUID::create( 't2f2re4e901we1zm' ),
						UUID::create( 't2feoifdgpa2rt02' ),
					],
					't2f2re4e901we1zm' => [
						UUID::create( 't2et4aiiijstihea' ),
						UUID::create( 't2et4aiwjnpnk5ua' ),
						UUID::create( 't2et6t35psmz1ac2' ),
						UUID::create( 't2f2mdjbw1dcfkia' ),
						UUID::create( 't2f2re4e901we1zm' ),
					],
				],
			],
		];
	}

	/**
	 * @dataProvider getDeepestCommonRootProvider
	 */
	public function testGetDeepestCommonRoot( $expectedDeepest, $message, $rootPaths ) {
		$actualDeepest = TestingAccessWrapper::newFromObject( $this->notificationController )
			->getDeepestCommonRoot( $rootPaths );
		$this->assertEquals( $expectedDeepest, $actualDeepest, $message );
	}

	public static function getFirstPreorderDepthFirstProvider() {
		// This isn't necessarily the actual structure returned by
		// fetchSubtreeIdentityMap.  It's the part we use
		$tree = [
			't2et6t35psmz1ac2' => [
				'children' => [
					't2f2m1hexpxgi9oy' => &$tree['t2f2m1hexpxgi9oy'],
					't2f2mdjbw1dcfkia' => &$tree['t2f2mdjbw1dcfkia'],
					't2f2mruymt1k4wia' => &$tree['t2f2mruymt1k4wia'],
				],
			],
			't2f2m1hexpxgi9oy' => [
				'children' => [
					't2f2n66t66w0j636' => &$tree['t2f2n66t66w0j636'],
					't2f2nio0fzrqybo2' => &$tree['t2f2nio0fzrqybo2'],
				],
			],
			't2f2mdjbw1dcfkia' => [
				'children' => [
					't2f2re4e901we1zm' => &$tree['t2f2re4e901we1zm'],
				],
			],
			't2f2mruymt1k4wia' => [
				'children' => [],
			],

			't2f2n66t66w0j636' => [
				'children' => [],
			],

			't2f2nio0fzrqybo2' => [
				'children' => [],
			],

			't2f2re4e901we1zm' => [
				'children' => [],
			],
		];

		$treeRoot = UUID::create( 't2et6t35psmz1ac2' );

		return [
			[
				$treeRoot,
				'Topmost is root',
				[
					't2et6t35psmz1ac2' => $treeRoot,
					't2f2mruymt1k4wia' => UUID::create( 't2f2mruymt1k4wia' ),
					't2f2nio0fzrqybo2' => UUID::create( 't2f2nio0fzrqybo2' ),
					't2f2re4e901we1zm' => UUID::create( 't2f2re4e901we1zm' ),
				],
				$treeRoot,
				$tree,
			],
			[
				UUID::create( 't2f2n66t66w0j636' ),
				'Topmost is not root',
				[
					't2f2mdjbw1dcfkia' => UUID::create( 't2f2mdjbw1dcfkia' ),
					't2f2n66t66w0j636' => UUID::create( 't2f2n66t66w0j636' ),
					't2f2re4e901we1zm' => UUID::create( 't2f2re4e901we1zm' ),
				],
				$treeRoot,
				$tree,
			],
		];
	}

	/**
	 * @dataProvider getFirstPreorderDepthFirstProvider
	 */
	public function testGetFirstPreorderDepthFirst(
		UUID $expectedFirst,
		$message,
		array $relevantPostIds,
		UUID $root,
		array $tree
	) {
		$actualFirst = TestingAccessWrapper::newFromObject( $this->notificationController )
			->getFirstPreorderDepthFirst( $relevantPostIds, $root, $tree );
		$this->assertEquals( $expectedFirst, $actualFirst, $message );
	}
}
