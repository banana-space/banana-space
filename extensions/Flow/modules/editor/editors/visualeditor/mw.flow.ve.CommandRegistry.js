( function () {
	'use strict';

	ve.ui.commandRegistry.register(
		new ve.ui.Command(
			'flowMention',
			'window',
			'open',
			{ args: [ 'flowMention' ], supportedSelections: [ 'linear' ] }
		)
	);

	ve.ui.commandRegistry.register(
		new ve.ui.Command(
			'flowMentionAt',
			'window',
			'open',
			{ args: [ 'flowMention', { selectAt: true } ], supportedSelections: [ 'linear' ] }
		)
	);
}() );
