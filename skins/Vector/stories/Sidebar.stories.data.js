/* eslint-disable quotes */

import sidebarTemplate from '!!raw-loader!../includes/templates/Sidebar.mustache';
import sidebarLegacyTemplate from '!!raw-loader!../includes/templates/legacy/Sidebar.mustache';
import { vectorMenuTemplate } from './MenuDropdown.stories.data';
import { PORTALS } from './MenuPortal.stories.data';

const HTML_LOGO_ATTRIBUTES = `class="mw-wiki-logo" href="/wiki/Main_Page" title="Visit the main page"`;
const SIDEBAR_BEFORE_OUTPUT_HOOKINFO = `Beware: Portals can be added, removed or reordered using
SidebarBeforeOutput hook as in this example.`;

export { sidebarTemplate, sidebarLegacyTemplate };

export const SIDEBAR_TEMPLATE_PARTIALS = {
	Menu: vectorMenuTemplate
};

export const SIDEBAR_DATA = {
	withNoPortals: {
		'has-logo': true,
		'array-portals-rest': [],
		'html-logo-attributes': HTML_LOGO_ATTRIBUTES
	},
	withPortalsAndOptOut: {
		'has-logo': false,
		'data-portals-first': PORTALS.navigation,
		'data-emphasized-sidebar-action': {
			href: '#',
			text: 'Switch to old look',
			title: 'Change your settings to go back to the old look of the skin (legacy Vector)'
		},
		'array-portals-rest': [
			PORTALS.toolbox,
			PORTALS.otherProjects
		],
		'data-portals-languages': PORTALS.langlinks,
		'html-logo-attributes': HTML_LOGO_ATTRIBUTES
	},
	withPortals: {
		'has-logo': true,
		'data-portals-first': PORTALS.navigation,
		'array-portals-rest': [
			PORTALS.toolbox,
			PORTALS.otherProjects
		],
		'data-portals-languages': PORTALS.langlinks,
		'html-logo-attributes': HTML_LOGO_ATTRIBUTES
	},
	withoutLogo: {
		'has-logo': false,
		'data-portals-languages': PORTALS.langlinks,
		'array-portals-first': PORTALS.navigation,
		'array-portals-rest': [
			PORTALS.toolbox,
			PORTALS.otherProjects
		]
	},
	thirdParty: {
		'has-logo': true,
		'array-portals-rest': [
			PORTALS.toolbox,
			PORTALS.navigation,
			{
				'html-portal-content': SIDEBAR_BEFORE_OUTPUT_HOOKINFO
			}
		],
		'html-logo-attributes': HTML_LOGO_ATTRIBUTES
	}
};
