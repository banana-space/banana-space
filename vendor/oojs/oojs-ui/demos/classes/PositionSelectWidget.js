Demo.PositionSelectWidget = function DemoPositionSelectWidget( config ) {
	var verticalPositions, horizontalPositions, $table,
		widget = this;

	Demo.PositionSelectWidget.parent.call( this, config );

	verticalPositions = [ 'above', 'top', 'center', 'bottom', 'below' ];
	horizontalPositions = [ 'before', 'start', 'center', 'end', 'after' ];

	$table = $( '<table>' );
	verticalPositions.forEach( function ( v ) {
		var $tr = $( '<tr>' );
		horizontalPositions.forEach( function ( h ) {
			var $td = $( '<td>' );
			$td.append( widget.getOption( h, v ).$element );
			$td.attr( 'title', v + '/' + h );
			$tr.append( $td );
		} );
		$table.append( $tr );
	} );

	this.$element.append( $table );
	this.$element.addClass( 'demo-positionSelectWidget' );
};
OO.inheritClass( Demo.PositionSelectWidget, OO.ui.RadioSelectWidget );
Demo.PositionSelectWidget.prototype.getOption = function ( h, v ) {
	var option = new OO.ui.RadioOptionWidget( {
		data: {
			horizontalPosition: h,
			verticalPosition: v
		}
	} );
	this.addItems( [ option ] );
	return option;
};
