/**
 * @external SearchData
 */

import searchBoxTemplate from '!!raw-loader!../includes/templates/SearchBox.mustache';
import { htmluserlangattributes } from './utils';

export { searchBoxTemplate };

/**
 * @type {SearchData}
 */
export const searchBoxData = {
	'form-action': '/w/index.php',
	'html-userlangattributes': htmluserlangattributes,
	'msg-search': 'Search',
	'html-input': '<input type="search" name="search" placeholder="Search Wikipedia" title="Search Wikipedia [⌃⌥f]" accesskey="f" id="searchInput" autocomplete="off">',
	'page-title': 'Special:Search',
	'html-button-search-fallback': '<input type="submit" name="fulltext" value="Search" title="Search pages for this text" id="mw-searchButton" class="searchButton mw-fallbackSearchButton"/>',
	'html-button-search': '<input type="submit" name="go" value="Go" title="Go to a page with this exact name if it exists" id="searchButton" class="searchButton">'
};
