<?php
if ( !defined( 'OOUI_DEMOS' ) ) {
	header( 'Location: ../demos.php' );
	exit;
}

$loremIpsum = "Lorem ipsum dolor sit amet, consectetur adipisicing elit, " .
	"sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.\xE2\x80\x8E";

$demoContainer = new OOUI\PanelLayout( [
	'expanded' => false,
	'padded' => true,
	'framed' => true,
] );

$demoContainer->addClasses( [ 'demo-container' ] );

$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'id' => 'demo-section-buttons',
	'infusable' => true,
	'label' => 'Buttons',
	'items' => [
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [ 'label' => 'Normal' ] ),
			[
				'label' => "ButtonWidget (normal)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Progressive',
				'flags' => [ 'progressive' ]
			] ),
			[
				'label' => "ButtonWidget (progressive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Destructive',
				'flags' => [ 'destructive' ]
			] ),
			[
				'label' => "ButtonWidget (destructive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Primary progressive',
				'flags' => [ 'primary', 'progressive' ]
			] ),
			[
				'label' => "ButtonWidget (primary, progressive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Primary destructive',
				'flags' => [ 'primary', 'destructive' ]
			] ),
			[
				'label' => "ButtonWidget (primary, destructive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Disabled',
				'disabled' => true
			] ),
			[
				'label' => "ButtonWidget (disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Progressive',
				'icon' => 'tag',
				'flags' => [ 'progressive' ],
				'disabled' => true
			] ),
			[
				'label' => "ButtonWidget (progressive, icon, disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Icon',
				'icon' => 'tag'
			] ),
			[
				'label' => "ButtonWidget (icon)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Icon',
				'icon' => 'tag',
				'flags' => [ 'progressive' ]
			] ),
			[
				'label' => "ButtonWidget (icon, progressive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Indicator',
				'indicator' => 'down'
			] ),
			[
				'label' => "ButtonWidget (indicator)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Indicator',
				'indicator' => 'down',
				'flags' => [ 'progressive' ]
			] ),
			[
				'label' => "ButtonWidget (indicator, progressive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'framed' => false,
				'icon' => 'help',
				'title' => 'Icon only'
			] ),
			[
				'label' => "ButtonWidget (frameless, icon only)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'framed' => false,
				'icon' => 'tag',
				'label' => 'Labeled'
			] ),
			[
				'label' => "ButtonWidget (frameless)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'framed' => false,
				'flags' => [ 'progressive' ],
				'icon' => 'check',
				'label' => 'Progressive'
			] ),
			[
				'label' => "ButtonWidget (frameless, progressive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'framed' => false,
				'flags' => [ 'destructive' ],
				'icon' => 'trash',
				'label' => 'Destructive'
			] ),
			[
				'label' => "ButtonWidget (frameless, destructive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'framed' => false,
				'flags' => [ 'destructive' ],
				'label' => 'Cancel'
			] ),
			[
				'label' => "ButtonWidget (frameless, label-only, destructive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'framed' => false,
				'icon' => 'tag',
				'label' => 'Disabled',
				'disabled' => true
			] ),
			[
				'label' => "ButtonWidget (frameless, disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonInputWidget( [
				'label' => 'Submit the form',
				'type' => 'submit',
				'flags' => [ 'primary', 'progressive' ],
				'useInputTag' => true
			] ),
			[
				'align' => 'top',
				'label' => "ButtonInputWidget (using <input>)\xE2\x80\x8E"
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonInputWidget( [
				'label' => 'Another button',
				'type' => 'button'
			] ),
			[
				'align' => 'top',
				'label' => "ButtonInputWidget (using <button>)\xE2\x80\x8E"
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonInputWidget( [
				'framed' => false,
				'label' => 'Another button',
				'type' => 'button'
			] ),
			[
				'align' => 'top',
				'label' => "ButtonInputWidget (frameless)\xE2\x80\x8E"
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonInputWidget( [
				'framed' => false,
				'label' => 'Another button',
				'type' => 'button',
				'useInputTag' => true
			] ),
			[
				'align' => 'top',
				'label' => "ButtonInputWidget (frameless, using <input>)\xE2\x80\x8E"
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Access key: G',
				'accessKey' => 'g'
			] ),
			[
				'label' => "ButtonWidget (with accesskey)\xE2\x80\x8E",
				'align' => 'top',
				'help' => new OOUI\HtmlSnippet( 'Notice: Using `accesskey` might '  .
					'<a href="http://webaim.org/techniques/keyboard/accesskey" target="_blank">' .
					'negatively impact screen readers</a>!' )
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonInputWidget( [
				'title' => 'Access key is added to the title.',
				'label' => 'Access key: H',
				'accessKey' => 'h'
			] ),
			[
				'label' => "ButtonInputWidget (with accesskey and title)\xE2\x80\x8E",
				'align' => 'top',
			]
		)
	]
] ) );

