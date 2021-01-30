( function () {
	// To allow users to cancel a thanks in the event of an accident, the action is delayed.
	var THANKS_DELAY = 2000,
		mobile = mw.mobileFrontend.require( 'mobile.startup' ),
		Button = mobile.Button,
		Icon = mobile.Icon,
		msgOptions = {
			// tag ensures that only one message in workflow is shown at any time
			tag: 'thanks'
		};
	/**
	 * Attempt to execute a thank operation for a given edit
	 *
	 * @param {string} name The username of the user who made the edit
	 * @param {string} revision The revision the user created
	 * @param {string} recipientGender The gender of the user who made the edit
	 * @return {jQuery.Promise} The thank operation's status.
	 */
	function thankUser( name, revision, recipientGender ) {
		return ( new mw.Api() ).postWithToken( 'csrf', {
			action: 'thank',
			rev: revision,
			source: 'mobilediff'
		} ).then( function () {
			mw.notify( mw.msg( 'thanks-button-action-completed', name, recipientGender, mw.user ),
				msgOptions );
		}, function ( errorCode ) {
			switch ( errorCode ) {
				case 'invalidrevision':
					mw.notify( mw.msg( 'thanks-error-invalidrevision' ), msgOptions );
					break;
				case 'ratelimited':
					mw.notify( mw.msg( 'thanks-error-ratelimited', recipientGender ), msgOptions );
					break;
				default:
					mw.notify( mw.msg( 'thanks-error-undefined', errorCode ), msgOptions );
			}
		} );
	}

	/**
	 * Disables the thank button marking the user as thanked
	 *
	 * @param {jQuery} $button used for thanking
	 * @param {string} gender The gender of the user who made the edit
	 * @return {jQuery} $button now disabled
	 */
	function disableThanks( $button, gender ) {
		return $button
			.addClass( 'thanked' )
			.prop( 'disabled', true )
			.text( mw.msg( 'thanks-button-thanked', mw.user, gender ) );
	}

	/**
	 * Create a thank button for a given edit
	 *
	 * @param {string} name The username of the user who made the edit
	 * @param {string} rev The revision the user created
	 * @param {string} gender The gender of the user who made the edit
	 * @return {jQuery|null} The HTML of the button.
	 */
	function createThankLink( name, rev, gender ) {
		var timeout,
			button = new Button( {
				progressive: true,
				additionalClassNames: 'mw-mf-action-button'
			} ),
			$button = button.$el;

		// append icon
		new Icon( {
			name: 'userTalk',
			glyphPrefix: 'thanks',
			hasText: true,
			label: mw.msg( 'thanks-button-thank', mw.user, gender )
		} ).$el.appendTo( $button );

		// Don't make thank button for self
		if ( name === mw.config.get( 'wgUserName' ) ) {
			return null;
		}
		// See if user has already been thanked for this edit
		if ( mw.config.get( 'wgThanksAlreadySent' ) ) {
			return disableThanks( $button, gender );
		}

		function cancelThanks( $btn ) {
			// Hide the notification
			$( '.mw-notification' ).hide();
			// Clear the queued thanks!
			clearTimeout( timeout );
			timeout = null;
			$btn.prop( 'disabled', false );
		}

		function queueThanks( $btn ) {
			var $msg = $( '<div>' ).addClass( 'mw-thanks-notification' ).text(
				mw.msg( 'thanks-button-action-queued', name, gender )
			);
			$( '<a>' ).text( mw.msg( 'thanks-button-action-cancel' ) ).appendTo( $msg );
			mw.notify( $msg, msgOptions );
			// Make it possible to cancel
			$msg.find( 'a' ).on( 'click', function () {
				cancelThanks( $btn );
			} );
			timeout = setTimeout( function () {
				timeout = null;
				thankUser( name, rev, gender ).then( function () {
					disableThanks( $btn, gender );
				} );
			}, THANKS_DELAY );
		}

		return $button.on( 'click', function () {
			var $this = $( this );
			$this.prop( 'disabled', true );
			// eslint-disable-next-line no-jquery/no-class-state
			if ( !$this.hasClass( 'thanked' ) && !timeout ) {
				queueThanks( $this );
			}
		} );
	}

	/**
	 * Initialise a thank button in the given container.
	 *
	 * @param {jQuery} $user existing element with data attributes associated describing a user.
	 * @param {jQuery} $container to render button in
	 */
	function init( $user, $container ) {
		var username = $user.data( 'user-name' ),
			rev = $user.data( 'revision-id' ),
			gender = $user.data( 'user-gender' ),
			$thankBtn;

		$thankBtn = createThankLink( username, rev, gender );
		if ( $thankBtn ) {
			$thankBtn.prependTo( $container );
		}

	}

	$( function () {
		init( $( '.mw-mf-user' ), $( '#mw-mf-userinfo' ) );
	} );

	// Expose for testing purposes
	mw.thanks = $.extend( {}, mw.thanks || {}, {
		_mobileDiffInit: init
	} );
}() );
