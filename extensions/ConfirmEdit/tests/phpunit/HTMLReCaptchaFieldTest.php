<?php

require_once __DIR__ . '/../../ReCaptcha/HTMLReCaptchaField.php';

/**
 * @covers HTMLReCaptchaField
 */
class HTMLReCaptchaFieldTest extends PHPUnit\Framework\TestCase {
	public function testSubmit() {
		$form = new HTMLForm( [
			'foo' => [
				'class' => HTMLReCaptchaField::class,
				'key' => '123',
				'theme' => 'x',
			],
		] );
		$mockClosure = $this->getMockBuilder( stdClass::class )
			->setMethods( [ '__invoke' ] )->getMock();
		$mockClosure->expects( $this->once() )->method( '__invoke' )
			->with( [] )->willReturn( true );

		$form->setTitle( Title::newFromText( 'Title' ) );
		$form->setSubmitCallback( $mockClosure );
		$form->prepareForm();
		$form->trySubmit();
	}
}
