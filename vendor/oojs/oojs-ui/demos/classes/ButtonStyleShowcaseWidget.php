<?php

namespace Demo;

use OOUI;

class ButtonStyleShowcaseWidget extends OOUI\Widget {

	protected static $styles = [
		[],
		[
			'flags' => [ 'progressive' ],
		],
		[
			'flags' => [ 'destructive' ],
		],
		[
			'flags' => [ 'primary', 'progressive' ],
		],
		[
			'flags' => [ 'primary', 'destructive' ],
		],
	];
	protected static $states = [
		[
			'label' => 'Button',
		],
		[
			'label' => 'Button',
			'icon' => 'tag',
		],
		[
			'label' => 'Button',
			'icon' => 'tag',
			'indicator' => 'down',
		],
		[
			'icon' => 'tag',
			'title' => "Title text",
		],
		[
			'indicator' => 'down',
		],
		[
			'icon' => 'tag',
			'indicator' => 'down',
		],
		[
			'label' => 'Button',
			'disabled' => true,
		],
		[
			'icon' => 'tag',
			'title' => "Title text",
			'disabled' => true,
		],
		[
			'indicator' => 'down',
			'disabled' => true,
		],
	];

	public function __construct( array $config = [] ) {
		parent::__construct( $config );

		$this->addClasses( [ 'demo-buttonStyleShowcaseWidget' ] );

		foreach ( self::$styles as $style ) {
			$buttonRow = new OOUI\Tag( 'div' );
			foreach ( self::$states as $state ) {
				$buttonRow->appendContent(
					new OOUI\ButtonWidget( array_merge( $style, $state ) )
				);
			}
			$this->appendContent( $buttonRow );
		}
	}

	protected function getJavaScriptClassName() {
		return 'Demo.ButtonStyleShowcaseWidget';
	}
}
