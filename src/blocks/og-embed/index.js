import './style.css';
import './editor.css';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { useState, useCallback } from '@wordpress/element';
import {
	Placeholder,
	TextControl,
	Button,
	Spinner,
	PanelBody,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

registerBlockType( 'og-fallback-embed/card', {
	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps();
		const [ inputUrl, setInput ] = useState( attributes.url || '' );
		const [ loading, setLoading ] = useState( false );
		const [ error, setError ] = useState( '' );

		const handleSubmit = useCallback( () => {
			const trimmed = inputUrl.trim();

			if ( ! trimmed ) {
				return;
			}

			try {
				new URL( trimmed );
			} catch {
				setError(
					__(
						'Please enter a valid URL.',
						'opengraph-fallback-embed'
					)
				);
				return;
			}

			setError( '' );
			setLoading( true );

			apiFetch( {
				path:
					'/og-fallback-embed/v1/preview?url=' +
					encodeURIComponent( trimmed ),
			} )
				.then( ( data ) => {
					setLoading( false );
					if ( data && data.title ) {
						setAttributes( { url: trimmed } );
					} else {
						setError(
							__(
								'No OpenGraph data found for this URL.',
								'opengraph-fallback-embed'
							)
						);
					}
				} )
				.catch( () => {
					setLoading( false );
					setError(
						__(
							'Could not fetch data. Check the URL and try again.',
							'opengraph-fallback-embed'
						)
					);
				} );
		}, [ inputUrl ] ); // eslint-disable-line react-hooks/exhaustive-deps

		const handleKeyDown = useCallback(
			( event ) => {
				if ( event.key === 'Enter' ) {
					event.preventDefault();
					handleSubmit();
				}
			},
			[ handleSubmit ]
		);

		const sidebar = (
			<InspectorControls>
				<PanelBody
					title={ __(
						'Embed Settings',
						'opengraph-fallback-embed'
					) }
				>
					<TextControl
						label={ __( 'URL', 'opengraph-fallback-embed' ) }
						value={ inputUrl }
						onChange={ ( val ) => {
							setInput( val );
							setError( '' );
						} }
						onKeyDown={ handleKeyDown }
					/>
					<Button
						variant="secondary"
						onClick={ handleSubmit }
						disabled={ loading }
						style={ { marginTop: '8px' } }
					>
						{ loading ? (
							<Spinner />
						) : (
							__( 'Update', 'opengraph-fallback-embed' )
						) }
					</Button>
				</PanelBody>
			</InspectorControls>
		);

		if ( attributes.url ) {
			return (
				<div { ...blockProps }>
					{ sidebar }
					<div className="og-fallback-embed-preview">
						<ServerSideRender
							block="og-fallback-embed/card"
							attributes={ attributes }
						/>
					</div>
				</div>
			);
		}

		return (
			<div { ...blockProps }>
				{ sidebar }
				<Placeholder
					icon="share-alt"
					label={ __(
						'OpenGraph Embed',
						'opengraph-fallback-embed'
					) }
					instructions={ __(
						'Paste a URL to embed it as a rich link card.',
						'opengraph-fallback-embed'
					) }
				>
					<div className="og-fallback-embed-input-row">
						<TextControl
							placeholder="https://example.com/article"
							value={ inputUrl }
							onChange={ ( val ) => {
								setInput( val );
								setError( '' );
							} }
							onKeyDown={ handleKeyDown }
							__nextHasNoMarginBottom
						/>
						<Button
							variant="primary"
							onClick={ handleSubmit }
							disabled={ loading }
						>
							{ loading ? (
								<Spinner />
							) : (
								__( 'Embed', 'opengraph-fallback-embed' )
							) }
						</Button>
					</div>
					{ error ? (
						<p className="og-fallback-embed-input-error">
							{ error }
						</p>
					) : null }
				</Placeholder>
			</div>
		);
	},

	save() {
		return null;
	},
} );
