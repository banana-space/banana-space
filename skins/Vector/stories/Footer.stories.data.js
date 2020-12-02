/* eslint-disable quotes */

import { htmluserlangattributes, placeholder } from './utils';
import footerTemplate from '!!raw-loader!../includes/templates/Footer.mustache';

const FOOTER_ROWS = [
	{
		id: 'footer-info',
		'array-items': [
			{
				id: 'footer-info-lastmod',
				html: 'This page was last modified on 10 January 2020, at 21:24.'
			},
			{
				id: 'footer-info-copyright',
				html: `This text is available under the <a href="https://creativecommons.org/licenses/by-sa/3.0/">Creative Commons Attribution-ShareAlike Licence</a>;
additional terms may apply. See <a href="https://foundation.wikimedia.org/wiki/Special:MyLanguage/Terms_of_Use">Terms of Use</a> for details.`

			}
		]
	},
	{
		id: 'footer-places',
		'array-items': [
			{
				id: 'footer-places-privacy',
				html: `<a href="https://foundation.wikimedia.org/wiki/Privacy_policy" class="extiw" title="wmf:Privacy policy">Privacy policy</a>`
			},
			{
				id: 'footer-places-about',
				html: `<a href="/wiki/Wikipedia:About" title="Wikipedia:About">About Wikipedia</a>`
			},
			{
				id: 'footer-places-disclaimer',
				html: `<a href="/wiki/Wikipedia:General_disclaimer" title="Wikipedia:General disclaimer">Disclaimers</a>`
			},
			{
				id: 'footer-places-contact',
				html: `<a href="//en.wikipedia.org/wiki/Wikipedia:Contact_us">Contact Wikipedia</a>`
			},
			{
				id: 'footer-places-developers',
				html: `<a href="https://www.mediawiki.org/wiki/Special:MyLanguage/How_to_contribute">Developers</a>`
			},
			{
				id: 'footer-places-statslink',
				html: `<a href="https://stats.wikimedia.org/v2/#/en.wikipedia.org">Statistics</a>`
			},
			{
				id: 'footer-places-cookiestatement',
				html: `<a href="https://foundation.wikimedia.org/wiki/Cookie_statement">Cookie statement</a>`
			},
			{
				id: 'footer-places-mobileview',
				html: `<a href="//en.m.wikipedia.org/w/index.php?title=Paris&amp;useskin=vector&amp;mobileaction=toggle_view_mobile" class="noprint stopMobileRedirectToggle">Mobile view</a>`
			}
		]
	},
	{
		id: 'footer-icons',
		'array-items': [
			{
				id: 'footer-copyrightico',
				html: `<a href="https://wikimediafoundation.org/"><img src="https://wikipedia.org/static/images/wikimedia-button.png" srcset="https://wikipedia.org/static/images/wikimedia-button-1.5x.png 1.5x, https://wikipedia.org/static/images/wikimedia-button-2x.png 2x" width="88" height="31" alt="Wikimedia Foundation"/></a>`
			},
			{
				id: 'footer-poweredbyico',
				html: `<a href="https://www.mediawiki.org/"><img src="https://wikipedia.org/static/images/poweredby_mediawiki_88x31.png" alt="Powered by MediaWiki" srcset="https://wikipedia.org/static/images/poweredby_mediawiki_132x47.png 1.5x, https://wikipedia.org/static/images/poweredby_mediawiki_176x62.png 2x" width="88" height="31"/></a>`
			}
		]
	}
];

export { footerTemplate };

export const FOOTER_TEMPLATE_DATA = {
	'html-userlangattributes': htmluserlangattributes,
	'html-hook-vector-before-footer': placeholder( 'output of VectorBeforeFooter hook (deprecated 1.35)', 20 ),
	'array-footer-rows': FOOTER_ROWS
};
