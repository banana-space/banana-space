( function () {
	QUnit.module( 'ext.echo.overlay', {
		beforeEach: function () {
			var ApiStub;

			this.$badge = $( '<a class="mw-echo-notifications-badge mw-echo-unseen-notifications">1</a>' );
			this.sandbox.stub( mw.echo, 'getBadge' ).returns( this.$badge );
			// Kill any existing overlays to avoid clashing with other tests
			$( '.mw-echo-overlay' ).remove();

			ApiStub = function ( mode, numberUnreadMessages ) {
				this.mode = mode;
				this.numberUnreadMessages = numberUnreadMessages || 7;
			};
			ApiStub.prototype = {
				post: function ( data ) {
					switch ( data.action ) {
						case 'echomarkread':
							data = this.getNewNotificationCountData( data, this.mode === 'with-new-messages' );
							break;

						case 'echomarkseen':
							data = { query: { echomarkseen: { result: 'success', timestamp: '20140509000000' } } };
							break;

						default:
							throw new Error( 'Unrecognized post action: ' + data.action );
					}

					return $.Deferred().resolve( data );
				},
				postWithToken: function ( type, data ) {
					return this.post( data );
				},
				get: function () {
					var i, id,
						index = [],
						listObj = {},
						data = this.getData();
					// a response which contains 0 unread messages and 1 unread alert
					if ( this.mode === 'no-new-messages' ) {
						data.query.notifications.message = {
							index: [ 100 ],
							list: {
								100: {
									'*': 'Jon sent you a message on the Flow board Talk:XYZ',
									read: '20140805211446',
									category: 'message',
									id: 100,
									timestamp: {
										date: '8 May',
										mw: '20140508211436',
										unix: '1407273276',
										utcunix: '1407273276'
									},
									type: 'message'
								}
							},
							rawcount: 0,
							count: '0'
						};
					// a response which contains 8 unread messages and 1 unread alert
					} else if ( this.mode === 'with-new-messages' ) {
						for ( i = 0; i < 7; i++ ) {
							id = 500 + i;
							index.push( id );
							listObj[ id ] = {
								'*': '!',
								category: 'message',
								id: id,
								timestamp: {
									date: '8 May',
									mw: '20140508211436',
									unix: '1407273276',
									utcunix: '1407273276'
								},
								type: 'message'
							};
						}
						data.query.notifications.message = {
							index: index,
							list: listObj,
							rawcount: this.numberUnreadMessages,
							count: String( this.numberUnreadMessages )
						};
						// Total number is number of messages + number of alerts (1)
						data.query.notifications.count = this.numberUnreadMessages + 1;
						data.query.notifications.rawcount = this.numberUnreadMessages + 1;
					}
					return $.Deferred().resolve( data );
				},
				getNewNotificationCountData: function ( data, hasNewMessages ) {
					var alertCount, messageCount,
						rawCount = 0,
						count = 0;

					messageCount = {
						count: '0',
						rawcount: 0
					};
					alertCount = {
						count: '0',
						rawcount: 0
					};
					if ( data.list === '100' ) {
						alertCount = {
							count: '0',
							rawcount: 0
						};
						count = 1;
						rawCount = 1;
					}

					if ( hasNewMessages && data.sections === 'alert' ) {
						messageCount = {
							count: '7',
							rawcount: 7
						};
						rawCount = 7;
						count = 7;
					}

					if ( data.list === 500 ) {
						messageCount = {
							count: '6',
							rawcount: 6
						};
						rawCount = 6;
						count = 6;
					}

					data = {
						query: {
							echomarkread: {
								alert: alertCount,
								message: messageCount,
								rawcount: rawCount,
								count: count
							}
						}
					};
					return data;
				},
				getData: function () {
					return {
						query: {
							notifications: {
								count: '1',
								rawcount: 1,
								message: {
									rawcount: 0,
									count: '0',
									index: [],
									list: {}
								},
								alert: {
									rawcount: 1,
									count: '1',
									index: [ 70, 71 ],
									list: {
										70: {
											'*': 'Jon mentioned you.',
											agent: { id: 212, name: 'Jon' },
											category: 'mention',
											id: 70,
											read: '20140805211446',
											timestamp: {
												date: '8 May',
												mw: '20140508211436',
												unix: '1407273276',
												utcunix: '1407273276'
											},
											title: {
												full: 'Spiders'
											},
											type: 'mention'
										},
										71: {
											'*': 'X talked to you.',
											category: 'edit-user-talk',
											id: 71,
											timestamp: {
												date: '8 May',
												mw: '20140508211436',
												unix: '1407273276',
												utcunix: '1407273276'
											},
											type: 'edit-user-talk'
										}
									}
								}
							}
						}
					};
				}
			};
			this.ApiStub = ApiStub;
		}
	} );

	QUnit.test( 'mw.echo.overlay.buildOverlay', function ( assert ) {
		var $overlay;
		this.sandbox.stub( mw.echo.overlay, 'api', new this.ApiStub() );
		mw.echo.overlay.buildOverlay( function ( $o ) {
			$overlay = $o;
		} );
		assert.strictEqual( $overlay.find( '.mw-echo-overlay-title ul li' ).length, 1, 'Only one tab in header' );
		assert.strictEqual( $overlay.find( '.mw-echo-notifications' ).length, 1, 'Overlay contains a list of notifications.' );
		assert.strictEqual( $overlay.find( '.mw-echo-notifications li' ).length, 2, 'There are two notifications.' );
		assert.strictEqual( $overlay.find( '.mw-echo-unread' ).length, 1, 'There is one unread notification.' );
		assert.strictEqual( $overlay.find( '.mw-echo-overlay-footer a' ).length, 2,
			'There is a footer with 2 links to preferences and all notifications.' );
		assert.strictEqual( this.$badge.text(),
			'0', 'The alerts are marked as read once opened.' );
		assert.strictEqual( this.$badge.hasClass( 'mw-echo-unseen-notifications' ),
			false, 'The badge no longer indicates new messages.' );
	} );

	QUnit.test( 'mw.echo.overlay.buildOverlay with messages', function ( assert ) {
		var $overlay;
		this.sandbox.stub( mw.echo.overlay, 'api', new this.ApiStub( 'no-new-messages' ) );
		mw.echo.overlay.buildOverlay( function ( $o ) {
			$overlay = $o;
		} );
		assert.strictEqual( $overlay.find( '.mw-echo-overlay-title ul li' ).length, 2, 'There are two tabs in header' );
		assert.strictEqual( $overlay.find( '.mw-echo-notifications' ).length, 2, 'Overlay contains 2 lists of notifications.' );
		assert.strictEqual( $overlay.find( '.mw-echo-overlay-title a' ).eq( 0 ).hasClass( 'mw-ui-quiet' ),
			true, 'First tab is the selected tab upon opening.' );
		assert.strictEqual( this.$badge.text(),
			'0', 'The label updates to 0 as alerts tab is default and now alerts have been read.' );
		assert.strictEqual( this.$badge.hasClass( 'mw-echo-unseen-notifications' ),
			false, 'The notification button class is updated with the default switch to alert tab.' );
	} );

	QUnit.test( 'Switch tabs on overlay. 1 unread alert, no unread messages.', function ( assert ) {
		var $overlay, $tabs;

		this.sandbox.stub( mw.echo.overlay, 'api', new this.ApiStub( 'no-new-messages' ) );
		mw.echo.overlay.buildOverlay( function ( $o ) {
			$overlay = $o;
			// switch to 1st tab (alerts)
			$overlay.find( '.mw-echo-overlay-title li a' ).eq( 0 ).trigger( 'click' );
		} );

		$tabs = $overlay.find( '.mw-echo-overlay-title li a' );

		assert.strictEqual( $tabs.eq( 0 ).hasClass( 'mw-ui-quiet' ),
			true, 'First tab is now the selected tab.' );
		assert.strictEqual( $tabs.eq( 1 ).hasClass( 'mw-ui-quiet' ),
			false, 'Second tab is not the selected tab.' );
		assert.strictEqual( this.$badge.text(),
			'0', 'The label is now set to 0.' );
		assert.strictEqual( this.$badge.hasClass( 'mw-echo-unseen-notifications' ),
			false, 'There are now zero unread notifications.' );

		assert.strictEqual( $tabs.eq( 0 ).text(), 'Alerts (0)', 'Check the label has a count in it.' );
		assert.strictEqual( $tabs.eq( 1 ).text(), 'Messages (0)', 'Check the label has an updated count in it.' );
		assert.strictEqual( $tabs.eq( 1 ).hasClass( 'mw-ui-active' ),
			true, 'Second tab has active class .as it is the only clickable tab' );
	} );

	QUnit.test( 'Unread message behaviour', function ( assert ) {
		var $overlay;

		this.sandbox.stub( mw.echo.overlay, 'api', new this.ApiStub( 'with-new-messages' ) );
		mw.echo.overlay.buildOverlay( function ( $o ) {
			$overlay = $o;
		} );

		// Test initial state
		assert.strictEqual( $overlay.find( '.mw-echo-overlay-title li a' ).eq( 1 ).text(), 'Messages (7)',
			'Check the label has a count in it and it is not automatically reset when tab is open.' );
		assert.strictEqual( $overlay.find( '.mw-echo-unread' ).length, 8, 'There are 8 unread notifications.' );

		// Click mark as read
		$overlay.find( '.mw-echo-notifications > button' ).eq( 0 ).trigger( 'click' );
		assert.strictEqual( $overlay.find( '.mw-echo-overlay-title li a' ).eq( 1 ).text(), 'Messages (0)',
			'Check all the notifications (even those outside overlay) have been marked as read.' );
		assert.strictEqual( $overlay.find( '.mw-echo-notifications ' ).eq( 1 ).find( '.mw-echo-unread' ).length,
			0, 'There are now no unread notifications in this tab.' );
		assert.strictEqual( $overlay.find( '.mw-echo-notifications > button' ).length, 0,
			'There are no notifications now so no need for button.' );
	} );

	QUnit.test( 'Mark as read.', function ( assert ) {
		var $overlay;
		this.$badge.text( '8' );
		this.sandbox.stub( mw.echo.overlay, 'api', new this.ApiStub( 'with-new-messages' ) );
		mw.echo.overlay.buildOverlay( function ( $o ) {
			$overlay = $o;
		} );

		// Test initial state
		assert.strictEqual( $overlay.find( '.mw-echo-overlay-title li a' ).eq( 1 ).text(), 'Messages (7)',
			'Check the label has a count in it and it is not automatically reset when tab is open.' );
		assert.strictEqual( $overlay.find( '.mw-echo-unread' ).length, 8,
			'There are 7 unread message notifications and although the alert is marked as read on server is displays as unread in overlay.' );
		assert.strictEqual( this.$badge.text(), '7', '7 unread notifications in badge (alerts get marked automatically).' );
		assert.strictEqual( $overlay.find( '.mw-echo-notifications li button' ).length, 7,
			'There are 7 mark as read button.' );

		// Click first mark as read
		$overlay.find( '.mw-echo-notifications li button' ).eq( 0 ).trigger( 'click' );

		assert.strictEqual( $overlay.find( '.mw-echo-overlay-title li a' ).eq( 1 ).text(), 'Messages (6)',
			'Check the notification was marked as read.' );
		assert.strictEqual( $overlay.find( '.mw-echo-unread' ).length, 7,
			'There are now 6 unread message notifications in UI and 1 unread alert.' );
		assert.strictEqual( $overlay.find( '.mw-echo-notifications li button' ).length, 6,
			'There are now 6 mark as read buttons.' );
		assert.strictEqual( this.$badge.text(), '6', 'Now 6 unread notifications.' );
	} );

	QUnit.test( 'Tabs when there is overflow.', function ( assert ) {
		var $overlay;
		this.sandbox.stub( mw.echo.overlay, 'api', new this.ApiStub( 'with-new-messages', 50 ) );
		mw.echo.overlay.buildOverlay( function ( $o ) {
			$overlay = $o;
		} );

		// Test initial state
		assert.strictEqual( $overlay.find( '.mw-echo-overlay-title li a' ).eq( 1 ).text(), 'Messages (50)',
			'Check the label has a count in it and reflects the total unread and not the shown unread' );
		assert.strictEqual( $overlay.find( '.mw-echo-unread' ).length, 8, 'There are 8 unread notifications.' );
	} );

	QUnit.test( 'Switching tabs visibility', function ( assert ) {
		var $overlay;

		this.sandbox.stub( mw.echo.overlay, 'api', new this.ApiStub( 'with-new-messages' ) );
		mw.echo.overlay.buildOverlay( function ( $o ) {
			// put in dom so we can do visibility tests
			$overlay = $o.appendTo( '#qunit-fixture' );
		} );

		// Test initial state
		assert.strictEqual( $overlay.find( '.mw-echo-notifications' ).eq( 0 ).is( ':visible' ),
			true, 'First tab (alerts) starts visible.' );
		assert.strictEqual( $overlay.find( '.mw-echo-notifications' ).eq( 1 ).is( ':visible' ),
			false, 'Second tab (messages) starts hidden.' );

		// Switch to second tab
		$overlay.find( '.mw-echo-overlay-title li a' ).eq( 1 ).trigger( 'click' );

		// check new tab visibility
		assert.strictEqual( $overlay.find( '.mw-echo-notifications' ).eq( 0 ).is( ':visible' ),
			false, 'First tab is now hidden.' );
		assert.strictEqual( $overlay.find( '.mw-echo-notifications' ).eq( 1 ).is( ':visible' ),
			true, 'Second tab is now visible.' );
	} );
}() );
