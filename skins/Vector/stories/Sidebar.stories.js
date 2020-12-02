import mustache from 'mustache';
import '../.storybook/common.less';
import '../resources/skins.vector.styles/Sidebar.less';
import '../resources/skins.vector.styles/SidebarLogo.less';
import '../resources/skins.vector.styles/MenuPortal.less';
import { sidebarTemplate,
	sidebarLegacyTemplate, SIDEBAR_DATA, SIDEBAR_TEMPLATE_PARTIALS } from './Sidebar.stories.data';

export default {
	title: 'Sidebar'
};

export const sidebarLegacyWithNoPortals = () => mustache.render(
	sidebarLegacyTemplate, SIDEBAR_DATA.withNoPortals, SIDEBAR_TEMPLATE_PARTIALS
);

export const sidebarLegacyWithPortals = () => mustache.render(
	sidebarLegacyTemplate, SIDEBAR_DATA.withPortals, SIDEBAR_TEMPLATE_PARTIALS
);

export const sidebarModernWithoutLogo = () => mustache.render(
	sidebarTemplate, SIDEBAR_DATA.withoutLogo, SIDEBAR_TEMPLATE_PARTIALS
);

export const sidebarModernWithPortals = () => mustache.render(
	sidebarTemplate, SIDEBAR_DATA.withPortals, SIDEBAR_TEMPLATE_PARTIALS
);

export const sidebarModernThirdParty = () => mustache.render(
	sidebarTemplate, SIDEBAR_DATA.thirdParty, SIDEBAR_TEMPLATE_PARTIALS
);
