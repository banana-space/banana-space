import mustache from 'mustache';
import { menuTemplate as vectorTabsTemplate } from './Menu.stories.data';
import { namespaceTabsData, pageActionsData } from './MenuTabs.stories.data';
import '../resources/skins.vector.styles/MenuTabs.less';
import '../resources/skins.vector.styles/TabWatchstarLink.less';
import '../.storybook/common.less';

export default {
	title: 'MenuTabs'
};

export const pageActionTabs = () => mustache.render( vectorTabsTemplate, pageActionsData );

export const namespaceTabs = () => mustache.render( vectorTabsTemplate, namespaceTabsData );
