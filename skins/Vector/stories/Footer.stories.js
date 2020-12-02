import mustache from 'mustache';
import { FOOTER_TEMPLATE_DATA, footerTemplate } from './Footer.stories.data';
import '../resources/skins.vector.styles/Footer.less';
import '../.storybook/common.less';

export default {
	title: 'Footer'
};

export const footer = () => mustache.render( footerTemplate, FOOTER_TEMPLATE_DATA );
