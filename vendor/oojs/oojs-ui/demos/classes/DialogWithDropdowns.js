Demo.DialogWithDropdowns = function DemoDialogWithDropdowns( config ) {
	Demo.DialogWithDropdowns.parent.call( this, config );
};
OO.inheritClass( Demo.DialogWithDropdowns, OO.ui.ProcessDialog );
Demo.DialogWithDropdowns.static.title = 'Dialog with dropdowns ($overlay test)';
Demo.DialogWithDropdowns.static.actions = [
	{ action: 'save', label: 'Done', flags: [ 'primary', 'progressive' ] },
	{ action: 'cancel', label: 'Cancel', flags: [ 'safe', 'back' ] }
];
Demo.DialogWithDropdowns.prototype.getBodyHeight = function () {
	return 300;
};
Demo.DialogWithDropdowns.prototype.initialize = function () {
	var
		loremIpsum = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, ' +
			'sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.\u200E',
		$spacer = $( '<div>' ).height( 350 );
	Demo.DialogWithDropdowns.parent.prototype.initialize.apply( this, arguments );
	this.bookletLayout = new OO.ui.BookletLayout( {
		outlined: true
	} );
	this.pages = [
		new Demo.SamplePage( 'info', {
			label: 'Information',
			icon: 'info',
			content: [
				'This is a test of various kinds of dropdown menus and their $overlay config option. ',
				'Entries without any icon use a correctly set $overlay and their menus should be able to extend outside of this small dialog. ',
				'Entries with the ', new OO.ui.IconWidget( { icon: 'alert' } ), ' icon do not, and their menus should be clipped to the dialog\'s boundaries. ',
				'All dropdown menus should stick to the widget proper, even when scrolling while the menu is open.'
			]
		} ),
		new Demo.SamplePage( 'dropdown', {
			label: 'DropdownWidget',
			content: [ $spacer.clone(), new OO.ui.DropdownWidget( {
				$overlay: this.$overlay,
				menu: {
					items: this.makeItems()
				}
			} ), $spacer.clone() ]
		} ),
		new Demo.SamplePage( 'dropdown2', {
			label: 'DropdownWidget',
			icon: 'alert',
			content: [ $spacer.clone(), new OO.ui.DropdownWidget( {
				menu: {
					items: this.makeItems()
				}
			} ), $spacer.clone() ]
		} ),
		new Demo.SamplePage( 'combobox', {
			label: 'ComboBoxInputWidget',
			content: [ $spacer.clone(), new OO.ui.ComboBoxInputWidget( {
				$overlay: this.$overlay,
				menu: {
					items: this.makeItems()
				}
			} ), $spacer.clone() ]
		} ),
		new Demo.SamplePage( 'combobox2', {
			label: 'ComboBoxInputWidget',
			icon: 'alert',
			content: [ $spacer.clone(), new OO.ui.ComboBoxInputWidget( {
				menu: {
					items: this.makeItems()
				}
			} ), $spacer.clone() ]
		} ),
		new Demo.SamplePage( 'lookup', {
			label: 'LookupElement',
			content: [ $spacer.clone(), new Demo.NumberLookupTextInputWidget( {
				$overlay: this.$overlay
			} ), $spacer.clone() ]
		} ),
		new Demo.SamplePage( 'lookup2', {
			label: 'LookupElement',
			icon: 'alert',
			content: [ $spacer.clone(), new Demo.NumberLookupTextInputWidget(), $spacer.clone() ]
		} ),
		new Demo.SamplePage( 'fieldsetandfield', {
			label: 'FieldsetLayout and FieldLayout',
			content: [ $spacer.clone(), new OO.ui.FieldsetLayout( {
				$overlay: this.$overlay,
				label: 'Fieldset',
				help: loremIpsum,
				items: [
					new OO.ui.FieldLayout( new OO.ui.CheckboxInputWidget(), {
						$overlay: this.$overlay,
						align: 'inline',
						label: 'Field',
						help: loremIpsum
					} )
				]
			} ), $spacer.clone() ]
		} ),
		new Demo.SamplePage( 'fieldsetandfield2', {
			label: 'FieldsetLayout and FieldLayout',
			icon: 'alert',
			content: [ $spacer.clone(), new OO.ui.FieldsetLayout( {
				label: 'Fieldset',
				help: loremIpsum,
				items: [
					new OO.ui.FieldLayout( new OO.ui.CheckboxInputWidget(), {
						align: 'inline',
						label: 'Field',
						help: loremIpsum
					} )
				]
			} ), $spacer.clone() ]
		} ),
		new Demo.SamplePage( 'popupbutton', {
			label: 'PopupButtonWidget',
			content: [ $spacer.clone(), new OO.ui.PopupButtonWidget( {
				$overlay: this.$overlay,
				label: 'Popup button',
				popup: {
					padded: true,
					$content: this.makeContents()
				}
			} ), $spacer.clone() ]
		} ),
		new Demo.SamplePage( 'popupbutton2', {
			label: 'PopupButtonWidget',
			icon: 'alert',
			content: [ $spacer.clone(), new OO.ui.PopupButtonWidget( {
				label: 'Popup button',
				popup: {
					padded: true,
					$content: this.makeContents()
				}
			} ), $spacer.clone() ]
		} ),
		new Demo.SamplePage( 'popupbuttonhoriz', {
			label: 'PopupButtonWidget (horizontal)',
			content: [ $spacer.clone(), new OO.ui.PopupButtonWidget( {
				$overlay: this.$overlay,
				label: 'Popup button',
				popup: {
					position: 'after',
					height: 200,
					width: 200,
					padded: true,
					$content: this.makeContents()
				}
			} ), $spacer.clone() ]
		} ),
		new Demo.SamplePage( 'popupbuttonhoriz2', {
			label: 'PopupButtonWidget (horizontal)',
			icon: 'alert',
			content: [ $spacer.clone(), new OO.ui.PopupButtonWidget( {
				label: 'Popup button',
				popup: {
					position: 'after',
					height: 200,
					width: 200,
					padded: true,
					$content: this.makeContents()
				}
			} ), $spacer.clone() ]
		} ),
		new Demo.SamplePage( 'capsulemenu', {
			label: 'CapsuleMultiselectWidget (menu)',
			content: [ $spacer.clone(), new OO.ui.CapsuleMultiselectWidget( {
				$overlay: this.$overlay,
				menu: {
					items: this.makeItems()
				}
			} ), $spacer.clone() ]
		} ),
		new Demo.SamplePage( 'capsulemenu2', {
			label: 'CapsuleMultiselectWidget (menu)',
			icon: 'alert',
			content: [ $spacer.clone(), new OO.ui.CapsuleMultiselectWidget( {
				menu: {
					items: this.makeItems()
				}
			} ), $spacer.clone() ]
		} ),
		new Demo.SamplePage( 'capsulepopup', {
			label: 'CapsuleMultiselectWidget (popup)',
			content: [ $spacer.clone(), new Demo.CapsuleNumberPopupMultiselectWidget( {
				$overlay: this.$overlay
			} ), $spacer.clone() ]
		} ),
		new Demo.SamplePage( 'capsulepopup2', {
			label: 'CapsuleMultiselectWidget (popup)',
			icon: 'alert',
			content: [ $spacer.clone(), new Demo.CapsuleNumberPopupMultiselectWidget(), $spacer.clone() ]
		} )
	];
	this.bookletLayout.on( 'set', function ( page ) {
		page.$element[ 0 ].scrollTop = 325;
	} );
	this.bookletLayout.addPages( this.pages );
	this.$body.append( this.bookletLayout.$element );
};
Demo.DialogWithDropdowns.prototype.makeItems = function () {
	return [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9 ].map( function ( val ) {
		return new OO.ui.MenuOptionWidget( {
			data: val,
			label: String( val )
		} );
	} );
};
Demo.DialogWithDropdowns.prototype.makeContents = function () {
	var loremIpsum = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, ' +
		'sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.\u200E';
	return $( '<p>' ).text( loremIpsum );
};

Demo.DialogWithDropdowns.prototype.getActionProcess = function ( action ) {
	if ( action ) {
		return new OO.ui.Process( function () {
			this.close( { action: action } );
		}, this );
	}
	return Demo.DialogWithDropdowns.parent.prototype.getActionProcess.call( this, action );
};
