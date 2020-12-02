/* global QRCode, QRErrorCorrectLevel */

( function () {
	$.fn.qrcode = function ( options ) {
		var createCanvas, createTable;

		// if options is string,
		if ( typeof options === 'string' ) {
			options = { text: options };
		}

		// set default values
		// typeNumber < 1 for automatic calculation
		options = $.extend( {}, {
			render: 'canvas',
			width: 256,
			height: 256,
			typeNumber: -1,
			correctLevel: QRErrorCorrectLevel.H,
			background: '#ffffff',
			foreground: '#000000'
		}, options );

		createCanvas = function () {
			var qrcode, canvas, ctx, tileW, tileH, row, col, w, h;

			// create the qrcode itself
			qrcode = new QRCode( options.typeNumber, options.correctLevel );
			qrcode.addData( options.text );
			qrcode.make();

			// create canvas element
			canvas = document.createElement( 'canvas' );
			canvas.width = options.width;
			canvas.height = options.height;
			ctx = canvas.getContext( '2d' );

			// compute tileW/tileH based on options.width/options.height
			tileW = options.width / qrcode.getModuleCount();
			tileH = options.height / qrcode.getModuleCount();

			// draw in the canvas
			for ( row = 0; row < qrcode.getModuleCount(); row++ ) {
				for ( col = 0; col < qrcode.getModuleCount(); col++ ) {
					ctx.fillStyle = qrcode.isDark( row, col ) ?
						options.foreground :
						options.background;

					w = ( Math.ceil( ( col + 1 ) * tileW ) - Math.floor( col * tileW ) );
					h = ( Math.ceil( ( row + 1 ) * tileW ) - Math.floor( row * tileW ) );
					ctx.fillRect( Math.round( col * tileW ), Math.round( row * tileH ), w, h );
				}
			}
			// return just built canvas
			return canvas;
		};

		// from Jon-Carlos Rivera (https://github.com/imbcmdth)
		createTable = function () {
			var qrcode, $table, tileW, tileH, row, col, $row;

			// create the qrcode itself
			qrcode = new QRCode( options.typeNumber, options.correctLevel );
			qrcode.addData( options.text );
			qrcode.make();

			// create table element
			$table = $( '<table>' )
				.css( 'width', options.width + 'px' )
				.css( 'height', options.height + 'px' )
				.css( 'border', '0' )
				.css( 'border-collapse', 'collapse' )
				.css( 'background-color', options.background );

			// compute tileS percentage
			tileW = options.width / qrcode.getModuleCount();
			tileH = options.height / qrcode.getModuleCount();

			// draw in the table
			for ( row = 0; row < qrcode.getModuleCount(); row++ ) {
				$row = $( '<tr>' ).css( 'height', tileH + 'px' ).appendTo( $table );

				for ( col = 0; col < qrcode.getModuleCount(); col++ ) {
					$( '<td>' )
						.css( 'width', tileW + 'px' )
						.css( 'background-color', qrcode.isDark( row, col ) ? options.foreground : options.background )
						.appendTo( $row );
				}
			}
			// return just built canvas
			return $table;
		};

		return this.each( function () {
			var element = options.render === 'canvas' ? createCanvas() : createTable();
			$( element ).appendTo( this );
		} );
	};
}() );
