Demo.ContinuousOutlinedBookletDialog = function DemoContinuousOutlinedBookletDialog( config ) {
	Demo.ContinuousOutlinedBookletDialog.parent.call( this, config );
};
OO.inheritClass( Demo.ContinuousOutlinedBookletDialog, OO.ui.ProcessDialog );
Demo.ContinuousOutlinedBookletDialog.static.title = 'Continuous outlined booklet dialog';
Demo.ContinuousOutlinedBookletDialog.static.actions = [
	{ action: 'save', label: 'Done', flags: [ 'primary', 'progressive' ] },
	{ action: 'cancel', label: 'Cancel', flags: [ 'safe', 'back' ] }
];
Demo.ContinuousOutlinedBookletDialog.prototype.getBodyHeight = function () {
	return 250;
};
Demo.ContinuousOutlinedBookletDialog.prototype.initialize = function () {
	var lipsum;
	Demo.ContinuousOutlinedBookletDialog.parent.prototype.initialize.apply( this, arguments );
	this.bookletLayout = new OO.ui.BookletLayout( {
		outlined: true,
		continuous: true
	} );
	lipsum = [
		'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Quisque eleifend justo nec erat tempus, quis aliquet augue aliquam. Sed rutrum odio in tellus pharetra, ut mollis est fermentum. ' +
			'Sed egestas dolor libero, a aliquet sem finibus eu. Morbi dolor nisl, pulvinar vitae maximus sed, lacinia eu ipsum. Fusce rutrum placerat massa, vel vehicula nisi viverra nec. ' +
			'Nam at turpis vel nisi efficitur tempor. Interdum et malesuada fames ac ante ipsum primis in faucibus. Morbi aliquam pulvinar fermentum. Maecenas rutrum accumsan lorem ac sagittis. ' +
			'Praesent id nunc gravida, iaculis odio eu, maximus ligula. Praesent ut tellus mollis, pharetra orci vitae, interdum lacus. Nulla sodales lacus eget libero pellentesque tempor.',
		'Ut a metus elementum, eleifend velit et, malesuada enim.',
		'Aenean sem eros, rutrum vitae pulvinar at, vulputate id quam. Quisque tincidunt, ligula pulvinar consequat tempor, tellus erat lobortis nisl, non euismod diam nisl ut libero. Etiam mollis, ' +
			'risus a tincidunt efficitur, ipsum justo ullamcorper sem, id gravida dui lacus quis turpis. In consectetur tincidunt elit in mollis. Sed nec ultricies turpis, at dictum risus. Curabitur ipsum diam, ' +
			'aliquet sit amet ante eu, congue cursus magna. Donec at lectus in nulla ornare faucibus. Vestibulum mattis massa eu convallis convallis. Sed tristique ut quam non eleifend. Nunc aliquam, nisi non ' +
			'posuere dictum, est nunc mollis nisl.',
		'Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Fusce laoreet mi mi, nec tempor erat posuere malesuada. Nam dignissim at nisl ac aliquet. In fermentum ' +
			'mauris et tellus fermentum rutrum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Aliquam hendrerit diam mauris, id rutrum justo malesuada nec. Duis ',
		'Ut fringilla enim nec augue rutrum, nec vestibulum orci sollicitudin. Donec eget ex tincidunt augue ullamcorper efficitur at sed odio. Praesent ac interdum elit. Suspendisse blandit feugiat pulvinar. '
	];
	this.pages = [
		new Demo.SamplePage( 'page1', { label: 'Level 0', icon: 'window', level: 0, content: [ $( '<h3>' ).text( 'Page 1' ), lipsum[ 0 ] ] } ),
		new Demo.SamplePage( 'page2', { label: 'Level 1', icon: 'window', level: 1, content: [ $( '<h3>' ).text( 'Page 2' ), lipsum[ 1 ] ] } ),
		new Demo.SamplePage( 'page3', { label: 'Level 2', icon: 'window', level: 2, content: [ $( '<h3>' ).text( 'Page 3' ), lipsum[ 2 ] ] } ),
		new Demo.SamplePage( 'page4', { label: 'Level 1', icon: 'window', level: 1, content: [ $( '<h3>' ).text( 'Page 4' ), lipsum[ 3 ] ] } ),
		new Demo.SamplePage( 'page5', { label: 'Level 2', icon: 'window', level: 2, content: [ $( '<h3>' ).text( 'Page 5' ), lipsum[ 4 ] ] } )
	];

	this.bookletLayout.addPages( this.pages );
	this.$body.append( this.bookletLayout.$element );
};
Demo.ContinuousOutlinedBookletDialog.prototype.getActionProcess = function ( action ) {
	if ( action ) {
		return new OO.ui.Process( function () {
			this.close( { action: action } );
		}, this );
	}
	return Demo.ContinuousOutlinedBookletDialog.parent.prototype.getActionProcess.call( this, action );
};
Demo.ContinuousOutlinedBookletDialog.prototype.getSetupProcess = function ( data ) {
	return Demo.ContinuousOutlinedBookletDialog.parent.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.bookletLayout.setPage( 'page1' );
		}, this );
};
