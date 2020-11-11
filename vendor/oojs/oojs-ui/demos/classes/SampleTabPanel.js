Demo.SampleTabPanel = function DemoSampleTabPanel( name, config ) {
	OO.ui.TabPanelLayout.call( this, name, config );
	if ( this.$element.is( ':empty' ) ) {
		this.$element.text( this.label );
	}
};
OO.inheritClass( Demo.SampleTabPanel, OO.ui.TabPanelLayout );
