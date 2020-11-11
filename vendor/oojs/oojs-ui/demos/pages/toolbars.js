Demo.static.pages.toolbars = function ( demo ) {
	var i, toolGroups, actionButton, actionButtonDelete, actionButtonDisabled, actionGroup, publishButton, AlertTool, PopupTool, ToolGroupTool,
		setDisabled = function () { this.setDisabled( true ); },
		$demo = demo.$element,
		$containers = $(),
		toolFactories = [],
		toolGroupFactories = [],
		toolbars = [],
		configs = [
			{},
			{ actions: true },
			{},
			{ actions: true },
			{ position: 'bottom' },
			{ actions: true, position: 'bottom' },
			{},
			{ actions: true }
		];

	// Show some random accelerator keys that don't actually work
	function getToolAccelerator( name ) {
		return {
			listTool1: 'Ctrl+Shift+1',
			listTool2: 'Ctrl+Alt+2',
			listTool3: 'Cmd+Enter',
			listTool5: 'Shift+Down',
			menuTool: 'Ctrl+M'
		}[ name ];
	}

	for ( i = 0; i <= 7; i++ ) {
		toolFactories.push( new OO.ui.ToolFactory() );
		toolGroupFactories.push( new OO.ui.ToolGroupFactory() );
		toolbars.push( new OO.ui.Toolbar( toolFactories[ i ], toolGroupFactories[ i ], configs[ i ] ) );
		toolbars[ i ].getToolAccelerator = getToolAccelerator;
	}

	function createTool( toolbar, group, name, icon, title, init, onSelect, displayBothIconAndLabel ) {
		var Tool = function () {
			Tool.parent.apply( this, arguments );
			this.toggled = false;
			if ( init ) {
				init.call( this );
			}
		};

		OO.inheritClass( Tool, OO.ui.Tool );

		Tool.prototype.onSelect = function () {
			if ( onSelect ) {
				onSelect.call( this );
			} else {
				this.toggled = !this.toggled;
				this.setActive( this.toggled );
			}
			toolbars[ toolbar ].emit( 'updateState' );
		};
		Tool.prototype.onUpdateState = function () {};

		Tool.static.name = name;
		Tool.static.group = group;
		Tool.static.icon = icon;
		Tool.static.title = title;
		Tool.static.displayBothIconAndLabel = !!displayBothIconAndLabel;
		return Tool;
	}

	function createToolGroup( toolbar, group ) {
		$.each( toolGroups[ group ], function ( i, tool ) {
			var args = tool.slice();
			args.splice( 0, 0, toolbar, group );
			toolFactories[ toolbar ].register( createTool.apply( null, args ) );
		} );
	}

	function createDisabledToolGroup( parent, name ) {
		var DisabledToolGroup = function () {
			DisabledToolGroup.parent.apply( this, arguments );
			this.setDisabled( true );
		};

		OO.inheritClass( DisabledToolGroup, parent );

		DisabledToolGroup.static.name = name;

		DisabledToolGroup.prototype.onUpdateState = function () {
			this.setLabel( 'Disabled' );
		};

		return DisabledToolGroup;
	}

	toolGroupFactories[ 0 ].register( createDisabledToolGroup( OO.ui.BarToolGroup, 'disabledBar' ) );
	toolGroupFactories[ 0 ].register( createDisabledToolGroup( OO.ui.ListToolGroup, 'disabledList' ) );
	toolGroupFactories[ 1 ].register( createDisabledToolGroup( OO.ui.MenuToolGroup, 'disabledMenu' ) );

	AlertTool = function ( toolGroup, config ) {
		// Parent constructor
		OO.ui.PopupTool.call( this, toolGroup, $.extend( { popup: {
			padded: true,
			label: 'Alert head',
			head: true
		} }, config ) );

		this.popup.$body.append( '<p>Alert contents</p>' );
	};

	OO.inheritClass( AlertTool, OO.ui.PopupTool );

	AlertTool.static.name = 'alertTool';
	AlertTool.static.group = 'popupTools';
	AlertTool.static.icon = 'alert';

	toolFactories[ 2 ].register( AlertTool );
	toolFactories[ 4 ].register( AlertTool );

	PopupTool = function ( toolGroup, config ) {
		// Parent constructor
		OO.ui.PopupTool.call( this, toolGroup, $.extend( { popup: {
			padded: true,
			label: 'Popup head',
			head: true
		} }, config ) );

		this.popup.$body.append( '<p>Popup contents</p>' );
	};

	OO.inheritClass( PopupTool, OO.ui.PopupTool );

	PopupTool.static.name = 'popupTool';
	PopupTool.static.group = 'popupTools';
	PopupTool.static.icon = 'help';

	toolFactories[ 2 ].register( PopupTool );
	toolFactories[ 4 ].register( PopupTool );

	ToolGroupTool = function ( toolGroup, config ) {
		// Parent constructor
		OO.ui.ToolGroupTool.call( this, toolGroup, config );
	};

	OO.inheritClass( ToolGroupTool, OO.ui.ToolGroupTool );

	ToolGroupTool.static.name = 'toolGroupTool';
	ToolGroupTool.static.group = 'barTools';
	ToolGroupTool.static.groupConfig = {
		label: 'More',
		include: [ { group: 'moreListTools' } ]
	};

	toolFactories[ 0 ].register( ToolGroupTool );
	toolFactories[ 3 ].register( ToolGroupTool );
	toolFactories[ 5 ].register( ToolGroupTool );

	// Toolbars setup, in order of toolbar items appearance
	// Toolbar
	toolbars[ 0 ].setup( [
		{
			type: 'bar',
			include: [ { group: 'barTools' } ],
			demote: [ 'toolGroupTool' ]
		},
		{
			type: 'disabledBar',
			include: [ { group: 'disabledBarTools' } ]
		},
		{
			type: 'list',
			label: 'List',
			icon: 'image',
			include: [ { group: 'listTools' } ],
			allowCollapse: [ 'listTool1', 'listTool6' ]
		},
		{
			type: 'disabledList',
			label: 'List',
			icon: 'image',
			include: [ { group: 'disabledListTools' } ]
		},
		{
			type: 'list',
			label: 'Auto-disabling list',
			icon: 'image',
			include: [ { group: 'autoDisableListTools' } ]
		},
		{
			label: 'Catch-all',
			include: '*'
		}
	] );
	// Toolbar with action buttons
	toolbars[ 1 ].setup( [
		{
			type: 'menu',
			header: 'Popup-/MenuToolGroup header',
			icon: 'image',
			include: [ { group: 'menuTools' } ]
		},
		{
			type: 'disabledMenu',
			icon: 'image',
			include: [ { group: 'disabledMenuTools' } ]
		},
		{
			type: 'bar',
			include: [ { group: 'cite' } ]
		},
		{
			type: 'bar',
			include: [ { group: 'citeDisabled' } ]
		}
	] );
	// Action toolbar for toolbars[ 3 ] below
	toolbars[ 2 ].setup( [
		{
			include: [ { group: 'popupTools' } ]
		},
		{
			type: 'list',
			icon: 'menu',
			indicator: '',
			include: [ { group: 'overflowTools' } ]
		},
		{
			type: 'list',
			icon: 'edit',
			include: [ { group: 'editorSwitchTools' } ]
		}
	] );
	// Word processor toolbar
	toolbars[ 3 ].setup( [
		{
			type: 'bar',
			include: [ { group: 'history' } ]
		},
		{
			type: 'menu',
			include: [ { group: 'formatTools' } ]
		},
		{
			type: 'list',
			icon: 'textStyle',
			include: [ { group: 'styleTools' } ]
		},
		{
			type: 'bar',
			include: [ { group: 'link' } ]
		},
		{
			type: 'bar',
			include: [ { group: 'cite' } ]
		},
		{
			type: 'bar',
			include: [ { group: 'citeDisabled' } ]
		},
		{
			type: 'list',
			icon: 'listBullet',
			include: [ { group: 'structureTools' } ]
		},
		{
			type: 'list',
			label: 'Insert',
			include: [ { group: 'insertTools' }, { group: 'autoDisableListTools' }, { group: 'unusedStuff' } ],
			allowCollapse: [ 'comment', 'hieroglyphs', 'score', 'signature', 'gallery', 'chem', 'math', 'syntaxHighlightDialog', 'graph', 'referencesList' ]
		},
		{
			type: 'bar',
			include: [ { group: 'specialCharacters' } ]
		}
	] );
	// Action toolbar for toolbars[ 5 ] below
	toolbars[ 4 ].setup( [
		{
			include: [ { group: 'popupTools' } ]
		},
		{
			include: [ { group: 'alertTools' } ]
		},
		{
			type: 'list',
			icon: 'menu',
			indicator: '',
			include: [ { group: 'overflowTools' } ]
		},
		{
			type: 'list',
			icon: 'edit',
			include: [ { group: 'editorSwitchTools' } ]
		}
	] );
	// Word processor toolbar set to `position: 'bottom'`
	toolbars[ 5 ].setup( [
		{
			type: 'bar',
			include: [ { group: 'history' } ]
		},
		{
			type: 'menu',
			include: [ { group: 'formatTools' } ]
		},
		{
			type: 'list',
			icon: 'textStyle',
			include: [ { group: 'styleTools' } ]
		},
		{
			type: 'bar',
			include: [ { group: 'link' } ]
		},
		{
			type: 'bar',
			include: [ { group: 'cite' } ]
		},
		{
			type: 'bar',
			include: [ { group: 'citeDisabled' } ]
		},
		{
			type: 'list',
			icon: 'listBullet',
			include: [ { group: 'structureTools' } ]
		},
		{
			type: 'list',
			label: 'Insert',
			include: [ { group: 'insertTools' }, { group: 'autoDisableListTools' }, { group: 'unusedStuff' } ]
		},
		{
			type: 'bar',
			include: [ { group: 'specialCharacters' } ]
		}
	] );
	// Action toolbar for toolbars[7]
	toolbars[ 6 ].setup( [
		{
			type: 'list',
			indicator: 'down',
			flags: [ 'primary', 'progressive' ],
			include: [ { group: 'listTools' } ]
		}
	] );
	// Toolbar with action buttons, in a ButtonGroup
	toolbars[ 7 ].setup( [
		{
			type: 'menu',
			icon: 'image',
			include: [ { group: 'menuTools' } ]
		},
		{
			type: 'disabledMenu',
			icon: 'image',
			include: [ { group: 'disabledMenuTools' } ]
		}
	] );

	actionButton = new OO.ui.ButtonWidget( { label: 'Action' } );
	actionButtonDisabled = new OO.ui.ButtonWidget( { label: 'Disabled', disabled: true } );
	toolbars[ 1 ].$actions.append( actionButton.$element, actionButtonDisabled.$element );

	for ( i = 3; i <= 5; i += 2 ) {
		publishButton = new OO.ui.ButtonWidget( { label: 'Publish changes', flags: [ 'progressive', 'primary' ] } );
		toolbars[ i ].$actions.append( toolbars[ i - 1 ].$element, publishButton.$element );
	}

	actionButtonDelete = new OO.ui.ButtonWidget( { label: 'Delete', flags: [ 'destructive' ] } );
	publishButton = new OO.ui.ButtonWidget( { label: 'Publish changes', flags: [ 'progressive', 'primary' ] } );
	actionGroup = new OO.ui.ButtonGroupWidget( {
		items: [ actionButtonDelete, publishButton, toolbars[ 6 ].items[ 0 ] ]
	} );
	toolbars[ 7 ].$actions.append( actionGroup.$element );

	for ( i = 0; i < toolbars.length; i++ ) {
		toolbars[ i ].emit( 'updateState' );
	}

	// ToolGroups definition, in alphabetical/disabledAlphabetical order
	toolGroups = {
		barTools: [
			[ 'barTool', 'image', 'Basic tool in bar' ],
			[ 'disabledBarTool', 'image', 'Basic tool in bar disabled', setDisabled ]
		],

		disabledBarTools: [
			[ 'barToolInDisabled', 'image', 'Basic tool in disabled bar' ]
		],

		cite: [
			[ 'citeTool', 'quotes', 'Cite', null, null, true ]
		],

		citeDisabled: [
			[ 'citeToolDisabled', 'quotes', 'Cite', setDisabled, null, true ]
		],

		editorSwitchTools: [
			[ 'visualEditor', 'eye', 'Visual editing' ],
			[ 'wikitextEditor', 'wikiText', 'Source editing' ]
		],

		formatTools: [
			[ 'paragraph', null, 'Paragraph' ],
			[ 'heading2', null, 'Heading 2' ],
			[ 'heading3', null, 'Sub-heading 1' ],
			[ 'heading4', null, 'Sub-heading 2' ],
			[ 'heading5', null, 'Sub-heading 3' ],
			[ 'heading6', null, 'Sub-heading 4' ],
			[ 'preformatted', null, 'Preformatted' ],
			[ 'blockquote', null, 'Blockquote' ]
		],

		history: [
			[ 'undoTool', 'undo', 'Undo' ],
			[ 'redoTool', 'redo', 'Redo' ]
		],

		insertTools: [
			[ 'media', 'image', 'First basic tool in list' ],
			[ 'template', 'puzzle', 'Template' ],
			[ 'table', 'table', 'Table' ],
			[ 'comment', 'comment', 'Comment' ],
			[ 'hieroglyphs', 'specialCharacter', 'Hieroglyphs' ],
			[ 'score', 'specialCharacter', 'Musical notation' ],
			[ 'signature', 'signature', 'Your signature' ],
			[ 'gallery', 'imageGallery', 'Gallery' ],
			[ 'chem', 'specialCharacter', 'Chemical formula' ],
			[ 'math', 'specialCharacter', 'Math formula' ],
			[ 'syntaxHighlightDialog', 'markup', 'Code block' ],
			[ 'graph', 'specialCharacter', 'Graph' ],
			[ 'referencesList', 'specialCharacter', 'References list' ]
		],

		link: [
			[ 'linkTool', 'link', 'Link' ]
		],

		listTools: [
			[ 'listTool', 'image', 'First basic tool in list' ],
			[ 'listTool1', 'image', 'Basic tool in list' ],
			[ 'listTool3', 'image', 'Basic disabled tool in list', setDisabled ],
			[ 'listTool6', 'image', 'A final tool' ]
		],

		moreListTools: [
			[ 'listTool2', 'code', 'Another basic tool' ],
			[ 'listTool4', 'image', 'More basic tools' ],
			[ 'listTool5', 'ellipsis', 'And even more' ]
		],

		disabledListTools: [
			[ 'listToolInDisabled', 'image', 'Basic tool in disabled list' ]
		],

		autoDisableListTools: [
			[ 'autoDisableListTool', 'image', 'Click to disable this tool', null, setDisabled ]
		],

		menuTools: [
			[ 'menuTool', 'image', 'Basic tool' ],
			[ 'iconlessMenuTool', null, 'Tool without an icon' ],
			[ 'disabledMenuTool', 'image', 'Basic tool disabled', setDisabled ]
		],

		disabledMenuTools: [
			[ 'menuToolInDisabled', 'image', 'Basic tool' ]
		],

		overflowTools: [
			[ 'meta', 'window', 'Options' ],
			[ 'categories', 'image', 'Categories' ],
			[ 'settings', 'settings', 'Page settings' ],
			[ 'advanced', 'advanced', 'Advanced settings' ],
			[ 'textLanguage', 'language', 'Languages' ],
			[ 'templatesUsed', 'puzzle', 'Templates used' ],
			[ 'codeMirror', 'highlight', 'Syntax highlighting', setDisabled ],
			[ 'changeDirectionality', 'textDirRTL', 'View as right-to-left' ],
			[ 'find', 'articleSearch', 'Find and replace' ]
		],

		specialCharacters: [
			[ 'specialCharacter', 'specialCharacter', 'Special character' ]
		],

		popupTools: [
			[ 'popupTool', 'alertTool' ]
		],

		structureTools: [
			[ 'bullet', 'listBullet', 'Bullet list' ],
			[ 'number', 'listNumbered', 'Numbered list' ],
			[ 'outdent', 'outdent', 'Decrease indentation' ],
			[ 'indent', 'indent', 'Increase indentation' ]
		],

		styleTools: [
			[ 'bold', 'bold', 'Bold' ],
			[ 'italic', 'italic', 'Italic' ],
			[ 'italic', 'italic', 'Italic' ],
			[ 'superscript', 'superscript', 'Superscript' ],
			[ 'subscript', 'subscript', 'Subscript' ],
			[ 'strikethrough', 'strikethrough', 'Strikethrough' ],
			[ 'code', 'code', 'Computer Code' ],
			[ 'underline', 'underline', 'Underline' ],
			[ 'language', 'language', 'Language' ],
			[ 'big', 'bigger', 'Big' ],
			[ 'small', 'smaller', 'Small' ],
			[ 'clear', 'cancel', 'Clear Styling', setDisabled ]
		],

		unusedStuff: [
			[ 'unusedTool', 'help', 'This tool is not explicitly used anywhere' ],
			[ 'unusedTool1', 'help', 'And neither is this one' ]
		]
	};

	// ToolGroup creation, in Toolbar numeric and ToolGroup alphabetical order
	createToolGroup( 0, 'barTools' );
	createToolGroup( 0, 'disabledBarTools' );
	createToolGroup( 0, 'listTools' );
	createToolGroup( 0, 'moreListTools' );
	createToolGroup( 0, 'disabledListTools' );
	createToolGroup( 0, 'autoDisableListTools' );
	createToolGroup( 0, 'unusedStuff' );

	createToolGroup( 1, 'cite' );
	createToolGroup( 1, 'citeDisabled' );
	createToolGroup( 1, 'menuTools' );
	createToolGroup( 1, 'disabledMenuTools' );

	createToolGroup( 6, 'listTools' );

	createToolGroup( 7, 'menuTools' );
	createToolGroup( 7, 'disabledMenuTools' );

	for ( i = 3; i <= 5; i += 2 ) {
		createToolGroup( i - 1, 'overflowTools' );
		createToolGroup( i - 1, 'editorSwitchTools' );
		createToolGroup( i, 'cite' );
		createToolGroup( i, 'formatTools' );
		createToolGroup( i, 'insertTools' );
		createToolGroup( i, 'history' );
		createToolGroup( i, 'link' );
		createToolGroup( i, 'listTools' );
		createToolGroup( i, 'moreListTools' );
		createToolGroup( i, 'autoDisableListTools' );
		createToolGroup( i, 'menuTools' );
		createToolGroup( i, 'specialCharacters' );
		createToolGroup( i, 'structureTools' );
		createToolGroup( i, 'styleTools' );
		createToolGroup( i, 'unusedStuff' );
	}

	for ( i = 0; i < toolbars.length; i++ ) {
		if ( i === 2 || i === 4 || i === 6 ) {
			// Action toolbars
			continue;
		}
		$containers = $containers.add(
			new OO.ui.PanelLayout( {
				expanded: false,
				framed: true
			} ).$element
				.addClass( 'demo-toolbar' )
		);

		$containers.last().append( toolbars[ i ].$element );
	}
	$containers.append( '' );
	$demo.append(
		new OO.ui.PanelLayout( {
			expanded: false,
			framed: false
		} ).$element
			.addClass( 'demo-container demo-toolbars' )
			.attr( 'role', 'main' )
			.append(
				$containers.eq( 0 ).append( '<div class="demo-toolbars-contents">Toolbar</div>' ),
				$containers.eq( 1 ).append( '<div class="demo-toolbars-contents">Toolbar with action buttons</div>' ),
				$containers.eq( 2 ).append( '<div class="demo-toolbars-contents">Word processor toolbar</div>' ),
				$containers.eq( 3 ).prepend( '<div class="demo-toolbars-contents">Word processor toolbar set to <code>position: &#39;bottom&#39;</code></div>' ),
				$containers.eq( 4 ).append( '<div class="demo-toolbars-contents">Toolbar with action buttons in a group</div>' )
			)
	);
	for ( i = 0; i < toolbars.length; i++ ) {
		toolbars[ i ].initialize();
	}
};