$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'id' => 'demo-section-button-sets',
	'infusable' => true,
	'label' => 'Button sets',
	'items' => [
		new OOUI\FieldLayout(
			new OOUI\ButtonGroupWidget( [
				'items' => [
					new OOUI\ButtonWidget( [
						'icon' => 'tag',
						'label' => 'One'
					] ),
					new OOUI\ButtonWidget( [
						'label' => 'Two'
					] ),
					new OOUI\ButtonWidget( [
						'indicator' => 'clear',
						'label' => 'Three'
					] )
				]
			] ),
			[
				'label' => 'ButtonGroupWidget',
				'align' => 'top'
			]
		)
	]
] ) );

$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'id' => 'demo-section-button-showcase',
	'infusable' => true,
	'label' => 'Button style showcase',
	'items' => [
		new OOUI\FieldLayout(
			new Demo\ButtonStyleShowcaseWidget(),
			[
				'align' => 'top',
			]
		)
	]
] ) );

$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'id' => 'demo-section-inputs',
	'infusable' => true,
	'label' => 'TextInput',
	'items' => [
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [ 'value' => 'Text input' ] ),
			[
				'label' => "TextInputWidget\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [ 'icon' => 'help' ] ),
			[
				'label' => "TextInputWidget (icon)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'required' => true
			] ),
			[
				'label' => "TextInputWidget (required)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [ 'placeholder' => 'Placeholder' ] ),
			[
				'label' => "TextInputWidget (placeholder)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'value' => 'Title attribute',
				'title' => 'Title attribute with more information about me.'
			] ),
			[
				'label' => "TextInputWidget (with title)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [ 'type' => 'search' ] ),
			[
				'label' => "TextInputWidget (type=search)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\SearchInputWidget(),
			[
				'label' => "SearchInputWidget",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'value' => 'Access key: S',
				'accessKey' => 's'
			] ),
			[
				'label' => "TextInputWidget (with accesskey)\xE2\x80\x8E",
				'align' => 'top',
				'help' => new OOUI\HtmlSnippet( 'Notice: Using `accesskey` might '  .
					'<a href="http://webaim.org/techniques/keyboard/accesskey" target="_blank">' .
					'negatively impact screen readers</a>!' )
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'value' => 'Readonly',
				'readOnly' => true
			] ),
			[
				'label' => "TextInputWidget (readonly)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'value' => 'Disabled',
				'disabled' => true
			] ),
			[
				'label' => "TextInputWidget (disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\MultilineTextInputWidget( [
				'value' => "Multiline\nMultiline"
			] ),
			[
				'label' => "MultilineTextInputWidget \xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\MultilineTextInputWidget( [
				'rows' => 15,
				'value' => "Multiline\nMultiline"
			] ),
			[
				'label' => "MultilineTextInputWidget (rows=15)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\MultilineTextInputWidget( [
				'value' => "Multiline\nMultiline",
				'icon' => 'tag',
				'indicator' => 'required'
			] ),
			[
				'label' => "MultilineTextInputWidget (icon, indicator)\xE2\x80\x8E",
				'align' => 'top'
			]
		)
	]
] ) );

