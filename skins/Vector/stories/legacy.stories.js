import mustache from 'mustache';
import '../resources/skins.vector.styles/skin-legacy.less';
import legacySkinTemplate from '!!raw-loader!../includes/templates/skin-legacy.mustache';
import {
	LEGACY_TEMPLATE_DATA,
	NAVIGATION_TEMPLATE_DATA,
	TEMPLATE_PARTIALS
} from './skin.stories.data';

export default {
	title: 'Skin (legacy)'
};

export const vectorLegacyLoggedOut = () => mustache.render(
	legacySkinTemplate,
	Object.assign(
		{},
		LEGACY_TEMPLATE_DATA,
		NAVIGATION_TEMPLATE_DATA.loggedOutWithVariants
	),
	TEMPLATE_PARTIALS
);

export const vectorLegacyLoggedIn = () => mustache.render(
	legacySkinTemplate,
	Object.assign(
		{},
		LEGACY_TEMPLATE_DATA,
		NAVIGATION_TEMPLATE_DATA.loggedInWithMoreActions
	),
	TEMPLATE_PARTIALS
);
