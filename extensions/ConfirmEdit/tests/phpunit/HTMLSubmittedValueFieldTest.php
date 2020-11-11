<?php

require_once __DIR__ . '/../../ReCaptcha/HTMLSubmittedValueField.php';

/**
 * @covers HTMLSubmittedValueField
 */
class HTMLSubmittedValueFieldTest extends PHPUnit\Framework\TestCase {
	public function testSubmit() {
		$form = new HTMLForm( [
			'foo' => [
				'class' => HTMLSubmittedValueField::class,
				'name' => 'bar',
			],
		] );
		$request = new FauxRequest( [
			'foo' => '123',
			'bar' => '456',
		], true );
		$mockClosure = $this->getMockBuilder( stdClass::class )
			->setMethods( [ '__invoke' ] )->getMock();
		$mockClosure->expects( $this->once() )->method( '__invoke' )
			->with( [ 'foo' => '456' ] )->willReturn( true );

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setRequest( $request );
		$form->setTitle( Title::newFromText( 'Title' ) );
		$form->setContext( $context );
		$form->setSubmitCallback( $mockClosure );
		$form->prepareForm();
		$form->trySubmit();
	}
}
