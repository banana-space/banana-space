Demo.BookletDialog = function DemoBookletDialog( config ) {
	Demo.BookletDialog.parent.call( this, config );
};
OO.inheritClass( Demo.BookletDialog, OO.ui.ProcessDialog );
Demo.BookletDialog.static.title = 'Booklet dialog';
Demo.BookletDialog.static.actions = [
	{ action: 'save', label: 'Done', flags: [ 'primary', 'progressive' ] },
	{ action: 'cancel', label: 'Cancel', flags: [ 'safe', 'back' ] }
];
Demo.BookletDialog.prototype.getBodyHeight = function () {
	return 250;
};
Demo.BookletDialog.prototype.initialize = function () {
	var dialog;
	Demo.BookletDialog.parent.prototype.initialize.apply( this, arguments );

	dialog = this;

	function changePage( direction ) {
		var pageIndex = dialog.pages.indexOf( dialog.bookletLayout.getCurrentPage() );
		pageIndex = ( dialog.pages.length + pageIndex + direction ) % dialog.pages.length;
		dialog.bookletLayout.setPage( dialog.pages[ pageIndex ].getName() );
	}

	this.navigationField = new OO.ui.FieldLayout(
		new OO.ui.ButtonGroupWidget( {
			items: [
				new OO.ui.ButtonWidget( {
					data: 'previous',
					icon: 'previous'
				} ).on( 'click', function () {
					changePage( -1 );
				} ),
				new OO.ui.ButtonWidget( {
					data: 'next',
					icon: 'next'
				} ).on( 'click', function () {
					changePage( 1 );
				} )
			]
		} ),
		{
			label: 'Change page',
			align: 'top'
		}
	);

	this.bookletLayout = new OO.ui.BookletLayout();
	this.pages = [
		new Demo.SamplePage( 'page-1', { label: 'Page 1', icon: 'window' } ),
		new Demo.SamplePage( 'page-2', { label: 'Page 2', icon: 'window' } ),
		new Demo.SamplePage( 'page-3', { label: 'Page 3', icon: 'window' } )
	];
	this.bookletLayout.addPages( this.pages );
	this.bookletLayout.connect( this, { set: 'onBookletLayoutSet' } );
	this.bookletLayout.setPage( 'page-1' );

	this.$body.append( this.bookletLayout.$element );
};
Demo.BookletDialog.prototype.getActionProcess = function ( action ) {
	if ( action ) {
		return new OO.ui.Process( function () {
			this.close( { action: action } );
		}, this );
	}
	return Demo.BookletDialog.parent.prototype.getActionProcess.call( this, action );
};
Demo.BookletDialog.prototype.onBookletLayoutSet = function ( page ) {
	page.$element.append( this.navigationField.$element );
};
