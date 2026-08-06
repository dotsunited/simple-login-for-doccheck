( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';

	const el = element.createElement;
	const __ = i18n.__;
	const InspectorControls = blockEditor.InspectorControls;
	const PanelBody = components.PanelBody;
	const Placeholder = components.Placeholder;
	const SelectControl = components.SelectControl;
	const useBlockProps = blockEditor.useBlockProps;

	const sizeOptions = [
		{ label: __( 'Use plugin setting', 'simple-login-for-doccheck' ), value: '' },
		{ label: __( 'Small', 'simple-login-for-doccheck' ), value: 'small' },
		{ label: __( 'Medium', 'simple-login-for-doccheck' ), value: 'medium' },
		{ label: __( 'Large', 'simple-login-for-doccheck' ), value: 'large' },
	];

	const languageOptions = [
		{ label: __( 'Use plugin setting', 'simple-login-for-doccheck' ), value: '' },
		{ label: __( 'Automatic', 'simple-login-for-doccheck' ), value: 'auto' },
		{ label: __( 'German', 'simple-login-for-doccheck' ), value: 'de' },
		{ label: __( 'English', 'simple-login-for-doccheck' ), value: 'en' },
		{ label: __( 'French', 'simple-login-for-doccheck' ), value: 'fr' },
		{ label: __( 'Spanish', 'simple-login-for-doccheck' ), value: 'es' },
		{ label: __( 'Italian', 'simple-login-for-doccheck' ), value: 'it' },
		{ label: __( 'Dutch', 'simple-login-for-doccheck' ), value: 'nl' },
	];

	function Edit( props ) {
		const attributes = props.attributes;
		const previewSize = attributes.size || 'medium';
		const blockProps = useBlockProps( {
			className: 'simple-login-for-doccheck-block-editor',
		} );

		return el(
			element.Fragment,
			null,
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{
						title: __( 'Button settings', 'simple-login-for-doccheck' ),
						initialOpen: true,
					},
					el( SelectControl, {
						label: __( 'Size', 'simple-login-for-doccheck' ),
						value: attributes.size,
						options: sizeOptions,
						onChange: function ( size ) {
							props.setAttributes( { size: size } );
						},
					} ),
					el( SelectControl, {
						label: __( 'Language', 'simple-login-for-doccheck' ),
						value: attributes.language,
						options: languageOptions,
						onChange: function ( language ) {
							props.setAttributes( { language: language } );
						},
					} )
				)
			),
			el(
				'div',
				blockProps,
				el(
					Placeholder,
					{
						icon: 'lock',
						label: __( 'DocCheck Login', 'simple-login-for-doccheck' ),
					},
					el(
						'div',
						{
							className:
								'simple-login-for-doccheck-block-editor__preview simple-login-for-doccheck-block-editor__preview--' +
								previewSize,
						},
						__( 'DocCheck login button', 'simple-login-for-doccheck' )
					),
					el(
						'p',
						{ className: 'simple-login-for-doccheck-block-editor__description' },
						__(
							'The real login button is rendered on the published page.',
							'simple-login-for-doccheck'
						)
					)
				)
			)
		);
	}

	blocks.registerBlockType( 'dotsunited/simple-login-for-doccheck', {
		apiVersion: 3,
		title: __( 'DocCheck Login', 'simple-login-for-doccheck' ),
		category: 'widgets',
		icon: 'lock',
		description: __(
			'Displays the DocCheck login button for protected content.',
			'simple-login-for-doccheck'
		),
		keywords: [
			'DocCheck',
			__( 'login', 'simple-login-for-doccheck' ),
			__( 'authentication', 'simple-login-for-doccheck' ),
		],
		attributes: {
			size: {
				type: 'string',
				default: '',
			},
			language: {
				type: 'string',
				default: '',
			},
		},
		supports: {
			html: false,
		},
		edit: Edit,
		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.element,
	window.wp.i18n
);