$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'id' => 'demo-section-inputs-binary',
	'infusable' => true,
	'label' => 'Checkbox & Radio',
	'items' => [
		new OOUI\FieldLayout(
			new OOUI\CheckboxInputWidget( [
				'selected' => true
			] ),
			[
				'align' => 'inline',
				'label' => 'CheckboxInputWidget'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\CheckboxInputWidget( [
				'selected' => true,
				'disabled' => true
			] ),
			[
				'align' => 'inline',
				'label' => "CheckboxInputWidget (disabled)\xE2\x80\x8E"
			]
		),
		new OOUI\FieldLayout(
			new OOUI\CheckboxInputWidget( [
				'selected' => true,
				'accessKey' => 't'
			] ),
			[
				'align' => 'inline',
				'label' => "CheckboxInputWidget (with accesskey T and title)\xE2\x80\x8E",
				'title' => 'Access key is added to the title.',
			]
		),
		new OOUI\FieldLayout(
			new OOUI\RadioInputWidget( [
				'name' => 'oojs-ui-radio-demo'
			] ),
			[
				'align' => 'inline',
				'label' => 'Connected RadioInputWidget #1'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\RadioInputWidget( [
				'name' => 'oojs-ui-radio-demo',
				'selected' => true
			] ),
			[
				'align' => 'inline',
				'label' => 'Connected RadioInputWidget #2'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\RadioInputWidget( [
				'selected' => true,
				'disabled' => true
			] ),
			[
				'align' => 'inline',
				'label' => "RadioInputWidget (disabled)\xE2\x80\x8E"
			]
		),
		new OOUI\FieldLayout(
			new OOUI\RadioSelectInputWidget( [
				'value' => 'dog',
				'options' => [
					[
						'data' => 'cat',
						'label' => 'Cat'
					],
					[
						'data' => 'dog',
						'label' => 'Dog'
					],
					[
						'data' => 'goldfish',
						'label' => 'Goldfish'
					],
				]
			] ),
			[
				'align' => 'top',
				'label' => 'RadioSelectInputWidget',
			]
		),
		new OOUI\FieldLayout(
			new OOUI\CheckboxMultiselectInputWidget( [
				'value' => [ 'dog', 'cat' ],
				'options' => [
					[
						'data' => 'cat',
						'label' => 'Cat'
					],
					[
						'data' => 'dog',
						'label' => "Dog (disabled)\xE2\x80\x8E",
						'disabled' => true
					],
					[
						'data' => 'goldfish',
						'label' => 'Goldfish'
					],
				]
			] ),
			[
				'align' => 'top',
				'label' => 'CheckboxMultiselectInputWidget',
			]
		)
	]
] ) );

$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'id' => 'demo-section-dropdown',
	'infusable' => true,
	'label' => 'Dropdown',
	'items' => [
		new OOUI\FieldLayout(
			new OOUI\DropdownInputWidget( [
				'options' => [
					[
						'data' => 'a',
						'label' => 'First'
					],
					[
						'data' => 'b',
						'label' => 'Second'
					],
					[
						'data' => 'c',
						'label' => 'Third'
					]
				],
				'value' => 'b'
			] ),
			[
				'label' => 'DropdownInputWidget',
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\DropdownInputWidget( [
				'disabled' => true
			] ),
			[
				'label' => 'DropdownInputWidget (disabled)',
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\DropdownInputWidget( [
				'options' => [
					[
						'optgroup' => 'Vowels'
					],
					[
						'data' => 'a',
						'label' => 'A'
					],
					[
						'optgroup' => 'Consonants'
					],
					[
						'data' => 'b',
						'label' => 'B'
					],
					[
						'data' => 'c',
						'label' => 'C'
					]
				],
				'value' => 'b'
			] ),
			[
				'label' => 'DropdownInputWidget (with optgroup)',
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\DropdownInputWidget( [
				'options' => [
					[ 'data' => 'sq', 'label' => 'Albanian' ],
					[ 'data' => 'frp', 'label' => 'Arpitan' ],
					[ 'data' => 'ba', 'label' => 'Bashkir' ],
					[ 'data' => 'pt-br', 'label' => 'Brazilian Portuguese' ],
					[ 'data' => 'tzm', 'label' => 'Central Atlas Tamazight' ],
					[ 'data' => 'zh', 'label' => 'Chinese' ],
					[ 'data' => 'co', 'label' => 'Corsican' ],
					[ 'data' => 'del', 'label' => 'Delaware' ],
					[ 'data' => 'eml', 'label' => 'Emiliano-Romagnolo' ],
					[ 'data' => 'en', 'label' => 'English' ],
					[ 'data' => 'fi', 'label' => 'Finnish' ],
					[ 'data' => 'aln', 'label' => 'Gheg Albanian' ],
					[ 'data' => 'he', 'label' => 'Hebrew' ],
					[ 'data' => 'ilo', 'label' => 'Iloko' ],
					[ 'data' => 'kbd', 'label' => 'Kabardian' ],
					[ 'data' => 'csb', 'label' => 'Kashubian' ],
					[ 'data' => 'avk', 'label' => 'Kotava' ],
					[ 'data' => 'lez', 'label' => 'Lezghian' ],
					[ 'data' => 'nds-nl', 'label' => 'Low Saxon' ],
					[ 'data' => 'ml', 'label' => 'Malayalam' ],
					[ 'data' => 'dum', 'label' => 'Middle Dutch' ],
					[ 'data' => 'ary', 'label' => 'Moroccan Arabic' ],
					[ 'data' => 'pih', 'label' => 'Norfuk / Pitkern' ],
					[ 'data' => 'ny', 'label' => 'Nyanja' ],
					[ 'data' => 'ang', 'label' => 'Old English' ],
					[ 'data' => 'non', 'label' => 'Old Norse' ],
					[ 'data' => 'pau', 'label' => 'Palauan' ],
					[ 'data' => 'pdt', 'label' => 'Plautdietsch' ],
					[ 'data' => 'ru', 'label' => 'Russian' ],
					[ 'data' => 'stq', 'label' => 'Saterland Frisian' ],
					[ 'data' => 'ii', 'label' => 'Sichuan Yi' ],
					[ 'data' => 'bcc', 'label' => 'Southern Balochi' ],
					[ 'data' => 'shi', 'label' => 'Tachelhit' ],
					[ 'data' => 'th', 'label' => 'Thai' ],
					[ 'data' => 'tr', 'label' => 'Turkish' ],
					[ 'data' => 'fiu-vro', 'label' => 'Võro' ],
					[ 'data' => 'vls', 'label' => 'West Flemish' ],
					[ 'data' => 'zea', 'label' => 'Zeelandic' ],
				],
				'value' => 'en',
			] ),
			[
				'label' => "DropdownInputWidget (long)\xE2\x80\x8E",
				'align' => 'top'
			]
		)
	]
] ) );

