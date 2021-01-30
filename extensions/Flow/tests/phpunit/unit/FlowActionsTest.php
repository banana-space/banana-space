<?php

namespace Flow\Tests;

use Flow\FlowActions;
use MediaWikiUnitTestCase;

/**
 * @covers \Flow\FlowActions
 *
 * @group Flow
 */
class FlowActionsTest extends MediaWikiUnitTestCase {

	public function testAliasedTopLevelValues() {
		$actions = new FlowActions( [
			'something' => 'aliased',
			'aliased' => [
				'real' => 'value',
			],
		] );

		$this->assertEquals( 'value', $actions->getValue( 'something', 'real' ) );
	}
}
