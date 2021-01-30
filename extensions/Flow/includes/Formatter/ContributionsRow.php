<?php

namespace Flow\Formatter;

class ContributionsRow extends FormatterRow {
	public $rev_timestamp;
	// Used when the query uses the 'revision_actor_temp' table
	public $revactor_timestamp;
}
