import mustache from 'mustache';
import { menuTemplate, PERSONAL_MENU_TEMPLATE_DATA } from './Menu.stories.data';
import '../resources/skins.vector.styles/Menu.less';
import '../.storybook/common.less';

export default {
	title: 'Menu'
};

export const menu = () => mustache.render(
	menuTemplate,
	PERSONAL_MENU_TEMPLATE_DATA.defaultMenu
);

export const loggedOut = () => mustache.render( menuTemplate,
	PERSONAL_MENU_TEMPLATE_DATA.loggedOut );

export const loggedInWithEcho = () => mustache.render( menuTemplate,
	PERSONAL_MENU_TEMPLATE_DATA.loggedInWithEcho );

export const loggedInWithULS = () => mustache.render( menuTemplate,
	PERSONAL_MENU_TEMPLATE_DATA.loggedInWithULS );
