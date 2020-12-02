/**
 * @external MenuDefinition
 */

import { htmluserlangattributes } from './utils';

/**
 * @type {MenuDefinition}
 */
export const pageActionsData = {
	id: 'p-views',
	class: 'vector-menu-tabs vectorTabs',
	'list-classes': 'vector-menu-content-list',
	'label-id': 'p-views-label',
	label: 'Views',
	'html-userlangattributes': htmluserlangattributes,
	'html-items': `<li id="ca-view" class="collapsible selected">
		<a href="/wiki/Main_Page">Read</a>
</li>
<li id="ca-viewsource" class="collapsible">
	<a href="/w/index.php?title=Main_Page&amp;action=edit" title="This page is protected.
You can view its source [⌃⌥e]" accesskey="e">View source</a></li>
<li id="ca-history" class="collapsible">
	<a href="/w/index.php?title=Main_Page&amp;action=history" title="Past revisions of this page [⌃⌥h]" accesskey="h">View history</a>
</li>
<li id="ca-unwatch" class="collapsible icon mw-watchlink"><a href="/w/index.php?title=Main_Page&amp;action=unwatch" data-mw="interface" title="Remove this page from your watchlist [⌃⌥w]" accesskey="w">Unwatch</a></li>
`
};

/**
 * @type {MenuDefinition}
 */
export const namespaceTabsData = {
	id: 'p-namespaces',
	class: 'vector-menu-tabs vectorTabs',
	'list-classes': 'vector-menu-content-list',
	'label-id': 'p-namespaces-label',
	label: 'Namespaces',
	'html-userlangattributes': htmluserlangattributes,
	'html-items': `<li id="ca-nstab-main" class="selected"><a href="/wiki/Main_Page" title="View the content page [⌃⌥c]" accesskey="c">Main page</a></li>
<li id="ca-talk"><a href="/wiki/Talk:Main_Page" rel="discussion" title="Discussion about the content page [⌃⌥t]" accesskey="t">Talk (3)</a></li>`
};