$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'id' => 'demo-section-comboBox',
	'infusable' => true,
	'label' => 'ComboBox',
	'items' => [
		new OOUI\FieldLayout(
			new OOUI\ComboBoxInputWidget( [
				'options' => [
					[ 'data' => 'asd', 'label' => 'Label for asd' ],
					[ 'data' => 'fgh', 'label' => 'Label for fgh' ],
					[ 'data' => 'jkl', 'label' => 'Label for jkl' ],
					[ 'data' => 'zxc', 'label' => 'Label for zxc' ],
					[ 'data' => 'vbn', 'label' => 'Label for vbn' ],
				]
			] ),
			[
				'label' => 'ComboBoxInputWidget',
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ComboBoxInputWidget( [
				'disabled' => true,
				'options' => [
					[ 'data' => 'asd', 'label' => 'Label for asd' ],
					[ 'data' => 'fgh', 'label' => 'Label for fgh' ],
					[ 'data' => 'jkl', 'label' => 'Label for jkl' ],
					[ 'data' => 'zxc', 'label' => 'Label for zxc' ],
					[ 'data' => 'vbn', 'label' => 'Label for vbn' ],
				]
			] ),
			[
				'label' => "ComboBoxInputWidget (disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ComboBoxInputWidget(),
			[
				'label' => "ComboBoxInputWidget (empty)\xE2\x80\x8E",
				'align' => 'top'
			]
		)
	]
] ) );

$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'id' => 'demo-section-progressBar',
	'infusable' => true,
	'label' => 'Progress bar',
	'items' => [
		new OOUI\FieldLayout(
			new OOUI\ProgressBarWidget( [
				'progress' => 33
			] ),
			[
				'label' => 'Progress bar',
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ProgressBarWidget( [
				'disabled' => true,
				'progress' => 50
			] ),
			[
				'label' => 'Progress bar (disabled)',
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ProgressBarWidget( [
				'progress' => false
			] ),
			[
				'label' => 'Progress bar (indeterminate)',
				'align' => 'top'
			]
		),
	]
] ) );

