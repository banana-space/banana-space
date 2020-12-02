<?php

namespace MediaWiki\Widget;

/**
 * Check matrix widget. Displays a matrix of checkboxes for given options
 *
 * @copyright 2018 MediaWiki Widgets Team and others; see AUTHORS.txt
 * @license MIT
 */
class CheckMatrixWidget extends \OOUI\Widget {
	/** @var string|null */
	protected $name;
	/** @var string|null */
	protected $id;
	/** @var array */
	protected $columns;
	/** @var array */
	protected $rows;
	/** @var array */
	protected $tooltips;
	/** @var array */
	protected $values;
	/** @var array */
	protected $forcedOn;
	/** @var array */
	protected $forcedOff;

	/**
	 * Operates similarly to MultiSelectWidget, but instead of using an array of
	 * options, uses an array of rows and an array of columns to dynamically
	 * construct a matrix of options. The tags used to identify a particular cell
	 * are of the form "columnName-rowName"
	 *
	 * @param array $config Configuration array with the following options:
	 *   - columns
	 *     - Required associative array mapping column labels (as HTML) to their tags.
	 *   - rows
	 *     - Required associative array mapping row labels (as HTML) to their tags.
	 *   - force-options-on
	 *     - Array of column-row tags to be displayed as enabled but unavailable to change.
	 *   - force-options-off
	 *     - Array of column-row tags to be displayed as disabled but unavailable to change.
	 *   - tooltips
	 *     - Optional associative array mapping row labels to tooltips (as text, will be escaped).
	 */
	public function __construct( array $config = [] ) {
		// Configuration initialization

		parent::__construct( $config );

		$this->name = $config['name'] ?? null;
		$this->id = $config['id'] ?? null;

		// Properties
		$this->rows = $config['rows'] ?? [];
		$this->columns = $config['columns'] ?? [];
		$this->tooltips = $config['tooltips'] ?? [];

		$this->values = $config['values'] ?? [];

		$this->forcedOn = $config['forcedOn'] ?? [];
		$this->forcedOff = $config['forcedOff'] ?? [];

		// Build the table
		$table = new \OOUI\Tag( 'table' );
		$table->addClasses( [ 'mw-htmlform-matrix mw-widget-checkMatrixWidget-matrix' ] );
		$thead = new \OOUI\Tag( 'thead' );
		$table->appendContent( $thead );
		$tr = new \OOUI\Tag( 'tr' );

		// Build the header
		$tr->appendContent( $this->getCellTag( "\u{00A0}" ) );
		foreach ( $this->columns as $columnLabel => $columnTag ) {
			$tr->appendContent(
				$this->getCellTag( new \OOUI\HtmlSnippet( $columnLabel ), 'th' )
			);
		}
		$thead->appendContent( $tr );

		// Build the options matrix
		$tbody = new \OOUI\Tag( 'tbody' );
		$table->appendContent( $tbody );
		foreach ( $this->rows as $rowLabel => $rowTag ) {
			$tbody->appendContent(
				$this->getTableRow( $rowLabel, $rowTag )
			);
		}

		// Initialization
		$this->addClasses( [ 'mw-widget-checkMatrixWidget' ] );
		$this->appendContent( $table );
	}

	/**
	 * Get a formatted table row for the option, with
	 * a checkbox widget.
	 *
	 * @param string $label Row label (as HTML)
	 * @param string $tag Row tag name
	 * @return \OOUI\Tag The resulting table row
	 */
	private function getTableRow( $label, $tag ) {
		$row = new \OOUI\Tag( 'tr' );
		$tooltip = $this->getTooltip( $label );
		$labelFieldConfig = $tooltip ? [ 'help' => $tooltip ] : [];
		// Build label cell
		$labelField = new \OOUI\FieldLayout(
			new \OOUI\Widget(), // Empty widget, since we don't have the checkboxes here
			[
				'label' => new \OOUI\HtmlSnippet( $label ),
				'align' => 'inline',
			] + $labelFieldConfig
		);
		$row->appendContent( $this->getCellTag( $labelField ) );

		// Build checkbox column cells
		foreach ( $this->columns as $columnTag ) {
			$thisTag = "$columnTag-$tag";

			// Construct a checkbox
			$checkbox = new \OOUI\CheckboxInputWidget( [
				'value' => $thisTag,
				'name' => $this->name ? "{$this->name}[]" : null,
				'id' => $this->id ? "{$this->id}-$thisTag" : null,
				'selected' => $this->isTagChecked( $thisTag ),
				'disabled' => $this->isTagDisabled( $thisTag ),
			] );

			$row->appendContent( $this->getCellTag( $checkbox ) );
		}
		return $row;
	}

	/**
	 * Get an individual cell tag with requested content
	 *
	 * @param mixed $content Content for the <td> cell
	 * @param string $tagElement
	 * @return \OOUI\Tag Resulting cell
	 */
	private function getCellTag( $content, $tagElement = 'td' ) {
		$cell = new \OOUI\Tag( $tagElement );
		$cell->appendContent( $content );
		return $cell;
	}

	/**
	 * Check whether the given tag's checkbox should
	 * be checked
	 *
	 * @param string $tagName Tag name
	 * @return bool Tag should be checked
	 */
	private function isTagChecked( $tagName ) {
		// If the tag is in the value list
		return in_array( $tagName, (array)$this->values, true ) ||
			// Or if the tag is forced on
			in_array( $tagName, (array)$this->forcedOn, true );
	}

	/**
	 * Check whether the given tag's checkbox should
	 * be disabled
	 *
	 * @param string $tagName Tag name
	 * @return bool Tag should be disabled
	 */
	private function isTagDisabled( $tagName ) {
		return (
			// If the entire widget is disabled
			$this->isDisabled() ||
			// If the tag is 'forced on' or 'forced off'
			in_array( $tagName, (array)$this->forcedOn, true ) ||
			in_array( $tagName, (array)$this->forcedOff, true )
		);
	}

	/**
	 * Get the tooltip help associated with this row
	 *
	 * @param string $label Label name
	 * @return string Tooltip. Null if none is available.
	 */
	private function getTooltip( $label ) {
		return $this->tooltips[ $label ] ?? null;
	}

	protected function getJavaScriptClassName() {
		return 'mw.widgets.CheckMatrixWidget';
	}

	public function getConfig( &$config ) {
		$config += [
			'name' => $this->name,
			'id' => $this->id,
			'rows' => $this->rows,
			'columns' => $this->columns,
			'tooltips' => $this->tooltips,
			'forcedOff' => $this->forcedOff,
			'forcedOn' => $this->forcedOn,
			'values' => $this->values,
		];
		return parent::getConfig( $config );
	}
}
