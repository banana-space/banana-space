<?php

/**
 * Abstract entity for Echo model
 */
abstract class EchoAbstractEntity {

	/**
	 * Convert an entity's property to array
	 * @return array
	 */
	abstract public function toDbArray();

}
