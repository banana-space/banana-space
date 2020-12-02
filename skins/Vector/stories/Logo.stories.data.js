/**
 * @external LogoOptions
 * @external LogoTemplateData
 */

import logoTemplate from '!!raw-loader!../includes/templates/Logo.mustache';

/**
 * @type {LogoOptions}
 */
const wordmark = {
	src: '/assets/wordmark.svg',
	width: 116,
	height: 18
};

/**
 * @type {LogoOptions}
 */
const tagline = {
	src: '/assets/tagline.svg',
	width: 117,
	height: 13
};

/**
 * @type {string}
 */
const icon = '/assets/icon.png';

/**
 * @type {LogoTemplateData}
 */
const wordmarkTaglineIcon = {
	'data-logos': {
		wordmark, tagline, icon
	},
	'msg-sitetitle': 'Wikipedia',
	'msg-sitesubtitle': 'the free encyclopedia'
};

/**
 * @type {LogoTemplateData}
 */
const wordmarkIcon = {
	'data-logos': {
		wordmark, icon
	},
	'msg-sitetitle': 'Wikipedia',
	'msg-sitesubtitle': 'the free encyclopedia'
};

/**
 * @type {LogoTemplateData}
 */
const wordmarkOnly = {
	'data-logos': {
		wordmark
	},
	'msg-sitetitle': 'Wikipedia',
	'msg-sitesubtitle': 'the free encyclopedia'
};

/**
 * @type {LogoTemplateData}
 */
const noLogo = {
	'data-logos': {},
	'msg-sitetitle': 'Wikipedia',
	'msg-sitesubtitle': 'the free encyclopedia'
};

/**
 * @type {Object.<string, LogoTemplateData>}
 */
const LOGO_TEMPLATE_DATA = {
	wordmarkTaglineIcon,
	wordmarkIcon,
	wordmarkOnly,
	noLogo
};

export { LOGO_TEMPLATE_DATA, logoTemplate };
