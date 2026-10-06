/* Jobly.si HRM blocks: editor side. Rendering is server-side (render.php). No build step. */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var RangeControl = wp.components.RangeControl;
	var ToggleControl = wp.components.ToggleControl;
	var SelectControl = wp.components.SelectControl;
	var ServerSideRender = wp.serverSideRender;
	var data = window.joblyIntegration || { jobs: [], demo: false };

	function preview( name, attributes ) {
		return el( 'div', useBlockProps(), el( ServerSideRender, { block: name, attributes: attributes } ) );
	}

	wp.blocks.registerBlockType( 'jobly/jobs-list', {
		edit: function ( props ) {
			var a = props.attributes;
			return el(
				wp.element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Seznam', 'jobly-integration' ) },
						el( RangeControl, {
							label: __( 'Število mest', 'jobly-integration' ),
							value: a.number,
							min: 1,
							max: 24,
							onChange: function ( v ) {
								props.setAttributes( { number: v } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Videz', 'jobly-integration' ),
							value: a.layout,
							options: [
								{ value: 'list', label: __( 'Seznam', 'jobly-integration' ) },
								{ value: 'grid', label: __( 'Mreža', 'jobly-integration' ) },
							],
							onChange: function ( v ) {
								props.setAttributes( { layout: v } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Iskanje in filtri', 'jobly-integration' ),
							checked: !! a.filters,
							onChange: function ( v ) {
								props.setAttributes( { filters: v } );
							},
						} )
					)
				),
				preview( 'jobly/jobs-list', a )
			);
		},
		save: function () {
			return null;
		},
	} );

	wp.blocks.registerBlockType( 'jobly/apply-form', {
		edit: function ( props ) {
			var a = props.attributes;
			return el(
				wp.element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Obrazec', 'jobly-integration' ) },
						el( SelectControl, {
							label: __( 'Delovno mesto', 'jobly-integration' ),
							value: a.job,
							options: data.jobs,
							onChange: function ( v ) {
								props.setAttributes( { job: v } );
							},
						} ),
						a.job
							? el( ToggleControl, {
									label: __( 'Pokaži tudi vsebino oglasa', 'jobly-integration' ),
									checked: !! a.showAd,
									onChange: function ( v ) {
										props.setAttributes( { showAd: v } );
									},
							  } )
							: null,
						el( RangeControl, {
							label: __( 'Višina okvirja (px, 0 = privzeto)', 'jobly-integration' ),
							value: a.height,
							min: 0,
							max: 3000,
							step: 20,
							onChange: function ( v ) {
								props.setAttributes( { height: v } );
							},
						} )
					)
				),
				preview( 'jobly/apply-form', a )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
