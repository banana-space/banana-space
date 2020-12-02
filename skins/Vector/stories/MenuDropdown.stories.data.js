/**
 * @external MenuDefinition
 */

import vectorMenuTemplate from '!!raw-loader!../includes/templates/Menu.mustache';
import { htmluserlangattributes } from './utils';

export { vectorMenuTemplate };

/**
 * @type {MenuDefinition}
 */
export const moreData = {
	'is-dropdown': true,
	class: 'vector-menu-dropdown',
	'list-classes': 'vector-menu-content-list',
	label: 'More',
	id: 'p-cactions',
	'label-id': 'p-cactions-label',
	'html-userlangattributes': htmluserlangattributes,
	'html-items': `<li id="ca-delete">
	<a href="/w/index.php?title=Main_Page&amp;action=delete"
		title="Delete this page [⌃⌥d]" accesskey="d">Delete</a>
</li>
<li id="ca-move">
	<a href="/w/index.php/Special:MovePage/Main_Page"
		title="Move this page [⌃⌥m]" accesskey="m">Move</a>
</li>
<li id="ca-protect">
	<a href="/w/index.php?title=Main_Page&amp;action=protect"
		title="Protect this page [⌃⌥=]" accesskey="=">Protect</a>
</li>`
};

/**
 * @type {MenuDefinition}
 */
export const variantsData = {
	'is-dropdown': true,
	class: 'vector-menu-dropdown',
	'list-classes': 'vector-menu-content-list',
	label: '新加坡简体',
	id: 'p-variants',
	'label-id': 'p-variants-label',
	'html-userlangattributes': htmluserlangattributes,
	'html-items': `<li id="ca-varlang-0">
	<a href="/zh/%E4%B8%AD%E5%8D%8E%E4%BA%BA%E6%B0%91%E5%85%B1%E5%92%8C%E5%9B%BD"
		hreflang="zh" lang="zh">不转换</a></li>
<li id="ca-varlang-1">
	<a href="/zh-hans/%E4%B8%AD%E5%8D%8E%E4%BA%BA%E6%B0%91%E5%85%B1%E5%92%8C%E5%9B%BD"
		hreflang="zh-Hans" lang="zh-Hans">简体</a>
</li>
<li id="ca-varlang-2">
	<a href="/zh-hant/%E4%B8%AD%E5%8D%8E%E4%BA%BA%E6%B0%91%E5%85%B1%E5%92%8C%E5%9B%BD"
		hreflang="zh-Hant" lang="zh-Hant">繁體</a>
</li>
<li id="ca-varlang-3">
	<a href="/zh-cn/%E4%B8%AD%E5%8D%8E%E4%BA%BA%E6%B0%91%E5%85%B1%E5%92%8C%E5%9B%BD"
		hreflang="zh-Hans-CN" lang="zh-Hans-CN">大陆简体</a>
</li>
<li id="ca-varlang-4">
	<a href="/zh-hk/%E4%B8%AD%E5%8D%8E%E4%BA%BA%E6%B0%91%E5%85%B1%E5%92%8C%E5%9B%BD"
		hreflang="zh-Hant-HK" lang="zh-Hant-HK">香港繁體</a>
</li>
<li id="ca-varlang-5">
	<a href="/zh-mo/%E4%B8%AD%E5%8D%8E%E4%BA%BA%E6%B0%91%E5%85%B1%E5%92%8C%E5%9B%BD"
		hreflang="zh-Hant-MO" lang="zh-Hant-MO">澳門繁體</a>
</li>
<li id="ca-varlang-7" class="selected">
	<a href="/zh-sg/%E4%B8%AD%E5%8D%8E%E4%BA%BA%E6%B0%91%E5%85%B1%E5%92%8C%E5%9B%BD"
		hreflang="zh-Hans-SG" lang="zh-Hans-SG">新加坡简体</a>
</li>
<li id="ca-varlang-8">
	<a href="/zh-tw/%E4%B8%AD%E5%8D%8E%E4%BA%BA%E6%B0%91%E5%85%B1%E5%92%8C%E5%9B%BD"
		hreflang="zh-Hant-TW" lang="zh-Hant-TW">臺灣正體</a>
</li>`
};
