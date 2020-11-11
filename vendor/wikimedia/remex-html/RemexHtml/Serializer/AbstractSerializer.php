<?php
namespace RemexHtml\Serializer;

use RemexHtml\TreeBuilder\TreeHandler;

interface AbstractSerializer extends TreeHandler {
	/**
	 * Get the serialized result of tree construction
	 *
	 * @return string
	 */
	function getResult();
}
