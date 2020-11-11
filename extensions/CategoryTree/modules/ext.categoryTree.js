/**
 * JavaScript for the CategoryTree extension.
 *
 * © 2006 Daniel Kinzler
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Extensions
 * @author Daniel Kinzler, brightbyte.de
 */

( function ( $, mw ) {
	var loadChildren;

	/**
	 * Expands a given node (loading it's children if not loaded)
	 *
	 * @param {jQuery} $link
	 */
	function expandNode( $link ) {
		// Show the children node
		var $children = $link.parents( '.CategoryTreeItem' )
			.siblings( '.CategoryTreeChildren' );
		$children.show();

		$link
			.text( mw.msg( 'categorytree-collapse-bullet' ) )
			.attr( 'title', mw.msg( 'categorytree-collapse' ) )
			.data( 'ct-state', 'expanded' );

		if ( !$link.data( 'ct-loaded' ) ) {
			loadChildren( $link, $children );
		}
	}

	/**
	 * Collapses a node
	 *
	 * @param {jQuery} $link
	 */
	function collapseNode( $link ) {
		// Hide the children node
		$link.parents( '.CategoryTreeItem' )
			.siblings( '.CategoryTreeChildren' ).hide();

		$link
			.text( mw.msg( 'categorytree-expand-bullet' ) )
			.attr( 'title', mw.msg( 'categorytree-expand' ) )
			.data( 'ct-state', 'collapsed' );
	}

	/**
	 * Handles clicks on the expand buttons, and calls the appropriate function
	 *
	 * @context {Element} CategoryTreeToggle
	 */
	function handleNode() {
		var $link = $( this );
		if ( $link.data( 'ct-state' ) === 'collapsed' ) {
			expandNode( $link );
		} else {
			collapseNode( $link );
		}
	}

	/**
	 * Attach click handler to buttons
	 *
	 * @param {jQuery} $content
	 */
	function attachHandler( $content ) {
		$content.find( '.CategoryTreeToggle' )
			.click( handleNode )
			.attr( 'title', function () {
				return mw.msg(
					$( this ).data( 'ct-state' ) === 'collapsed' ?
						'categorytree-expand' :
						'categorytree-collapse'
				);
			} )
			.addClass( 'CategoryTreeToggleHandlerAttached' );
	}

	/**
	 * Loads children for a node via an HTTP call
	 *
	 * @param {jQuery} $link
	 * @param {jQuery} $children
	 */
	loadChildren = function ( $link, $children ) {
		var $linkParentCTTag, ctTitle, ctMode, ctOptions;

		/**
		 * Error callback
		 */
		function error() {
			var $retryLink;

			$retryLink = $( '<a>' )
				.text( mw.msg( 'categorytree-retry' ) )
				.attr( {
					role: 'button',
					tabindex: 0
				} )
				.on( 'click keypress', function ( e ) {
					if (
						e.type === 'click' ||
						e.type === 'keypress' && e.which === 13
					) {
						loadChildren( $link, $children );
					}
				} );

			$children
				.text( mw.msg( 'categorytree-error' ) + ' ' )
				.append( $retryLink );
		}

		$link.data( 'ct-loaded', true );

		$children.append(
			$( '<i class="CategoryTreeNotice"></i>' )
				.text( mw.msg( 'categorytree-loading' ) )
		);

		$linkParentCTTag = $link.parents( '.CategoryTreeTag' );

		// Element may not have a .CategoryTreeTag parent, fallback to defauls
		// Probably a CategoryPage (@todo: based on what?)
		ctTitle = $link.data( 'ct-title' );
		ctMode = $linkParentCTTag.data( 'ct-mode' );
		ctMode = typeof ctMode === 'number' ? ctMode : undefined;
		ctOptions = $linkParentCTTag.attr( 'data-ct-options' );
		if ( !ctOptions ) {
			ctOptions = mw.config.get( 'wgCategoryTreePageCategoryOptions' );
		}

		// Mode and options have defaults or fallbacks, title does not.
		// Don't make a request if there is no title.
		if ( typeof ctTitle !== 'string' ) {
			error();
			return;
		}

		new mw.Api().get( {
			action: 'categorytree',
			category: ctTitle,
			options: ctOptions,
			uselang: mw.config.get( 'wgUserLanguage' ),
			formatversion: 2
		} ).done( function ( data ) {
			data = data.categorytree.html;

			if ( data === '' ) {
				switch ( ctMode ) {
					// CategoryTreeMode::CATEGORIES = 0
					case 0:
						data = mw.msg( 'categorytree-no-subcategories' );
						break;
					// CategoryTreeMode::PAGES = 10
					case 10:
						data = mw.msg( 'categorytree-no-pages' );
						break;
					// CategoryTreeMode::PARENTS = 100
					case 100:
						data = mw.msg( 'categorytree-no-parent-categories' );
						break;
					// CategoryTreeMode::ALL = 20
					default:
						data = mw.msg( 'categorytree-nothing-found' );
				}

				data = $( '<i class="CategoryTreeNotice"></i>' ).text( data );
			}

			$children.html( data );
			attachHandler( $children );

		} )
			.fail( error );
	};

	// Register click events
	mw.hook( 'wikipage.content' ).add( attachHandler );

	$( function () {
		// Attach click handler for sidebar
		attachHandler( $( '#p-categorytree-portlet' ) );
	} );

}( jQuery, mediaWiki ) );
