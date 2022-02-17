<?php

namespace CirrusSearch\Search;

use SearchEngine;
use SearchIndexField;

class NestedIndexField extends CirrusIndexField {
	protected $typeName = "nested";

	/**
	 * Add sub-field for nested field
	 * @param string $name Field name
	 * @param SearchIndexField $subfield Field object
	 */
	public function addSubfield( $name, SearchIndexField $subfield ) {
		$this->subfields[$name] = $subfield;
	}

	public function getMapping( SearchEngine $engine ) {
		$fields = parent::getMapping( $engine );
		foreach ( $this->subfields as $name => $sub ) {
			$fields['properties'][$name] = $sub->getMapping( $engine );
		}
		return $fields;
	}
}
