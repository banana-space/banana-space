import { PORTALS, wrapPortlet } from './MenuPortal.stories.data';

export default {
	title: 'MenuPortal'
};

export const portal = () => wrapPortlet( PORTALS.example );
export const navigationPortal = () => wrapPortlet( PORTALS.navigation );
export const toolbox = () => wrapPortlet( PORTALS.toolbox );
export const langlinks = () => wrapPortlet( PORTALS.langlinks );
export const otherProjects = () => wrapPortlet( PORTALS.otherProjects );
