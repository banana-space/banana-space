/*!
 * VisualEditor MediaWiki CommandRegistry registrations.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/* MW-specific over-rides of core command registrations */

ve.ui.commandRegistry.register(
	new ve.ui.Command( 'insertTable', 'table', 'create',
		{
			args: [ {
				caption: true,
				header: true,
				rows: 3,
				cols: 4,
				type: 'mwTable',
				attributes: { wikitable: true }
			} ],
			supportedSelections: [ 'linear' ]
		}
	)
);
