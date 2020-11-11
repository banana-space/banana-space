Demo.UnsupportedSelectFileWidget = function DemoUnsupportedSelectFileWidget() {
	// Parent constructor
	Demo.UnsupportedSelectFileWidget.parent.apply( this, arguments );
};
OO.inheritClass( Demo.UnsupportedSelectFileWidget, OO.ui.SelectFileWidget );
Demo.UnsupportedSelectFileWidget.static.isSupported = function () {
	return false;
};