$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'id' => 'demo-section-others',
	'infusable' => true,
	'label' => 'Other widgets',
	'items' => [
		new OOUI\FieldLayout(
			new OOUI\IconWidget( [
				'icon' => 'search',
				'title' => 'Search icon'
			] ),
			[
				'label' => "IconWidget (normal)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\IconWidget( [
				'icon' => 'trash',
				'flags' => 'destructive',
				'title' => 'Remove icon'
			] ),
			[
				'label' => "IconWidget (flagged)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\IconWidget( [
				'icon' => 'search',
				'title' => 'Search icon',
				'disabled' => true
			] ),
			[
				'label' => "IconWidget (disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\IndicatorWidget( [
				'indicator' => 'required',
				'title' => 'Required indicator'
			] ),
			[
				'label' => "IndicatorWidget (normal)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\IndicatorWidget( [
				'indicator' => 'required',
				'title' => 'Required indicator',
				'disabled' => true
			] ),
			[
				'label' => "IndicatorWidget (disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\LabelWidget( [
				'label' => 'Label'
			] ),
			[
				'label' => "LabelWidget (normal)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\LabelWidget( [
				'label' => 'Label',
				'disabled' => true,
			] ),
			[
				'label' => "LabelWidget (disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\LabelWidget( [
				'label' => new OOUI\HtmlSnippet( '<b>Fancy</b> <i>text</i> <u>formatting</u>!' ),
			] ),
			[
				'label' => "LabelWidget (with html)\xE2\x80\x8E",
				'align' => 'top'
			]
		)
	]
] ) );

$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'id' => 'demo-section-fieldLayouts',
	'infusable' => true,
	'label' => 'Field layouts',
	'icon' => 'tag',
	'items' => [
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'FieldLayout with help',
				'help' => $loremIpsum,
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'FieldLayout with HTML help',
				'help' => new OOUI\HtmlSnippet( '<b>Bold text</b> is helpful!' ),
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'FieldLayout with title',
				'title' => 'Field title text',
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => new OOUI\HtmlSnippet( '<i>FieldLayout with rich text label</i>' ),
				'align' => 'top'
			]
		),
		new OOUI\ActionFieldLayout(
			new OOUI\TextInputWidget(),
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'ActionFieldLayout aligned top',
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget(),
			[
				'label' => 'FieldLayout aligned top with help',
				'help' => $loremIpsum,
				'align' => 'top'
			]
		),
		new OOUI\ActionFieldLayout(
			new OOUI\TextInputWidget(),
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'ActionFieldLayout aligned top with help',
				'help' => $loremIpsum,
				'align' => 'top'
			]
		),
		new OOUI\ActionFieldLayout(
			new OOUI\CheckboxInputWidget( [ 'selected' => true ] ),
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'ActionFieldLayout aligned inline',
				'align' => 'inline',
			]
		),
		new OOUI\FieldLayout(
			new OOUI\CheckboxInputWidget( [ 'selected' => true ] ),
			[
				'label' => 'FieldLayout aligned inline with help',
				'help' => $loremIpsum,
				'align' => 'inline',
			]
		),
		new OOUI\ActionFieldLayout(
			new OOUI\CheckboxInputWidget( [ 'selected' => true ] ),
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'ActionFieldLayout aligned inline with help',
				'help' => $loremIpsum,
				'align' => 'inline',
			]
		),
		new OOUI\ActionFieldLayout(
			new OOUI\TextInputWidget(),
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'ActionFieldLayout aligned left',
				'align' => 'left',
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget(),
			[
				'label' => 'FieldLayout aligned left with help',
				'help' => $loremIpsum,
				'align' => 'left',
			]
		),
		new OOUI\ActionFieldLayout(
			new OOUI\TextInputWidget(),
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'ActionFieldLayout aligned left with help',
				'help' => $loremIpsum,
				'align' => 'left',
			]
		),
		new OOUI\ActionFieldLayout(
			new OOUI\TextInputWidget(),
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'ActionFieldLayout aligned right',
				'align' => 'right',
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget(),
			[
				'label' => 'FieldLayout aligned right with help',
				'help' => $loremIpsum,
				'align' => 'right',
			]
		),
		new OOUI\ActionFieldLayout(
			new OOUI\TextInputWidget(),
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'ActionFieldLayout aligned right with help',
				'help' => $loremIpsum,
				'align' => 'right',
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'value' => ''
			] ),
			[
				'label' => 'FieldLayout with notice',
				'notices' => [ 'Please input a number.' ],
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'value' => 'Foo'
			] ),
			[
				'label' => 'FieldLayout with error message',
				'errors' => [ 'The value must be a number.' ],
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'value' => 'Foo'
			] ),
			[
				'label' => 'FieldLayout with notice and error message',
				'notices' => [ 'Please input a number.' ],
				'errors' => [ 'The value must be a number.' ],
				'align' => 'top'
			]
		)
	]
] ) );

