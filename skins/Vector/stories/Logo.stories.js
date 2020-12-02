import mustache from 'mustache';
import { logoTemplate, LOGO_TEMPLATE_DATA } from './Logo.stories.data';
import '../resources/skins.vector.styles/Logo.less';

export default {
	title: 'Logo'
};

export const logo = () => mustache.render(
	logoTemplate,
	LOGO_TEMPLATE_DATA.wordmarkTaglineIcon
);

export const logoWordmarkIcon = () => mustache.render(
	logoTemplate,
	LOGO_TEMPLATE_DATA.wordmarkIcon
);

export const logoWordmark = () => mustache.render(
	logoTemplate,
	LOGO_TEMPLATE_DATA.wordmarkOnly
);

export const noLogo = () => mustache.render(
	logoTemplate,
	LOGO_TEMPLATE_DATA.noLogo
);
