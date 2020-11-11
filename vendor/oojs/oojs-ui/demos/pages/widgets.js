Demo.static.pages.widgets = function ( demo ) {
	var i, fieldsets,
		loremIpsum = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, ' +
			'sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.\u200E',
		textInputForLabel, labelForTextInput, radioSelectInputForLabel, labelForRadioSelectInput,
		inputForValidation, fieldLayoutForValidation,
		horizontalDragItems = [],
		verticalDragItems = [],
		verticalHandledDragItems = [],
		$overlay = $( '<div>' ).addClass( 'demo-overlay' ).attr( 'id', 'demo-overlay' ),
		$demo = demo.$element,
		disabledItemsTagMultiselectWidget = new OO.ui.TagMultiselectWidget( {
			placeholder: 'Add tags',
			allowArbitrary: true
		} );

	disabledItemsTagMultiselectWidget.addTag( 'item1', 'Item 1 (optional)' );
	disabledItemsTagMultiselectWidget.addTag( 'item2', 'Item 2 (mandatory)' );
	disabledItemsTagMultiselectWidget.findItemFromData( 'item2' ).setDisabled( true );

	for ( i = 0; i <= 12; i++ ) {
		horizontalDragItems.push(
			new Demo.DraggableItemWidget( {
				data: 'item' + i,
				icon: 'tag',
				label: 'Inline item ' + i
			} )
		);
		if ( i <= 6 ) {
			verticalDragItems.push(
				new Demo.DraggableItemWidget( {
					data: 'item' + i,
					icon: 'tag',
					label: 'Item ' + i
				} )
			);
			verticalHandledDragItems.push(
				new Demo.DraggableHandledItemWidget( {
					data: 'item' + i,
					icon: 'draggable',
					label: 'Item ' + i
				} )
			);
		}
	}

	textInputForLabel = new OO.ui.TextInputWidget( { value: 'Input for label above' } );
	labelForTextInput = new OO.ui.LabelWidget( {
		label: 'Label for TextInputWidget below',
		input: textInputForLabel
	} );

	radioSelectInputForLabel = new OO.ui.RadioSelectInputWidget( {
		options: [
			{
				data: 'a',
				label: 'Input for label above'
			},
			{
				data: 'b',
				label: 'Input for label above'
			}
		]
	} );
	labelForRadioSelectInput = new OO.ui.LabelWidget( {
		label: 'Label for RadioSelectInputWidget below',
		input: radioSelectInputForLabel
	} );

	inputForValidation = new OO.ui.TextInputWidget( {
		validate: function ( value ) {
			return value.length % 2 === 0;
		}
	} );

	fieldLayoutForValidation = new OO.ui.FieldLayout( inputForValidation, {
		align: 'top',
		label: 'FieldLayout aligned top with validation errors',
		help: 'Enter only even number of characters'
	} );

	inputForValidation.$input.on( 'blur', function () {
		inputForValidation.getValidity().then( function () {
			fieldLayoutForValidation.setErrors( [] );
		}, function () {
			fieldLayoutForValidation.setErrors( [
				'Please enter an even number of characters'
			] );
		} );
	} );

	fieldsets = [
		new OO.ui.FieldsetLayout( {
			id: 'demo-section-buttons',
			label: 'Buttons',
			items: [
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( { label: 'Normal' } ),
					{
						label: 'ButtonWidget (normal)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						label: 'Progressive',
						flags: [ 'progressive' ]
					} ),
					{
						label: 'ButtonWidget (progressive)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						label: 'Destructive',
						flags: [ 'destructive' ]
					} ),
					{
						label: 'ButtonWidget (destructive)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						label: 'Primary progressive',
						flags: [ 'primary', 'progressive' ]
					} ),
					{
						label: 'ButtonWidget (primary, progressive)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						label: 'Primary destructive',
						flags: [ 'primary', 'destructive' ]
					} ),
					{
						label: 'ButtonWidget (primary, destructive)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						label: 'Disabled',
						disabled: true
					} ),
					{
						label: 'ButtonWidget (disabled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						label: 'Progressive',
						icon: 'tag',
						flags: [ 'progressive' ],
						disabled: true
					} ),
					{
						label: 'ButtonWidget (progressive, icon, disabled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						label: 'Icon',
						icon: 'tag'
					} ),
					{
						label: 'ButtonWidget (icon)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						label: 'Icon',
						icon: 'tag',
						flags: [ 'progressive' ]
					} ),
					{
						label: 'ButtonWidget (icon, progressive)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						label: 'Indicator',
						indicator: 'down'
					} ),
					{
						label: 'ButtonWidget (indicator)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						label: 'Indicator',
						indicator: 'down',
						flags: [ 'progressive' ]
					} ),
					{
						label: 'ButtonWidget (indicator, progressive)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						label: 'Access key: G',
						accessKey: 'g'
					} ),
					{
						label: 'ButtonWidget (with accesskey)\u200E',
						align: 'top',
						help: new OO.ui.HtmlSnippet( 'Notice: Using `accesskey` might <a href="http://webaim.org/techniques/keyboard/accesskey" target="_blank">negatively impact screen readers</a>!' )
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						icon: 'help',
						title: 'Icon only, framed'
					} ),
					{
						label: 'ButtonWidget (icon only)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						indicator: 'clear',
						title: 'Indicator only, framed'
					} ),
					{
						label: 'ButtonWidget (indicator only)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						framed: false,
						icon: 'help',
						title: 'Icon only'
					} ),
					{
						label: 'ButtonWidget (frameless, icon only)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						framed: false,
						indicator: 'clear',
						title: 'Indicator only'
					} ),
					{
						label: 'ButtonWidget (frameless, indicator only)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						framed: false,
						icon: 'tag',
						label: 'Labeled'
					} ),
					{
						label: 'ButtonWidget (frameless)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						framed: false,
						flags: [ 'progressive' ],
						icon: 'check',
						label: 'Progressive'
					} ),
					{
						label: 'ButtonWidget (frameless, progressive)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						framed: false,
						flags: [ 'warning' ],
						icon: 'alert',
						label: 'Warning'
					} ),
					{
						label: 'ButtonWidget (frameless, warning)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						framed: false,
						flags: [ 'destructive' ],
						icon: 'trash',
						label: 'Destructive'
					} ),
					{
						label: 'ButtonWidget (frameless, destructive)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						framed: false,
						flags: [ 'destructive' ],
						label: 'Cancel'
					} ),
					{
						label: 'ButtonWidget (frameless, label-only, destructive)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						framed: false,
						icon: 'tag',
						label: 'Disabled',
						disabled: true
					} ),
					{
						label: 'ButtonWidget (frameless, disabled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						framed: false,
						icon: 'tag',
						indicator: 'down',
						label: 'Labeled'
					} ),
					{
						label: 'ButtonWidget (frameless, icon & label & indicator)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						framed: false,
						indicator: 'down',
						label: 'Labeled'
					} ),
					{
						label: 'ButtonWidget (frameless, label & indicator)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						framed: false,
						icon: 'tag',
						indicator: 'down',
						title: 'Icon & indicator'
					} ),
					{
						label: 'ButtonWidget (frameless, icon & indicator)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonInputWidget( {
						label: 'Submit the form',
						type: 'submit',
						flags: [ 'primary', 'progressive' ],
						useInputTag: true
					} ),
					{
						align: 'top',
						label: 'ButtonInputWidget (using <input>)\u200E'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonInputWidget( {
						label: 'Another button',
						type: 'button'
					} ),
					{
						align: 'top',
						label: 'ButtonInputWidget (using <button>)\u200E'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonInputWidget( {
						title: 'Accesskey is added to the title.',
						label: 'Access key: H',
						accessKey: 'h'
					} ),
					{
						label: 'ButtonInputWidget (with accesskey & title)\u200E',
						align: 'top',
						help: new OO.ui.HtmlSnippet( 'Notice: Using `accesskey` might <a href="http://webaim.org/techniques/keyboard/accesskey" target="_blank">negatively impact screen readers</a>!' )
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonInputWidget( {
						framed: false,
						label: 'Another button',
						type: 'button'
					} ),
					{
						align: 'top',
						label: 'ButtonInputWidget (frameless, using <button>)\u200E'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonInputWidget( {
						framed: false,
						label: 'Another button',
						type: 'button',
						useInputTag: true
					} ),
					{
						align: 'top',
						label: 'ButtonInputWidget (frameless, using <input>)\u200E'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ToggleButtonWidget( { label: 'Toggle' } ),
					{
						label: 'ToggleButtonWidget',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ToggleButtonWidget( { label: 'Toggle', value: true } ),
					{
						label: 'ToggleButtonWidget (initially active)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ToggleButtonWidget( { icon: 'next' } ),
					{
						label: 'ToggleButtonWidget (icon only)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ToggleButtonWidget( { icon: 'next', value: true } ),
					{
						label: 'ToggleButtonWidget (icon only, initially active)\u200E',
						align: 'top'
					}
				)
			]
		} ),
		new OO.ui.FieldsetLayout( {
			id: 'demo-section-button-sets',
			label: 'Button sets',
			items: [
				new OO.ui.FieldLayout(
					new OO.ui.ButtonGroupWidget( {
						items: [
							new OO.ui.ButtonWidget( {
								icon: 'tag',
								label: 'One'
							} ),
							new OO.ui.ButtonWidget( {
								label: 'Two'
							} ),
							new OO.ui.ButtonWidget( {
								indicator: 'clear',
								label: 'Three'
							} )
						]
					} ),
					{
						label: 'ButtonGroupWidget',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonGroupWidget( {
						items: [
							new OO.ui.ButtonWidget( {
								label: 'One',
								flags: [ 'destructive' ]
							} ),
							new OO.ui.ButtonWidget( {
								label: 'Two',
								flags: [ 'progressive' ]
							} )
						]
					} ),
					{
						label: 'ButtonGroupWidget (destructive and progressive ButtonWidget)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonGroupWidget( {
						items: [
							new OO.ui.ButtonWidget( {
								icon: 'tag',
								flags: [ 'destructive' ]
							} ),
							new OO.ui.ButtonWidget( {
								label: 'Two',
								flags: [ 'progressive' ]
							} )
						]
					} ),
					{
						label: 'ButtonGroupWidget (destructive icon and progressive text)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonGroupWidget( {
						items: [
							new OO.ui.ToggleButtonWidget( {
								icon: 'tag',
								label: 'One'
							} ),
							new OO.ui.ToggleButtonWidget( {
								label: 'Two'
							} ),
							new OO.ui.ToggleButtonWidget( {
								indicator: 'clear',
								label: 'Three'
							} )
						]
					} ),
					{
						label: 'ButtonGroupWidget with ToggleButtonWidgets',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonSelectWidget( {
						items: [
							new OO.ui.ButtonOptionWidget( {
								data: 'b',
								icon: 'tag',
								label: 'One'
							} ),
							new OO.ui.ButtonOptionWidget( {
								data: 'c',
								label: 'Two'
							} ),
							new OO.ui.ButtonOptionWidget( {
								data: 'd',
								indicator: 'clear',
								label: 'Three'
							} )
						]
					} ),
					{
						label: 'ButtonSelectWidget',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonSelectWidget( {
						disabled: true,
						items: [
							new OO.ui.ButtonOptionWidget( {
								data: 'b',
								icon: 'tag',
								label: 'One'
							} ),
							new OO.ui.ButtonOptionWidget( {
								data: 'c',
								label: 'Two'
							} ),
							new OO.ui.ButtonOptionWidget( {
								data: 'd',
								indicator: 'clear',
								label: 'Three'
							} )
						]
					} ),
					{
						label: 'ButtonSelectWidget (disabled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonSelectWidget( {
						items: [
							new OO.ui.ButtonOptionWidget( {
								data: 'b',
								icon: 'tag',
								label: 'One',
								disabled: true
							} ),
							new OO.ui.ButtonOptionWidget( {
								data: 'c',
								label: 'Two'
							} ),
							new OO.ui.ButtonOptionWidget( {
								data: 'd',
								indicator: 'clear',
								label: 'Three'
							} )
						]
					} ),
					{
						label: 'ButtonSelectWidget (disabled items)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonSelectWidget( {
						items: [
							new OO.ui.ButtonOptionWidget( {
								data: 'a',
								label: 'Access key: I',
								accessKey: 'i'
							} ),
							new OO.ui.ButtonOptionWidget( {
								data: 'b',
								label: 'Access key: J',
								accessKey: 'j'
							} ),
							new OO.ui.ButtonOptionWidget( {
								data: 'c',
								label: 'Access key: K',
								accessKey: 'k'
							} )
						]
					} ),
					{
						label: 'ButtonSelectWidget (with accesskeys)\u200E',
						align: 'top'
					}
				)
			]
		} ),
		new OO.ui.FieldsetLayout( {
			id: 'demo-section-button-showcase',
			label: 'Button style showcase',
			items: [
				new OO.ui.FieldLayout(
					new Demo.ButtonStyleShowcaseWidget(),
					{
						align: 'top'
					}
				)
			]
		} ),
		new OO.ui.FieldsetLayout( {
			id: 'demo-section-inputs',
			label: 'Inputs: TextInput, TextInput, MultilineTextInput, SearchInput, NumberInput',
			items: [
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget( { value: 'Text input' } ),
					{
						label: 'TextInputWidget\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget( { icon: 'help' } ),
					{
						label: 'TextInputWidget (icon)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget( {
						required: true,
						validate: 'non-empty'
					} ),
					{
						label: 'TextInputWidget (required)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget( {
						validate: function ( value ) {
							return value.length % 2 === 0;
						}
					} ),
					{
						label: 'TextInputWidget (only allows even number of characters)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget( { placeholder: 'Placeholder' } ),
					{
						label: 'TextInputWidget (placeholder)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget( {
						value: 'Title attribute',
						title: 'Title attribute with more information about me.'
					} ),
					{
						label: 'TextInputWidget (with title)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget( {
						value: 'Readonly',
						readOnly: true
					} ),
					{
						label: 'TextInputWidget (readonly)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget( {
						value: 'Disabled',
						disabled: true
					} ),
					{
						label: 'TextInputWidget (disabled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.MultilineTextInputWidget( {
						value: 'Multiline\nMultiline'
					} ),
					{
						label: 'MultilineTextInputWidget\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.MultilineTextInputWidget( {
						rows: 15,
						value: 'Multiline\nMultiline'
					} ),
					{
						label: 'MultilineTextInputWidget (rows=15)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.MultilineTextInputWidget( {
						autosize: true,
						value: 'Autosize\nAutosize\nAutosize\nAutosize'
					} ),
					{
						label: 'MultilineTextInputWidget (autosize)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.MultilineTextInputWidget( {
						rows: 10,
						autosize: true,
						value: 'Autosize\nAutosize\nAutosize\nAutosize'
					} ),
					{
						label: 'MultilineTextInputWidget (autosize, rows=10)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.MultilineTextInputWidget( {
						autosize: true,
						icon: 'tag',
						indicator: 'clear',
						label: 'Inline label',
						value: 'Autosize\nAutosize\nAutosize\nAutosize'
					} ),
					{
						label: 'MultilineTextInputWidget (autosize, icon, indicator, label)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget( {
						value: 'Text input with label',
						label: 'Inline label'
					} ),
					{
						label: 'TextInputWidget (label)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget( {
						value: 'Text input with label',
						label: 'Inline label',
						labelPosition: 'before'
					} ),
					{
						label: 'TextInputWidget (label[position=before])\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget( {
						icon: 'tag',
						indicator: 'clear',
						value: 'Text input with label',
						label: 'Inline label'
					} ),
					{
						label: 'TextInputWidget (icon, indicator, label)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget( {
						icon: 'tag',
						indicator: 'clear',
						value: 'Text input with label',
						label: 'Inline label',
						labelPosition: 'before'
					} ),
					{
						label: 'TextInputWidget (icon, indicator, label[position=before])\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget( {
						value: 'Disabled',
						icon: 'tag',
						indicator: 'clear',
						label: 'Inline label',
						disabled: true
					} ),
					{
						label: 'TextInputWidget (icon, indicator, label, disabled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget( {
						value: 'Access key: S',
						accessKey: 's'
					} ),
					{
						label: 'TextInputWidget (with accesskey)\u200E',
						align: 'top',
						help: new OO.ui.HtmlSnippet( 'Notice: Using `accesskey` might <a href="http://webaim.org/techniques/keyboard/accesskey" target="_blank">negatively impact screen readers</a>!' )
					}
				),
				new OO.ui.FieldLayout(
					new Demo.DynamicLabelTextInputWidget( {
						getLabelText: function ( value ) {
							return String( value.length );
						}
					} ),
					{
						label: 'TextInputWidget (with dynamic label – length)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new Demo.DynamicLabelTextInputWidget( {
						maxLength: 300,
						getLabelText: function ( value ) {
							return String( 300 - value.length );
						}
					} ),
					{
						label: 'TextInputWidget (with dynamic label – remaining length)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.SearchInputWidget(),
					{
						label: 'SearchInputWidget (type=search)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.SearchInputWidget( { disabled: true } ),
					{
						label: 'SearchInputWidget (disabled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.SearchInputWidget( { disabled: true, value: 'test' } ),
					{
						label: 'SearchInputWidget (disabled, filled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.NumberInputWidget(),
					{
						label: 'NumberInputWidget',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.NumberInputWidget( { disabled: true } ),
					{
						label: 'NumberInputWidget (disabled)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.NumberInputWidget( { min: 1, max: 5, isInteger: true } ),
					{
						label: 'NumberInputWidget (1–5, ints only)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.NumberInputWidget( { min: 0, max: 1, step: 0.1, pageStep: 0.25 } ),
					{
						label: 'NumberInputWidget (0–1, step by .1, page by .25)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.NumberInputWidget( { showButtons: false } ),
					{
						label: 'NumberInputWidget (no buttons)',
						align: 'top'
					}
				)
			]
		} ),
		new OO.ui.FieldsetLayout( {
			id: 'demo-section-inputs-binary',
			label: 'Checkbox, Radio & ToggleSwitch',
			items: [
				new OO.ui.FieldLayout(
					new OO.ui.CheckboxInputWidget( {
						selected: true
					} ),
					{
						align: 'inline',
						label: 'CheckboxInputWidget'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.CheckboxInputWidget( {
						selected: true,
						disabled: true
					} ),
					{
						align: 'inline',
						label: 'CheckboxInputWidget (disabled)\u200E'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.CheckboxInputWidget( {
						selected: true,
						accessKey: 't'
					} ),
					{
						align: 'inline',
						label: 'CheckboxInputWidget (with accesskey T and title)\u200E',
						title: 'Access key is added to the title.'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.RadioInputWidget( {
						name: 'oojs-ui-radio-demo'
					} ),
					{
						align: 'inline',
						label: 'Connected RadioInputWidget #1'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.RadioInputWidget( {
						name: 'oojs-ui-radio-demo',
						selected: true
					} ),
					{
						align: 'inline',
						label: 'Connected RadioInputWidget #2'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.RadioInputWidget( {
						selected: true,
						disabled: true
					} ),
					{
						align: 'inline',
						label: 'RadioInputWidget (disabled)\u200E'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.RadioSelectWidget( {
						items: [
							new OO.ui.RadioOptionWidget( {
								data: 'cat',
								label: 'Cat'
							} ),
							new OO.ui.RadioOptionWidget( {
								data: 'dog',
								label: 'Dog'
							} ),
							new OO.ui.RadioOptionWidget( {
								data: 'goldfish',
								label: 'Goldfish. By the way, this is a very long label. ' + loremIpsum,
								disabled: true
							} )
						]
					} ),
					{
						align: 'top',
						label: 'RadioSelectWidget'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.CheckboxMultiselectWidget( {
						items: [
							new OO.ui.CheckboxMultioptionWidget( {
								data: 'cat',
								label: 'Cat'
							} ),
							new OO.ui.CheckboxMultioptionWidget( {
								data: 'dog',
								label: 'Dog'
							} ),
							new OO.ui.CheckboxMultioptionWidget( {
								data: 'goldfish',
								label: 'Goldfish. By the way, this is a very long label. ' + loremIpsum,
								disabled: true
							} )
						]
					} ),
					{
						align: 'top',
						label: 'CheckboxMultiselectWidget'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.RadioSelectInputWidget( {
						value: 'dog',
						options: [
							{
								data: 'cat',
								label: 'Cat'
							},
							{
								data: 'dog',
								label: 'Dog'
							},
							{
								data: 'goldfish',
								label: 'Goldfish'
							}
						]
					} ),
					{
						align: 'top',
						label: 'RadioSelectInputWidget'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.CheckboxMultiselectInputWidget( {
						value: [ 'dog', 'cat' ],
						options: [
							{
								data: 'cat',
								label: 'Cat'
							},
							{
								data: 'dog',
								label: 'Dog (disabled)\u200E',
								disabled: true
							},
							{
								data: 'goldfish',
								label: 'Goldfish'
							}
						]
					} ),
					{
						align: 'top',
						label: 'CheckboxMultiselectInputWidget'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.RadioSelectWidget( {
						items: [
							new OO.ui.RadioOptionWidget( {
								data: 'a',
								label: 'Access key: M',
								accessKey: 'm'
							} ),
							new OO.ui.RadioOptionWidget( {
								data: 'b',
								label: 'Access key: N',
								accessKey: 'n'
							} ),
							new OO.ui.RadioOptionWidget( {
								data: 'c',
								label: 'Access key: O',
								accessKey: 'o'
							} )
						]
					} ),
					{
						align: 'top',
						label: 'RadioSelectWidget (with accesskeys)\u200E'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ToggleSwitchWidget(),
					{
						label: 'ToggleSwitchWidget',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ToggleSwitchWidget( { disabled: true } ),
					{
						label: 'ToggleSwitchWidget (disabled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ToggleSwitchWidget( { disabled: true, value: true } ),
					{
						label: 'ToggleSwitchWidget (disabled, checked)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.RadioSelectWidget( {
						items: [
							new OO.ui.RadioOptionWidget( {
								data: 'a',
								label: $( $.parseHTML( 'Option A (<a href="https://example.com/a">details</a>)' ) )
							} ),
							new OO.ui.RadioOptionWidget( {
								data: 'b',
								label: $( $.parseHTML( 'Option B (<a href="https://example.com/b">details</a>)' ) )
							} ),
							new OO.ui.RadioOptionWidget( {
								data: 'c',
								label: $( $.parseHTML( 'Option C (<a href="https://example.com/c">details</a>)' ) )
							} )
						]
					} ),
					{
						label: 'RadioSelectWidget with links in the labels',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.RadioSelectWidget( {
						items: [
							new OO.ui.RadioOptionWidget( {
								data: 'foo',
								label: 'Foo'
							} ),
							new OO.ui.RadioOptionWidget( {
								data: 'bar',
								label: 'Bar'
							} ),
							new OO.ui.RadioOptionWidget( {
								data: '',
								label: $( [
									document.createTextNode( 'Other: ' ),
									new OO.ui.TextInputWidget().$element[ 0 ]
								] )
							} )
						]
					} ),
					{
						label: 'RadioSelectWidget with text input in a label',
						align: 'top'
					}
				)
			]
		} ),
		new OO.ui.FieldsetLayout( {
			id: 'demo-section-dropdown',
			label: 'Dropdown',
			items: [
				new OO.ui.FieldLayout(
					new OO.ui.DropdownWidget( {
						label: 'Select one',
						menu: {
							items: [
								new OO.ui.MenuOptionWidget( {
									data: 'a',
									label: 'First'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'b',
									label: 'Second',
									indicator: 'clear'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'c',
									label: 'Third'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'c',
									label: 'The fourth option has a long label'
								} ),
								new OO.ui.MenuOptionWidget( {
									icon: 'feedback',
									data: 'd',
									label: 'The fifth option has an icon'
								} )
							]
						}
					} ),
					{
						label: 'DropdownWidget',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.DropdownWidget( {
						label: 'Select one',
						icon: 'tag',
						menu: {
							items: [
								new OO.ui.MenuOptionWidget( {
									data: 'a',
									label: 'First'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'b',
									label: 'Disabled second option',
									indicator: 'clear',
									disabled: true
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'c',
									label: 'Third'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'd',
									label: 'Disabled fourth option with long label',
									disabled: true
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'c',
									label: 'Third'
								} )
							]
						}
					} ),
					{
						label: 'DropdownWidget (disabled options)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.DropdownWidget( {
						label: 'Select one',
						menu: {
							items: [
								new OO.ui.MenuSectionOptionWidget( {
									label: 'Dogs'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'corgi',
									label: 'Welsh Corgi',
									indicator: 'required'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'poodle',
									label: 'Standard Poodle',
									icon: 'star'
								} ),
								new OO.ui.MenuSectionOptionWidget( {
									label: 'Cats'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'lion',
									label: 'Lion'
								} )
							]
						}
					} ),
					{
						label: 'DropdownWidget (with MenuSectionOptionWidget)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.DropdownWidget( {
						label: 'Select one',
						disabled: true,
						menu: {
							items: [
								new OO.ui.MenuOptionWidget( {
									data: 'a',
									label: 'First'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'b',
									label: 'Second'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'c',
									label: 'Third'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'd',
									label: 'Fourth'
								} )
							]
						}
					} ),
					{
						label: 'DropdownWidget (disabled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.DropdownWidget( {
						label: 'Select one',
						$overlay: true,
						menu: {
							items: [
								new OO.ui.MenuOptionWidget( {
									data: 'a',
									label: 'First'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'b',
									label: 'Second'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'c',
									label: 'Third'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'd',
									label: 'Fourth'
								} )
							]
						}
					} ),
					{
						label: 'DropdownWidget (using default overlay)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.DropdownWidget( {
						label: 'Select one',
						$overlay: $overlay,
						menu: {
							items: [
								new OO.ui.MenuOptionWidget( {
									data: 'a',
									label: 'First'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'b',
									label: 'Second'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'c',
									label: 'Third'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'd',
									label: 'Fourth'
								} )
							]
						}
					} ),
					{
						label: 'DropdownWidget (using custom overlay)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.DropdownWidget( {
						label: 'Select one',
						menu: {
							items: [
								new OO.ui.MenuOptionWidget( {
									data: 'a',
									label: 'First'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'b',
									label: 'Second'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'c',
									label: 'Third'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'd',
									label: 'Fourth'
								} )
							],
							hideOnChoose: false
						}
					} ),
					{
						label: 'DropdownWidget (does not close on choose)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.DropdownWidget( {
						menu: {
							items: [
								new OO.ui.MenuOptionWidget( {
									data: 'a',
									label: 'Access key: P',
									accessKey: 'p'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'b',
									label: 'Access key: Q',
									accessKey: 'q'
								} ),
								new OO.ui.MenuOptionWidget( {
									data: 'c',
									label: 'Access key: R',
									accessKey: 'r'
								} )
							]
						}
					} ),
					{
						align: 'top',
						label: 'DropdownWidget (with accesskeys)\u200E'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.DropdownInputWidget( {
						options: [
							{
								data: 'a',
								label: 'First'
							},
							{
								data: 'b',
								label: 'Second'
							},
							{
								data: 'c',
								label: 'Third'
							}
						],
						value: 'b'
					} ),
					{
						label: 'DropdownInputWidget',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.DropdownInputWidget( {
						disabled: true
					} ),
					{
						label: 'DropdownInputWidget (disabled)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.DropdownInputWidget( {
						options: [
							{
								optgroup: 'Vowels'
							},
							{
								data: 'a',
								label: 'A'
							},
							{
								optgroup: 'Consonants'
							},
							{
								data: 'b',
								label: 'B'
							},
							{
								data: 'c',
								label: 'C'
							}
						],
						value: 'b'
					} ),
					{
						label: 'DropdownInputWidget (with optgroup)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.DropdownInputWidget( {
						options: [
							{ data: 'sq', label: 'Albanian' },
							{ data: 'frp', label: 'Arpitan' },
							{ data: 'ba', label: 'Bashkir' },
							{ data: 'pt-br', label: 'Brazilian Portuguese' },
							{ data: 'tzm', label: 'Central Atlas Tamazight' },
							{ data: 'zh', label: 'Chinese' },
							{ data: 'co', label: 'Corsican' },
							{ data: 'del', label: 'Delaware' },
							{ data: 'eml', label: 'Emiliano-Romagnolo' },
							{ data: 'en', label: 'English' },
							{ data: 'fi', label: 'Finnish' },
							{ data: 'aln', label: 'Gheg Albanian' },
							{ data: 'he', label: 'Hebrew' },
							{ data: 'ilo', label: 'Iloko' },
							{ data: 'kbd', label: 'Kabardian' },
							{ data: 'csb', label: 'Kashubian' },
							{ data: 'avk', label: 'Kotava' },
							{ data: 'lez', label: 'Lezghian' },
							{ data: 'nds-nl', label: 'Low Saxon' },
							{ data: 'ml', label: 'Malayalam' },
							{ data: 'dum', label: 'Middle Dutch' },
							{ data: 'ary', label: 'Moroccan Arabic' },
							{ data: 'pih', label: 'Norfuk / Pitkern' },
							{ data: 'ny', label: 'Nyanja' },
							{ data: 'ang', label: 'Old English' },
							{ data: 'non', label: 'Old Norse' },
							{ data: 'pau', label: 'Palauan' },
							{ data: 'pdt', label: 'Plautdietsch' },
							{ data: 'ru', label: 'Russian' },
							{ data: 'stq', label: 'Saterland Frisian' },
							{ data: 'ii', label: 'Sichuan Yi' },
							{ data: 'bcc', label: 'Southern Balochi' },
							{ data: 'shi', label: 'Tachelhit' },
							{ data: 'th', label: 'Thai' },
							{ data: 'tr', label: 'Turkish' },
							{ data: 'fiu-vro', label: 'Võro' },
							{ data: 'vls', label: 'West Flemish' },
							{ data: 'zea', label: 'Zeelandic' }
						],
						value: 'en'
					} ),
					{
						label: 'DropdownInputWidget (long)\u200E',
						align: 'top'
					}
				)
			]
		} ),
		new OO.ui.FieldsetLayout( {
			id: 'demo-section-comboBox',
			label: 'ComboBox',
			items: [
				new OO.ui.FieldLayout(
					new OO.ui.ComboBoxInputWidget( {
						options: [
							{ data: 'asd', label: 'Label for asd' },
							{ data: 'fgh', label: 'Label for fgh' },
							{ data: 'jkl', label: 'Label for jkl' },
							{ data: 'zxc', label: 'Label for zxc' },
							{ data: 'vbn', label: 'Label for vbn' }
						]
					} ),
					{
						label: 'ComboBoxInputWidget',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ComboBoxInputWidget( {
						options: [
							{ data: 'asd', label: 'A Label for asd' },
							{ data: 'fgh', label: 'A Label for fgh' },
							{ data: 'jkl', label: 'A Label for jkl' },
							{ data: 'zxc', label: 'A Label for zxc' },
							{ data: 'vbn', label: 'A Label for vbn' },
							{ data: 'asd', label: 'B Label for asd' },
							{ data: 'fgh', label: 'B Label for fgh' },
							{ data: 'jkl', label: 'B Label for jkl' },
							{ data: 'zxc', label: 'B Label for zxc' },
							{ data: 'vbn', label: 'B Label for vbn' },
							{ data: 'asd', label: 'C Label for asd' },
							{ data: 'fgh', label: 'C Label for fgh' },
							{ data: 'jkl', label: 'C Label for jkl' },
							{ data: 'zxc', label: 'C Label for zxc' },
							{ data: 'vbn', label: 'C Label for vbn' },
							{ data: 'asd', label: 'D Label for asd' },
							{ data: 'fgh', label: 'D Label for fgh' },
							{ data: 'jkl', label: 'D Label for jkl' },
							{ data: 'zxc', label: 'D Label for zxc' },
							{ data: 'vbn', label: 'D Label for vbn' },
							{ data: 'asd', label: 'E Label for asd' },
							{ data: 'fgh', label: 'E Label for fgh' },
							{ data: 'jkl', label: 'E Label for jkl' },
							{ data: 'zxc', label: 'E Label for zxc' },
							{ data: 'vbn', label: 'E Label for vbn' }
						],
						menu: {
							filterFromInput: true
						}
					} ),
					{
						label: 'ComboBoxInputWidget (filtering on input)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ComboBoxInputWidget( {
						options: [
							{ data: 'Option 1' },
							{ data: 'Option 2' },
							{ data: 'Option 3' },
							{ data: 'Option 4' },
							{ data: 'Option 5' }
						]
					} ),
					{
						label: 'ComboBoxInputWidget (no labels given)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ComboBoxInputWidget( {
						disabled: true,
						options: [
							{ data: 'asd', label: 'Label for asd' },
							{ data: 'fgh', label: 'Label for fgh' },
							{ data: 'jkl', label: 'Label for jkl' },
							{ data: 'zxc', label: 'Label for zxc' },
							{ data: 'vbn', label: 'Label for vbn' }
						]
					} ),
					{
						label: 'ComboBoxInputWidget (disabled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ComboBoxInputWidget(),
					{
						label: 'ComboBoxInputWidget (empty)\u200E',
						align: 'top'
					}
				)
			]
		} ),
		new OO.ui.FieldsetLayout( {
			id: 'demo-section-selectFile',
			label: 'SelectFile',
			items: [
				new OO.ui.FieldLayout(
					new OO.ui.SelectFileWidget( {} ),
					{
						label: 'SelectFileWidget\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.SelectFileWidget( { accept: [ 'image/png', 'image/jpeg' ] } ),
					{
						label: 'SelectFileWidget (accept PNG and JPEG)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.SelectFileWidget( {
						icon: 'tag',
						indicator: 'clear'
					} ),
					{
						label: 'SelectFileWidget (icon, indicator)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.SelectFileWidget( {
						icon: 'tag',
						indicator: 'clear',
						disabled: true
					} ),
					{
						label: 'SelectFileWidget (disabled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new Demo.UnsupportedSelectFileWidget(),
					{
						label: 'SelectFileWidget (no browser support)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.SelectFileWidget( { showDropTarget: true } ),
					{
						label: 'SelectFileWidget (with drop target)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.SelectFileWidget( {
						showDropTarget: true,
						disabled: true
					} ),
					{
						label: 'SelectFileWidget (with drop target, disabled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new Demo.UnsupportedSelectFileWidget( {
						showDropTarget: true
					} ),
					{
						label: 'SelectFileWidget (with drop target, no browser support)\u200E',
						align: 'top'
					}
				)
			]
		} ),
		new OO.ui.FieldsetLayout( {
			id: 'demo-section-tagMultiselect',
			label: 'TagMultiselect, MenuTagMultiselect, CapsuleMultiselect',
			items: [
				new OO.ui.FieldLayout(
					new OO.ui.TagMultiselectWidget( {
						placeholder: 'Add tags',
						allowArbitrary: true
					} ),
					{
						label: 'TagMultiselectWidget (allowArbitrary, inline input, placeholder)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					disabledItemsTagMultiselectWidget,
					{
						label: 'TagMultiselectWidget with disabled items',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TagMultiselectWidget( {
						placeholder: 'Add tags',
						allowArbitrary: true,
						disabled: true
					} ),
					{
						label: 'TagMultiselectWidget (disabled, inline input, placeholder)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TagMultiselectWidget( {
						allowArbitrary: false,
						allowDisplayInvalidTags: true,
						allowedValues: [ 'foo', 'bar', 'baz' ]
					} ),
					{
						label: 'TagMultiselectWidget (inline input, allowed values: [ \'foo\', \'bar\', \'baz\' ], allowDisplayInvalidTags)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TagMultiselectWidget( {
						placeholder: 'Add tags',
						allowArbitrary: true,
						inputPosition: 'outline'
					} ),
					{
						label: 'TagMultiselectWidget (allowArbitrary, inputPosition:outline, placeholder)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TagMultiselectWidget( {
						allowArbitrary: true,
						inputPosition: 'outline',
						inputWidget: new OO.ui.NumberInputWidget()
					} ),
					{
						label: 'TagMultiselectWidget (inputwidget: OO.ui.NumberInputWidget, inputPosition:outline)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.PopupTagMultiselectWidget( {
						allowArbitrary: true,
						icon: 'tag',
						indicator: 'clear'
					} ),
					{
						label: 'PopupTagMultiselectWidget (icon, indicator, allowArbitrary)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.MenuTagMultiselectWidget( {
						selected: [
							{ data: 'foo', label: 'Label for foo' },
							{ data: 'bar', label: 'Label for bar' }
						],
						options: [
							{ data: 'foo', label: 'Label for foo' },
							{ data: 'bar', label: 'Label for bar' },
							{ data: 'baz', label: 'Label for baz' }
						]
					} ),
					{
						label: 'MenuTagMultiselectWidget (initially selected, preset options)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.MenuTagMultiselectWidget( {
						selected: [ 'foo', 'bar' ],
						allowArbitrary: true
					} ),
					{
						label: 'MenuTagMultiselectWidget (initially selected, allowArbitrary)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.MenuTagMultiselectWidget( {
						allowArbitrary: false,
						options: [
							{ data: 'abc', label: 'Label for abc' },
							{ data: 'asd', label: 'Label for asd' },
							{ data: 'jkl', label: 'Label for jkl' }
						]
					} ),
					{
						label: 'MenuTagMultiselectWidget (allowArbitrary:false)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.MenuTagMultiselectWidget( {
						inputPosition: 'outline',
						allowArbitrary: false,
						options: [
							{ data: 'abc', label: 'Label for abc' },
							{ data: 'asd', label: 'Label for asd' },
							{ data: 'jkl', label: 'Label for jkl' }
						]
					} ),
					{
						label: 'MenuTagMultiselectWidget (inputPosition:outline, allowArbitrary:false)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.CapsuleMultiselectWidget( {
						menu: {
							items: [
								new OO.ui.MenuOptionWidget( { data: 'abc', label: 'Label for abc' } ),
								new OO.ui.MenuOptionWidget( { data: 'asd', label: 'Label for asd' } ),
								new OO.ui.MenuOptionWidget( { data: 'jkl', label: 'Label for jkl' } ),
								new OO.ui.MenuOptionWidget( { data: 'jjj', label: 'Label for jjj' } ),
								new OO.ui.MenuOptionWidget( { data: 'zxc', label: 'Label for zxc' } ),
								new OO.ui.MenuOptionWidget( { data: 'vbn', label: 'Label for vbn' } )
							]
						}
					} ),
					{
						label: 'CapsuleMultiselectWidget',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.CapsuleMultiselectWidget( {
						placeholder: 'Type like a cat…',
						menu: {
							items: [
								new OO.ui.MenuOptionWidget( { data: 'abc', label: 'Label for abc' } ),
								new OO.ui.MenuOptionWidget( { data: 'asd', label: 'Label for asd' } ),
								new OO.ui.MenuOptionWidget( { data: 'jkl', label: 'Label for jkl' } ),
								new OO.ui.MenuOptionWidget( { data: 'jjj', label: 'Label for jjj' } ),
								new OO.ui.MenuOptionWidget( { data: 'zxc', label: 'Label for zxc' } ),
								new OO.ui.MenuOptionWidget( { data: 'vbn', label: 'Label for vbn' } )
							]
						}
					} ),
					{
						label: 'CapsuleMultiselectWidget (with placeholder)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.CapsuleMultiselectWidget( {
						allowArbitrary: true,
						icon: 'tag',
						indicator: 'clear',
						menu: {
							items: [
								new OO.ui.MenuOptionWidget( { data: 'abc', label: 'Label for abc' } ),
								new OO.ui.MenuOptionWidget( { data: 'asd', label: 'Label for asd' } ),
								new OO.ui.MenuOptionWidget( { data: 'jkl', label: 'Label for jkl' } ),
								new OO.ui.MenuOptionWidget( { data: 'jjj', label: 'Label for jjj' } ),
								new OO.ui.MenuOptionWidget( { data: 'zxc', label: 'Label for zxc' } ),
								new OO.ui.MenuOptionWidget( { data: 'vbn', label: 'Label for vbn' } )
							]
						}
					} ),
					{
						label: 'CapsuleMultiselectWidget (icon, indicator, arbitrary values allowed)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.CapsuleMultiselectWidget( {
						disabled: true,
						icon: 'tag',
						indicator: 'clear',
						menu: {
							items: [
								new OO.ui.MenuOptionWidget( { data: 'abc', label: 'Label for abc' } ),
								new OO.ui.MenuOptionWidget( { data: 'asd', label: 'Label for asd' } ),
								new OO.ui.MenuOptionWidget( { data: 'jkl', label: 'Label for jkl' } ),
								new OO.ui.MenuOptionWidget( { data: 'jjj', label: 'Label for jjj' } ),
								new OO.ui.MenuOptionWidget( { data: 'zxc', label: 'Label for zxc' } ),
								new OO.ui.MenuOptionWidget( { data: 'vbn', label: 'Label for vbn' } )
							]
						}
					} ),
					{
						label: 'CapsuleMultiselectWidget (disabled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.CapsuleMultiselectWidget( {
						disabled: true,
						menu: {
							items: [
								new OO.ui.MenuOptionWidget( { data: 'abc', label: 'Label for abc' } ),
								new OO.ui.MenuOptionWidget( { data: 'asd', label: 'Label for asd' } ),
								new OO.ui.MenuOptionWidget( { data: 'jkl', label: 'Label for jkl' } )
							]
						}
					} ).addItemsFromData( [ 'abc', 'asd' ] ),
					{
						label: 'CapsuleMultiselectWidget (disabled, initially selected)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.CapsuleMultiselectWidget( {
						menu: {
							items: [
								new OO.ui.MenuOptionWidget( { data: 'abc', label: 'Label for abc' } ),
								new OO.ui.MenuOptionWidget( { data: 'asd', label: 'Label for asd' } ),
								new OO.ui.MenuOptionWidget( { data: 'jkl', label: 'Label for jkl' } )
							]
						}
					} ).addItemsFromData( [ 'abc', 'asd' ] ),
					{
						label: 'CapsuleMultiselectWidget (initially selected)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.CapsuleMultiselectWidget( {
						menu: {
							items: [
								new OO.ui.MenuOptionWidget( { data: 'abc', label: 'Label for abc' } ),
								new OO.ui.MenuSectionOptionWidget( { label: 'Foo' } ),
								new OO.ui.MenuOptionWidget( { data: 'asd', label: 'Label for asd' } ),
								new OO.ui.MenuOptionWidget( { data: 'jkl', label: 'Label for jkl' } ),
								new OO.ui.MenuSectionOptionWidget( { label: 'Bar' } ),
								new OO.ui.MenuOptionWidget( { data: 'jjj', label: 'Label for jjj' } ),
								new OO.ui.MenuOptionWidget( { data: 'zxc', label: 'Label for zxc' } ),
								new OO.ui.MenuSectionOptionWidget( { label: 'Baz' } ),
								new OO.ui.MenuOptionWidget( { data: 'vbn', label: 'Label for vbn' } )
							]
						}
					} ),
					{
						label: 'CapsuleMultiselectWidget (sectioned by MenuSectionOptionWidget)',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new Demo.CapsuleNumberPopupMultiselectWidget(),
					{
						label: 'CapsuleMultiselectWidget with NumberInputWidget popup\u200E',
						align: 'top'
					}
				)
			]
		} ),
		new OO.ui.FieldsetLayout( {
			id: 'demo-section-lookupElement',
			label: 'LookupElement',
			items: [
				new OO.ui.FieldLayout(
					new Demo.NumberLookupTextInputWidget(),
					{
						label: 'LookupElement (try inputting an integer)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new Demo.NumberLookupTextInputWidget( {
						highlightFirst: false
					} ),
					{
						label: 'LookupElement without highlighting 1st term (try inputting an integer)\u200E',
						align: 'top'
					}
				)
			]
		} ),
		new OO.ui.FieldsetLayout( {
			id: 'demo-section-popupButton',
			label: 'PopupButton',
			items: [
				new OO.ui.FieldLayout(
					new OO.ui.PopupButtonWidget( {
						icon: 'info',
						framed: false,
						popup: {
							head: true,
							label: 'More information',
							$content: $( '<p>' ).text( loremIpsum ),
							padded: true,
							align: 'force-left'
						}
					} ),
					{
						label: 'PopupButtonWidget (frameless, with popup head, align: force-left)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.PopupButtonWidget( {
						icon: 'info',
						framed: false,
						popup: {
							head: true,
							label: 'More information',
							$content: $( '<p>' ).text( loremIpsum ),
							padded: true,
							align: 'force-right'
						}
					} ),
					{
						label: 'PopupButtonWidget (frameless, with popup head align: force-right)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.PopupButtonWidget( {
						icon: 'info',
						framed: false,
						popup: {
							head: true,
							label: 'More information',
							$content: $( '<p>' ).text( loremIpsum ),
							padded: true,
							align: 'backwards'
						}
					} ),
					{
						label: 'PopupButtonWidget (frameless, with popup head align: backwards)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.PopupButtonWidget( {
						icon: 'info',
						framed: false,
						popup: {
							head: true,
							label: 'More information',
							$content: $( '<p>' ).text( loremIpsum ),
							padded: true,
							align: 'forwards'
						}
					} ),
					{
						label: 'PopupButtonWidget (frameless, with popup head align: forwards)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.PopupButtonWidget( {
						icon: 'info',
						framed: false,
						popup: {
							head: true,
							label: 'More information',
							$content: $( '<p>' ).text( loremIpsum ),
							padded: true,
							align: 'center'
						}
					} ),
					{
						label: 'PopupButtonWidget (frameless, with popup head align: center)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.PopupButtonWidget( {
						icon: 'info',
						framed: false,
						popup: {
							head: true,
							label: 'More information',
							$content: $( '<p>' + loremIpsum + '</p><ul><li>Item one</li><li>Item two</li><li>Item three</li><li>Item four</li></ul><p>Even more text here which might well be clipped off the visible area.</p>' ),
							$footer: $( '<p>And maybe a footer whilst we\'re at it?</p>' ),
							padded: true,
							align: 'forwards'
						}
					} ),
					{
						label: 'PopupButtonWidget (frameless, with popup head and footer, align: forwards)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.PopupButtonWidget( {
						icon: 'menu',
						label: 'Options',
						popup: {
							$content: $( '<p>' ).text( loremIpsum ),
							padded: true,
							align: 'forwards'
						}
					} ),
					{
						label: 'PopupButtonWidget (framed, no popup head, align: forwards)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.PopupButtonWidget( {
						icon: 'menu',
						label: 'Options',
						popup: {
							$content: $( '<p>' ).text( loremIpsum ),
							padded: true,
							align: 'backwards'
						}
					} ),
					{
						label: 'PopupButtonWidget (framed, no popup head, align: backwards)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.PopupButtonWidget( {
						icon: 'menu',
						label: 'Options',
						popup: {
							$content: $( '<p>' ).text( loremIpsum ),
							padded: true,
							align: 'center'
						}
					} ),
					{
						label: 'PopupButtonWidget (framed, no popup head, align: center)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.PopupButtonWidget( {
						icon: 'menu',
						label: 'Options',
						popup: {
							$content: $( '<p>' ).text( loremIpsum ),
							padded: true,
							align: 'center',
							position: 'above'
						}
					} ),
					{
						label: 'PopupButtonWidget (framed, no popup head, position: above)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.PopupButtonWidget( {
						icon: 'menu',
						label: 'Options',
						popup: {
							$content: $( '<p>' ).text( loremIpsum ),
							padded: true,
							align: 'center',
							position: 'before'
						}
					} ),
					{
						label: 'PopupButtonWidget (framed, no popup head, position: before)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.PopupButtonWidget( {
						icon: 'menu',
						label: 'Options',
						popup: {
							$content: $( '<p>' ).text( loremIpsum ),
							padded: true,
							align: 'center',
							position: 'after'
						}
					} ),
					{
						label: 'PopupButtonWidget (framed, no popup head, position: after)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.PopupButtonWidget( {
						icon: 'menu',
						label: 'Options',
						popup: {
							$content: $( '<p>' ).text( loremIpsum ),
							padded: true,
							anchor: false,
							align: 'center'
						}
					} ),
					{
						label: 'PopupButtonWidget (no anchor, align: center)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.PopupButtonWidget( {
						icon: 'menu',
						label: 'Options',
						popup: {
							$content: $( '<p>' ).text( loremIpsum ),
							padded: true,
							anchor: false,
							align: 'forwards'
						}
					} ),
					{
						label: 'PopupButtonWidget (no anchor, align: forwards)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.PopupButtonWidget( {
						icon: 'menu',
						label: 'Options',
						popup: {
							$content: $( '<p>' ).text( loremIpsum ),
							padded: true,
							anchor: false,
							align: 'backwards'
						}
					} ),
					{
						label: 'PopupButtonWidget (no anchor, align: backwards)\u200E',
						align: 'top'
					}
				)
			]
		} ),
		new OO.ui.FieldsetLayout( {
			id: 'demo-section-draggable',
			label: 'Draggable',
			items: [
				new OO.ui.FieldLayout(
					new Demo.DraggableGroupWidget( {
						orientation: 'horizontal',
						items: horizontalDragItems
					} ),
					{
						label: 'DraggableGroupWidget (horizontal)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new Demo.DraggableGroupWidget( {
						items: verticalDragItems
					} ),
					{
						label: 'DraggableGroupWidget (vertical)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new Demo.DraggableGroupWidget( {
						items: verticalHandledDragItems
					} ),
					{
						label: 'DraggableGroupWidget with handles (vertical)\u200E',
						align: 'top'
					}
				)
			]
		} ),
		new OO.ui.FieldsetLayout( {
			id: 'demo-section-progressBar',
			label: 'Progress bar',
			items: [
				new OO.ui.FieldLayout(
					new OO.ui.ProgressBarWidget( {
						progress: 33
					} ),
					{
						label: 'Progress bar',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ProgressBarWidget( {
						progress: 50,
						disabled: true
					} ),
					{
						label: 'Progress bar (disabled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ProgressBarWidget( {
						progress: false
					} ),
					{
						label: 'Progress bar (indeterminate)\u200E',
						align: 'top'
					}
				)
			]
		} ),
		new OO.ui.FieldsetLayout( {
			id: 'demo-section-others',
			label: 'Other widgets',
			items: [
				new OO.ui.FieldLayout(
					new OO.ui.IconWidget( {
						icon: 'search',
						title: 'Search icon'
					} ),
					{
						label: 'IconWidget (normal)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.IconWidget( {
						icon: 'trash',
						flags: 'destructive',
						title: 'Remove icon'
					} ),
					{
						label: 'IconWidget (flagged)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.IconWidget( {
						icon: 'search',
						title: 'Search icon',
						disabled: true
					} ),
					{
						label: 'IconWidget (disabled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.IndicatorWidget( {
						indicator: 'clear',
						title: 'Required indicator'
					} ),
					{
						label: 'IndicatorWidget (normal)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.IndicatorWidget( {
						indicator: 'clear',
						title: 'Required indicator',
						disabled: true
					} ),
					{
						label: 'IndicatorWidget (disabled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.LabelWidget( {
						label: 'Label'
					} ),
					{
						label: 'LabelWidget (normal)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.LabelWidget( {
						label: 'Label',
						disabled: true
					} ),
					{
						label: 'LabelWidget (disabled)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.LabelWidget( {
						label: new OO.ui.HtmlSnippet( '<b>Fancy</b> <i>text</i> <u>formatting</u>!' )
					} ),
					{
						label: 'LabelWidget (with HTML)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					labelForTextInput,
					{
						label: 'LabelWidget (with an associated TextInputWidget)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					textInputForLabel,
					{
						label: 'TextInputWidget (with an associated label)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					labelForRadioSelectInput,
					{
						label: 'LabelWidget (with an associated RadioSelectInputWidget)\u200E',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					radioSelectInputForLabel,
					{
						label: 'RadioSelectInputWidget (with an associated label)\u200E',
						align: 'top'
					}
				)
			]
		} ),
		new OO.ui.FieldsetLayout( {
			id: 'demo-section-fieldLayouts',
			label: 'Field layouts',
			icon: 'tag',
			help: loremIpsum,
			items: [
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						label: 'Button'
					} ),
					{
						label: 'FieldLayout with help',
						help: loremIpsum,
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						label: 'Button'
					} ),
					{
						label: 'FieldLayout with HTML help',
						help: new OO.ui.HtmlSnippet( '<b>Bold text</b> is helpful!' ),
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						label: 'Button'
					} ),
					{
						label: 'FieldLayout with title',
						title: 'Field title text',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.ButtonWidget( {
						label: 'Button'
					} ),
					{
						label: $( '<i>' ).text( 'FieldLayout with rich text label' ),
						align: 'top'
					}
				),
				new OO.ui.ActionFieldLayout(
					new OO.ui.TextInputWidget(),
					new OO.ui.ButtonWidget( {
						label: 'Button'
					} ),
					{
						label: 'ActionFieldLayout aligned top',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget(),
					{
						label: 'FieldLayout aligned top with help',
						help: loremIpsum,
						align: 'top'
					}
				),
				new OO.ui.ActionFieldLayout(
					new OO.ui.TextInputWidget(),
					new OO.ui.ButtonWidget( {
						label: 'Button'
					} ),
					{
						label: 'ActionFieldLayout aligned top with help',
						help: loremIpsum,
						align: 'top'
					}
				),
				new OO.ui.ActionFieldLayout(
					new OO.ui.CheckboxInputWidget( { selected: true } ),
					new OO.ui.ButtonWidget( {
						label: 'Button'
					} ),
					{
						label: 'ActionFieldLayout aligned inline',
						align: 'inline'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.CheckboxInputWidget( { selected: true } ),
					{
						label: 'FieldLayout aligned inline with help',
						help: loremIpsum,
						align: 'inline'
					}
				),
				new OO.ui.ActionFieldLayout(
					new OO.ui.CheckboxInputWidget( { selected: true } ),
					new OO.ui.ButtonWidget( {
						label: 'Button'
					} ),
					{
						label: 'ActionFieldLayout aligned inline with help',
						help: loremIpsum,
						align: 'inline'
					}
				),
				new OO.ui.ActionFieldLayout(
					new OO.ui.TextInputWidget(),
					new OO.ui.ButtonWidget( {
						label: 'Button'
					} ),
					{
						label: 'ActionFieldLayout aligned left',
						align: 'left'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget(),
					{
						label: 'FieldLayout aligned left with help',
						help: loremIpsum,
						align: 'left'
					}
				),
				new OO.ui.ActionFieldLayout(
					new OO.ui.TextInputWidget(),
					new OO.ui.ButtonWidget( {
						label: 'Button'
					} ),
					{
						label: 'ActionFieldLayout aligned left with help',
						help: loremIpsum,
						align: 'left'
					}
				),
				new OO.ui.ActionFieldLayout(
					new OO.ui.TextInputWidget(),
					new OO.ui.ButtonWidget( {
						label: 'Button'
					} ),
					{
						label: 'ActionFieldLayout aligned right',
						align: 'right'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget(),
					{
						label: 'FieldLayout aligned right with help',
						help: loremIpsum,
						align: 'right'
					}
				),
				new OO.ui.ActionFieldLayout(
					new OO.ui.TextInputWidget(),
					new OO.ui.ButtonWidget( {
						label: 'Button'
					} ),
					{
						label: 'ActionFieldLayout aligned right with help',
						help: loremIpsum,
						align: 'right'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget(),
					{
						label: 'FieldLayout aligned top with very long label. ' + loremIpsum,
						help: loremIpsum,
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.CheckboxInputWidget( { selected: true } ),
					{
						label: 'FieldLayout aligned inline with very long label. ' + loremIpsum,
						help: loremIpsum,
						align: 'inline'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget(),
					{
						label: 'FieldLayout aligned left with very long label. ' + loremIpsum,
						help: loremIpsum,
						align: 'left'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget(),
					{
						label: 'FieldLayout aligned right with very long label. ' + loremIpsum,
						help: loremIpsum,
						align: 'right'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget( {
						value: ''
					} ),
					{
						label: 'FieldLayout with notice',
						notices: [ 'Please input a number.' ],
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget( {
						value: 'Foo'
					} ),
					{
						label: 'FieldLayout with error message',
						errors: [ 'The value must be a number.' ],
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.TextInputWidget( {
						value: 'Foo'
					} ),
					{
						label: 'FieldLayout with notice and error message',
						notices: [ 'Please input a number.' ],
						errors: [ 'The value must be a number.' ],
						align: 'top'
					}
				),
				fieldLayoutForValidation
			]
		} ),
		new OO.ui.FieldsetLayout( {
			id: 'demo-section-horizontalLayout',
			label: 'HorizontalLayout',
			items: [
				new OO.ui.FieldLayout(
					new OO.ui.Widget( {
						content: [ new OO.ui.HorizontalLayout( {
							items: [
								new OO.ui.ButtonWidget( { label: 'Button' } ),
								new OO.ui.ButtonGroupWidget( { items: [
									new OO.ui.ToggleButtonWidget( { label: 'A' } ),
									new OO.ui.ToggleButtonWidget( { label: 'B' } )
								] } ),
								new OO.ui.ButtonInputWidget( { label: 'ButtonInput' } ),
								new OO.ui.TextInputWidget( { value: 'TextInput' } ),
								new OO.ui.DropdownInputWidget( { options: [
									{
										label: 'DropdownInput',
										data: null
									}
								] } ),
								new OO.ui.CheckboxInputWidget( { selected: true } ),
								new OO.ui.RadioInputWidget( { selected: true } ),
								new OO.ui.LabelWidget( { label: 'Label' } )
							]
						} ) ]
					} ),
					{
						label: 'Multiple widgets shown as a single line, ' +
							'as used in compact forms or in parts of a bigger widget.',
						align: 'top'
					}
				)
			]
		} ),
		new OO.ui.FieldsetLayout( {
			id: 'demo-section-other-layouts',
			label: 'Other layouts',
			items: [
				new OO.ui.FieldLayout(
					new OO.ui.Widget( {
						content: [
							new OO.ui.PanelLayout( {
								expanded: false,
								framed: true,
								content: [
									new OO.ui.BookletLayout( {
										expanded: false,
										outlined: true
									} ).addPages( [
										new Demo.SamplePage( 'first', {
											expanded: false,
											label: 'One'
										} ),
										new Demo.SamplePage( 'second', {
											expanded: false,
											label: 'Two'
										} ),
										new Demo.SamplePage( 'third', {
											expanded: false,
											label: 'Three'
										} ),
										new Demo.SamplePage( 'fourth', {
											expanded: false,
											label: 'Four'
										} ),
										new Demo.SamplePage( 'long', {
											expanded: false,
											label: 'Long',
											content: [
												$( '<p>' ).text( loremIpsum ),
												$( '<p>' ).text( loremIpsum ),
												$( '<p>' ).text( loremIpsum ),
												$( '<p>' ).text( loremIpsum ),
												$( '<p>' ).text( loremIpsum ),
												$( '<p>' ).text( loremIpsum ),
												$( '<p>' ).text( loremIpsum )
											]
										} )
									] )
								]
							} )
						]
					} ),
					{
						label: 'Outlined BookletLayout',
						align: 'top'
					}
				),
				new OO.ui.FieldLayout(
					new OO.ui.Widget( {
						content: [
							new OO.ui.PanelLayout( {
								expanded: false,
								framed: true,
								content: [
									new OO.ui.IndexLayout( {
										expanded: false
									} ).addTabPanels( [
										new Demo.SampleTabPanel( 'first', {
											expanded: false,
											label: 'One tab'
										} ),
										new Demo.SampleTabPanel( 'second', {
											expanded: false,
											label: 'Two tab'
										} ),
										new Demo.SampleTabPanel( 'third', {
											expanded: false,
											label: 'Three tab'
										} ),
										new Demo.SampleTabPanel( 'fourth', {
											expanded: false,
											label: 'Four tab'
										} ),
										new Demo.SampleTabPanel( 'long', {
											expanded: false,
											label: 'Long tab',
											content: [
												$( '<p>' ).text( loremIpsum ),
												$( '<p>' ).text( loremIpsum ),
												$( '<p>' ).text( loremIpsum )
											]
										} )
									] )
								]
							} )
						]
					} ),
					{
						label: 'IndexLayout',
						align: 'top'
					}
				)
			]
		} ),
		new OO.ui.FormLayout( {
			method: 'GET',
			action: 'demos.php',
			items: [
				new OO.ui.FieldsetLayout( {
					id: 'demo-section-formLayout',
					label: 'Form layout (compounded example)',
					items: [
						new OO.ui.FieldLayout(
							new OO.ui.TextInputWidget( {
								name: 'username'
							} ),
							{
								label: 'User name',
								align: 'top'
							}
						),
						new OO.ui.FieldLayout(
							new OO.ui.TextInputWidget( {
								name: 'password',
								type: 'password'
							} ),
							{
								label: 'Password',
								align: 'top'
							}
						),
						new OO.ui.FieldLayout(
							new OO.ui.ButtonSelectWidget( {
								items: [
									new OO.ui.ButtonOptionWidget( {
										label: 'One'
									} ),
									new OO.ui.ButtonOptionWidget( {
										label: 'Two'
									} ),
									new OO.ui.ButtonOptionWidget( {
										indicator: 'clear',
										label: 'Three'
									} )
								]
							} ),
							{
								label: 'Select one of multiple ButtonSelectWidget Buttons',
								align: 'top'
							}
						),
						new OO.ui.FieldLayout(
							new OO.ui.SelectFileWidget( {} ),
							{
								label: 'Select a file with SelectFileWidget\u200E',
								align: 'top'
							}
						),
						new OO.ui.FieldLayout(
							new OO.ui.CapsuleMultiselectWidget( {
								menu: {
									items: [
										new OO.ui.MenuOptionWidget( { data: 'abc', label: 'Abc Label' } ),
										new OO.ui.MenuOptionWidget( { data: 'def', label: 'Def Label' } ),
										new OO.ui.MenuOptionWidget( { data: 'ghi', label: 'Ghi Label' } )
									]
								}
							} ).addItemsFromData( [ 'abc', 'def' ] ),
							{
								label: 'Select from multiple CapsuleMultiselectWidget items\u200E',
								align: 'top'
							}
						),
						new OO.ui.FieldLayout(
							new OO.ui.RadioSelectWidget( {
								items: [
									new OO.ui.RadioOptionWidget( {
										data: 'mouse',
										label: 'Mouse'
									} ),
									new OO.ui.RadioOptionWidget( {
										data: 'elephant',
										label: 'Elephant'
									} )
								]
							} ),
							{
								align: 'top',
								label: 'Toggle the RadioSelectWidget'
							}
						),
						new OO.ui.FieldLayout(
							new OO.ui.ToggleSwitchWidget( { value: true } ),
							{
								label: 'Switch the ToggleSwitchWidget (checked)',
								align: 'right'
							}
						),
						new OO.ui.FieldLayout(
							new OO.ui.CheckboxInputWidget( {
								name: 'rememberme',
								selected: true
							} ),
							{
								label: 'Remember me',
								align: 'inline'
							}
						),
						new OO.ui.FieldLayout(
							new OO.ui.HiddenInputWidget( {
								name: 'hidden',
								value: 'hidden value'
							} )
						),
						new OO.ui.FieldLayout(
							new OO.ui.ButtonInputWidget( {
								type: 'submit',
								label: 'Submit form'
							} )
						)
					]
				} ),
				new OO.ui.FieldsetLayout( {
					label: null,
					items: [
						new OO.ui.FieldLayout(
							new OO.ui.TextInputWidget(),
							{
								label: 'Summary',
								align: 'top'
							}
						),
						new OO.ui.FieldLayout(
							new OO.ui.Widget( {
								content: [ new OO.ui.HorizontalLayout( {
									items: [
										new OO.ui.ButtonInputWidget( {
											name: 'login',
											label: 'Log in',
											type: 'submit',
											flags: [ 'primary', 'progressive' ],
											icon: 'userAvatar'
										} ),
										new OO.ui.ButtonWidget( {
											framed: false,
											flags: [ 'destructive' ],
											label: 'Cancel'
										} ),
										new OO.ui.ButtonWidget( {
											framed: false,
											icon: 'tag',
											label: 'Random icon button'
										} ),
										new OO.ui.ButtonWidget( {
											framed: false,
											icon: 'help',
											title: 'Icon only'
										} )
									]
								} ) ]
							} ),
							{
								label: null,
								align: 'top'
							}
						)
					]
				} )
			]
		} )
	];

	$.each( fieldsets, function ( i, fieldsetLayout ) {
		var showLayoutCode = false;

		if (
			fieldsetLayout instanceof OO.ui.FormLayout ||
			fieldsetLayout.getLabel() === 'Field layouts'
		) {
			showLayoutCode = true;
		}

		$.each( fieldsetLayout.getItems(), function ( j, fieldLayout ) {
			fieldLayout.$element.append(
				demo.buildLinkExample( fieldLayout, fieldsetLayout instanceof OO.ui.FormLayout ? fieldLayout : fieldsetLayout ),
				demo.buildConsole( fieldLayout, 'layout', 'widget', showLayoutCode )
			);
		} );
	} );

	$demo.append(
		new OO.ui.PanelLayout( {
			expanded: false,
			framed: true
		} ).$element
			.addClass( 'demo-container' )
			.attr( 'role', 'main' )
			.append(
				$( fieldsets.map( function ( fieldset ) { return fieldset.$element[ 0 ]; } ) )
			)
	);

	$overlay.appendTo( 'body' );

	demo.once( 'destroy', function () {
		// We are removing all of the widgets from the page, so also remove their "detached"
		// menus and stuff, otherwise they can remain visible forever.
		$overlay.remove();
		OO.ui.$defaultOverlay.empty();
	} );
};