// We can't make the outer FieldsetLayout infusable, because the Widget in its FieldLayout
// is added with 'content', which is not preserved after infusion. But we need the Widget
// to wrap the HorizontalLayout. Need to think about this at some point.
$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'id' => 'demo-section-horizontalLayout',
	'infusable' => false,
	'label' => 'HorizontalLayout',
	'items' => [
		new OOUI\FieldLayout(
			new OOUI\Widget( [
				'content' => new OOUI\HorizontalLayout( [
					'infusable' => true,
					'items' => [
						new OOUI\ButtonWidget( [ 'label' => 'Button' ] ),
						new OOUI\ButtonGroupWidget( [ 'items' => [
							new OOUI\ButtonWidget( [ 'label' => 'A' ] ),
							new OOUI\ButtonWidget( [ 'label' => 'B' ] )
						] ] ),
						new OOUI\ButtonInputWidget( [ 'label' => 'ButtonInput' ] ),
						new OOUI\TextInputWidget( [ 'value' => 'TextInput' ] ),
						new OOUI\DropdownInputWidget( [ 'options' => [
							[
								'label' => 'DropdownInput',
								'data' => null
							]
						] ] ),
						new OOUI\CheckboxInputWidget( [ 'selected' => true ] ),
						new OOUI\RadioInputWidget( [ 'selected' => true ] ),
						new OOUI\LabelWidget( [ 'label' => 'Label' ] )
					],
				] ),
			] ),
			[
				'label' => 'Multiple widgets shown as a single line, ' .
					'as used in compact forms or in parts of a bigger widget.',
				'align' => 'top'
			]
		),
	],
] ) );

$demoContainer->appendContent( new OOUI\FormLayout( [
	'infusable' => true,
	'method' => 'GET',
	'action' => 'demos.php',
	'items' => [
		new OOUI\FieldsetLayout( [
			'id' => 'demo-section-formLayout',
			'label' => 'Form layout',
			'items' => [
				new OOUI\FieldLayout(
					new OOUI\TextInputWidget( [
						'name' => 'username',
					] ),
					[
						'label' => 'User name',
						'align' => 'top',
					]
				),
				new OOUI\FieldLayout(
					new OOUI\TextInputWidget( [
						'name' => 'password',
						'type' => 'password',
					] ),
					[
						'label' => 'Password',
						'align' => 'top',
					]
				),
				new OOUI\FieldLayout(
					new OOUI\CheckboxInputWidget( [
						'name' => 'rememberme',
						'selected' => true,
					] ),
					[
						'label' => 'Remember me',
						'align' => 'inline',
					]
				),
				new OOUI\FieldLayout(
					new OOUI\HiddenInputWidget( [
						'name' => 'hidden',
						'value' => 'hidden value',
					] )
				),
				new OOUI\FieldLayout(
					new OOUI\ButtonInputWidget( [
						'type' => 'submit',
						'label' => 'Submit form',
					] )
				),
			]
		] ),
		new OOUI\FieldsetLayout( [
			'label' => null,
			'items' => [
				new OOUI\FieldLayout(
					new OOUI\TextInputWidget( [
						'name' => 'summary',
					] ),
					[
						'label' => 'Summary',
						'align' => 'top',
					]
				),
				new OOUI\FieldLayout(
					new OOUI\Widget( [
						'content' => new OOUI\HorizontalLayout( [
							'items' => [
								new OOUI\ButtonInputWidget( [
									'name' => 'login',
									'label' => 'Log in',
									'type' => 'submit',
									'flags' => [ 'primary', 'progressive' ],
									'icon' => 'userAvatar',
								] ),
								new OOUI\ButtonInputWidget( [
									'framed' => false,
									'flags' => [ 'destructive' ],
									'label' => 'Cancel',
								] ),
								new OOUI\ButtonInputWidget( [
									'framed' => false,
									'icon' => 'tag',
									'label' => 'Random icon button',
								] ),
							]
						] ),
					] ),
					[
						'label' => null,
						'align' => 'top',
					]
				),
			]
		] ),
	]
] ) );

echo $demoContainer;
