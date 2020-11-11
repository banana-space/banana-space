<?php

/**
 * Creates a ReCaptcha widget. Does not return any data; handling the data submitted by the
 * widget is callers' responsibility.
 */
class HTMLReCaptchaField extends HTMLFormField {
	/** @var string Public key parameter to be passed to ReCaptcha. */
	protected $key;

	/** @var string Theme parameter to be passed to ReCaptcha. */
	protected $theme;

	/** @var bool Use secure connection to ReCaptcha. */
	protected $secure;

	/** @var string Error returned by ReCaptcha in the previous round. */
	protected $error;

	/**
	 * Parameters:
	 * - key: (string, required) ReCaptcha public key
	 * - theme: (string, required) ReCaptcha theme
	 * - secure: (bool) Use secure connection to ReCaptcha
	 * - error: (string) ReCaptcha error from previous round
	 * @param array $params
	 */
	public function __construct( array $params ) {
		$params += [
			'secure' => true,
			'error' => null,
		];
		parent::__construct( $params );

		$this->key = $params['key'];
		$this->theme = $params['theme'];
		$this->secure = $params['secure'];
		$this->error = $params['error'];
	}

	public function getInputHTML( $value ) {
		$attribs = $this->getAttributes( [ 'tabindex' ] ) + [ 'theme' => $this->theme ];
		$js = 'var RecaptchaOptions = ' . Xml::encodeJsVar( $attribs );
		$widget = recaptcha_get_html( $this->key, $this->error, $this->secure );
		return Html::inlineScript( $js ) . $widget;
	}

	public function skipLoadData( $request ) {
		return true;
	}
}
