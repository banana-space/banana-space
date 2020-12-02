/**
 * @external MenuDefinition
 */

/* eslint-disable quotes */

import mustache from 'mustache';
import { vectorMenuTemplate as portalTemplate } from './MenuDropdown.stories.data';
import '../resources/skins.vector.styles/MenuPortal.less';
import '../.storybook/common.less';
import { placeholder, htmluserlangattributes } from './utils';

/**
 * @param {MenuDefinition} data
 * @return {HTMLElement}
 */
export const wrapPortlet = ( data ) => {
	const node = document.createElement( 'div' );
	node.setAttribute( 'id', 'mw-panel' );
	node.innerHTML = mustache.render( portalTemplate, data );
	return node;
};

/**
 * @param {string} html
 * @return {string}
 */
const portletAfter = ( html ) => {
	return `<div class="after-portlet after-portlet-tb">${html}</div>`;
};

/**
 * @type {Object.<string, MenuDefinition>}
 */
export const PORTALS = {
	example: {
		id: 'p-example',
		class: 'vector-menu-portal portal',
		'list-classes': 'vector-menu-content-list',
		'html-tooltip': 'Message tooltip-p-example acts as tooltip',
		label: 'Portal title',
		'label-id': 'p-example-label',
		'html-userlangattributes': htmluserlangattributes,
		'html-items': `
<li><a href='#'>A list of links</a></li>
<li><a href='#'>with ids</a></li>
<li><a href='#'>on each list item</a></li>
`,
		'html-after-portal': portletAfter(
			placeholder( `<p>Beware: The <a href="https://codesearch.wmflabs.org/search/?q=BaseTemplateAfterPortlet&i=nope&files=&repos=">BaseTemplateAfterPortlet hook</a> can be used to inject arbitary HTML here for any portlet.</p>`, 60 )
		)
	},
	navigation: {
		id: 'p-navigation',
		class: 'portal portal-first',
		'list-classes': 'vector-menu-content-list',
		'html-tooltip': 'A message tooltip-p-navigation must exist for this to appear',
		label: 'Navigation',
		'label-id': 'p-navigation-label',
		'html-userlangattributes': htmluserlangattributes,
		'html-items': `
		<li id="n-mainpage-description"><a href="/wiki/Main_Page" title="Visit the main page [⌃⌥z]" accesskey="z">Main page</a></li><li id="n-contents"><a href="/wiki/Wikipedia:Contents" title="Guides to browsing Wikipedia">Contents</a></li><li id="n-featuredcontent"><a href="/wiki/Wikipedia:Featured_content" title="Featured content – the best of Wikipedia">Featured content</a></li><li id="n-currentevents"><a href="/wiki/Portal:Current_events" title="Find background information on current events">Current events</a></li><li id="n-randompage"><a href="/wiki/Special:Random" title="Load a random page [⌃⌥x]" accesskey="x">Random page</a></li><li id="n-sitesupport"><a href="https://donate.wikimedia.org/wiki/Special:FundraiserRedirector?utm_source=donate&amp;utm_medium=sidebar&amp;utm_campaign=C13_en.wikipedia.org&amp;uselang=en" title="Support us">Donate</a></li><li id="n-shoplink"><a href="//shop.wikimedia.org" title="Visit the Wikipedia store">Wikipedia store</a></li>
`,
		'html-after-portal': portletAfter( placeholder( 'Possible hook output (navigation)', 50 ) )
	},
	toolbox: {
		id: 'p-tb',
		class: 'vector-menu-portal portal',
		'list-classes': 'vector-menu-content-list',
		'html-tooltip': 'A message tooltip-p-tb must exist for this to appear',
		label: 'Tools',
		'label-id': 'p-tb-label',
		'html-userlangattributes': htmluserlangattributes,
		'html-items': `
<li id="t-whatlinkshere"><a href="/wiki/Special:WhatLinksHere/Spain" title="A list of all wiki pages that link here [⌃⌥j]" accesskey="j">What links here</a></li><li id="t-recentchangeslinked"><a href="/wiki/Special:RecentChangesLinked/Spain" rel="nofollow" title="Recent changes in pages linked from this page [⌃⌥k]" accesskey="k">Related changes</a></li><li id="t-upload"><a href="/wiki/Wikipedia:File_Upload_Wizard" title="Upload files [⌃⌥u]" accesskey="u">Upload file</a></li><li id="t-specialpages"><a href="/wiki/Special:SpecialPages" title="A list of all special pages [⌃⌥q]" accesskey="q">Special pages</a></li><li id="t-permalink"><a href="/w/index.php?title=Spain&amp;oldid=935087243" title="Permanent link to this revision of the page">Permanent link</a></li><li id="t-info"><a href="/w/index.php?title=Spain&amp;action=info" title="More information about this page">Page information</a></li><li id="t-wikibase"><a href="https://www.wikidata.org/wiki/Special:EntityPage/Q29" title="Link to connected data repository item [⌃⌥g]" accesskey="g">Wikidata item</a></li><li id="t-cite"><a href="/w/index.php?title=Special:CiteThisPage&amp;page=Spain&amp;id=935087243" title="Information on how to cite this page">Cite this page</a></li>
`,
		'html-after-portal': portletAfter( placeholder( 'Possible hook output (tb)', 50 ) )
	},
	langlinks: {
		id: 'p-lang',
		class: 'vector-menu-portal portal',
		'list-classes': 'vector-menu-content-list',
		'html-tooltip': 'A message tooltip-p-lang must exist for this to appear',
		label: 'In other languages',
		'label-id': 'p-lang-label',
		'html-userlangattributes': htmluserlangattributes,
		'html-items': `
		<li class="interlanguage-link interwiki-ace">
			<a href="https://ace.wikipedia.org/wiki/Seupanyo"
				title="Seupanyo – Achinese" lang="ace" hreflang="ace" class="interlanguage-link-target">Acèh</a>
				</li><li class="interlanguage-link interwiki-kbd"><a href="https://kbd.wikipedia.org/wiki/%D0%AD%D1%81%D0%BF%D0%B0%D0%BD%D0%B8%D1%8D" title="Эспаниэ – Kabardian" lang="kbd" hreflang="kbd" class="interlanguage-link-target">Адыгэбзэ</a></li><li class="interlanguage-link interwiki-ady"><a href="https://ady.wikipedia.org/wiki/%D0%98%D1%81%D0%BF%D0%B0%D0%BD%D0%B8%D0%B5" title="Испание – Adyghe" lang="ady" hreflang="ady" class="interlanguage-link-target">Адыгабзэ</a></li><li class="interlanguage-link interwiki-af"><a href="https://af.wikipedia.org/wiki/Spanje" title="Spanje – Afrikaans" lang="af" hreflang="af" class="interlanguage-link-target">Afrikaans</a></li><li class="interlanguage-link interwiki-ak"><a href="https://ak.wikipedia.org/wiki/Spain" title="Spain – Akan" lang="ak" hreflang="ak" class="interlanguage-link-target">Akan</a></li><li class="interlanguage-link interwiki-als"><a href="https://als.wikipedia.org/wiki/Spanien" title="Spanien – Alemannisch" lang="gsw" hreflang="gsw" class="interlanguage-link-target">Alemannisch</a></li><li class="interlanguage-link interwiki-am"><a href="https://am.wikipedia.org/wiki/%E1%8A%A5%E1%88%B5%E1%8D%93%E1%8A%95%E1%8B%AB" title="እስፓንያ – Amharic" lang="am" hreflang="am" class="interlanguage-link-target">አማርኛ</a></li><li class="interlanguage-link interwiki-ang"><a href="https://ang.wikipedia.org/wiki/Sp%C4%93onland" title="Spēonland – Old English" lang="ang" hreflang="ang" class="interlanguage-link-target">Ænglisc</a></li><li class="interlanguage-link interwiki-ab"><a href="https://ab.wikipedia.org/wiki/%D0%98%D1%81%D0%BF%D0%B0%D0%BD%D0%B8%D0%B0" title="Испаниа – Abkhazian" lang="ab" hreflang="ab" class="interlanguage-link-target">Аҧсшәа</a></li><li class="interlanguage-link interwiki-ar badge-Q17437798 badge-goodarticle" title="good article"><a href="https://ar.wikipedia.org/wiki/%D8%A5%D8%B3%D8%A8%D8%A7%D9%86%D9%8A%D8%A7" title="إسبانيا – Arabic" lang="ar" hreflang="ar" class="interlanguage-link-target">العربية</a></li><li class="interlanguage-link interwiki-an">
`,
		'html-after-portal': portletAfter(
			`<span class="wb-langlinks-edit wb-langlinks-link"><a href="https://www.wikidata.org/wiki/Special:EntityPage/Q29#sitelinks-wikipedia" title="Edit interlanguage links (provided by WikiBase extension)" class="wbc-editpage">Edit links</a></span></div>
${placeholder( `<p>Further hook output possible (lang)</p>`, 60 )}`
		)
	},
	otherProjects: {
		id: 'p-wikibase-otherprojects',
		class: 'vector-menu-portal portal',
		'list-classes': 'vector-menu-content-list',
		'html-tooltip': 'A message tooltip-p-wikibase-otherprojects must exist for this to appear',
		label: 'In other projects',
		'label-id': 'p-wikibase-otherprojects-label',
		'html-userlangattributes': htmluserlangattributes,

		'html-items': `
		<li class="wb-otherproject-link wb-otherproject-commons"><a href="https://commons.wikimedia.org/wiki/Category:Spain" hreflang="en">Wikimedia Commons</a></li><li class="wb-otherproject-link wb-otherproject-wikinews"><a href="https://en.wikinews.org/wiki/Category:Spain" hreflang="en">Wikinews</a></li><li class="wb-otherproject-link wb-otherproject-wikiquote"><a href="https://en.wikiquote.org/wiki/Spain" hreflang="en">Wikiquote</a></li><li class="wb-otherproject-link wb-otherproject-wikivoyage"><a href="https://en.wikivoyage.org/wiki/Spain" hreflang="en">Wikivoyage</a></li>`,
		'html-after-portal': ''
	}
};
