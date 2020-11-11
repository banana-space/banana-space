<?php

class CaptchaHashStore extends CaptchaStore {
	protected $data = [];

	public function store( $index, $info ) {
		$this->data[$index] = $info;
	}

	public function retrieve( $index ) {
		if ( array_key_exists( $index, $this->data ) ) {
			return $this->data[$index];
		}
		return false;
	}

	public function clear( $index ) {
		unset( $this->data[$index] );
	}

	public function cookiesNeeded() {
		return false;
	}

	public function clearAll() {
		$this->data = [];
	}
}
