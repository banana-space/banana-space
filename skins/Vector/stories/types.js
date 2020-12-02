/**
 * @typedef {Object} Indicator
 * @property {string} html of the indicator link.
 * @property {string} id of the indicator.
 * @property {string} class of the indicator
 */

/**
 * @typedef {Object} LogoOptions
 * @property {string} src of logo. Can be relative, absolute or data uri.
 * @property {string} [alt] text of logo.
 * @property {number} width of asset
 * @property {number} height of asset
 */

/**
 * @typedef {Object} ResourceLoaderSkinModuleLogos
 * @property {string} [icon] e.g. Wikipedia globe
 * @property {LogoOptions} [wordmark] e.g. Legacy Vector logo
 * @property {LogoOptions} [tagline] e.g. Legacy Vector logo
 */

/**
 * @typedef {Object} LogoTemplateData
 * @property {ResourceLoaderSkinModuleLogos} data-logos as configured,
 *  the return value of ResourceLoaderSkinModule::getAvailableLogos.
 * @property {string} msg-sitetitle alternate text for wordmark
	href the url to navigate to on click.
 * @property {string} msg-sitesubtitle alternate text for tagline.
 */

/**
 * @typedef {Object} SidebarData
 * @property {MenuDefinition} data-portals-languages
 * @property {MenuDefinition} data-portals-first
 * @property {MenuDefinition[]} array-portals-rest
 * @property {boolean} [has-logo] whether to show a logo or not.
 */

/**
 * @typedef {Object} SearchData
 * @property {string|null} msg-search
 * @property {string} [html-userlangattributes]
 * @property {string} form-action URL
 * @property {string|null} html-input
 * @property {string|null} page-title the title of the search page
 * @property {string|null} html-button-search-fallback
 * @property {string|null} html-button-search
 */

/**
 * @typedef {Object} MenuDefinition
 * @property {string} id
 * @property {string} label-id
 * @property {string} label
 * @property {string} html-items
 * @property {string} [html-tooltip]
 * @property {string} [class] of menu
 * @property {string} list-classes of the unordered list element inside the menu
 * @property {string} [html-userlangattributes]
 * @property {boolean} [is-dropdown]
 * @property {string} [html-hook-vector-after-toolbox] Deprecated and used
 *  by the toolbox portal menu.
 * @property {string} [html-after-portal] Additional HTML specific to portal menus.
 */
