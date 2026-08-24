/* Admin scripts for Food Menu */
( function ( $ ) {
	'use strict';

	function initSortable() {
		$( '#fmp-variations-rows' ).sortable( {
			handle: '.fmp-drag-handle',
			axis: 'y',
			items: '.fmp-variation-row',
		} );
	}

	function initMediaPicker( buttonSelector, title, libraryType, onSelect ) {
		$( document ).on( 'click', buttonSelector, function ( e ) {
			e.preventDefault();
			var frame = wp.media( {
				title: title,
				button: { text: 'Use this file' },
				library: { type: libraryType },
				multiple: false,
			} );
			frame.on( 'select', function () {
				onSelect( frame.state().get( 'selection' ).first().toJSON() );
			} );
			frame.open();
		} );
	}

	function initTermMediaPicker( buttonSelector, title, libraryType, inputSelector, previewSelector, removeSelector ) {
		initMediaPicker( buttonSelector, title, libraryType, function ( attachment ) {
			$( inputSelector ).val( attachment.id || attachment.url );
			if ( previewSelector && attachment.url ) {
				$( previewSelector ).html( '<img src="' + attachment.url + '" alt="" />' );
			}
			$( removeSelector ).prop( 'disabled', false );
		} );
	}

	initTermMediaPicker( '.fmp-term-select-image', 'Choose Term Image', 'image', '#fmp_term_image_id', '#fmp-term-image-preview', '.fmp-term-remove-image' );
	initTermMediaPicker( '.fmp-term-select-poster', 'Choose Term Video Poster', 'image', '#fmp_term_video_poster_id', '#fmp-term-poster-preview', '.fmp-term-remove-poster' );
	initMediaPicker( '.fmp-term-select-video', 'Choose Term Video', [ 'video/mp4', 'video/webm' ], function ( attachment ) {
		$( '#fmp_term_video_url' ).val( attachment.url );
		$( '.fmp-term-remove-video' ).prop( 'disabled', false );
	} );

	$( document ).on( 'click', '.fmp-term-remove-image', function ( e ) {
		e.preventDefault();
		$( '#fmp_term_image_id' ).val( '' );
		$( '#fmp-term-image-preview' ).empty();
		$( this ).prop( 'disabled', true );
	} );

	$( document ).on( 'click', '.fmp-term-remove-poster', function ( e ) {
		e.preventDefault();
		$( '#fmp_term_video_poster_id' ).val( '' );
		$( '#fmp-term-poster-preview' ).empty();
		$( this ).prop( 'disabled', true );
	} );

	$( document ).on( 'click', '.fmp-term-remove-video', function ( e ) {
		e.preventDefault();
		$( '#fmp_term_video_url' ).val( '' );
		$( this ).prop( 'disabled', true );
	} );

	initMediaPicker( '#fmp-select-video', 'Choose Item Video', [ 'video/mp4', 'video/webm' ], function ( attachment ) {
		$( '#fmp_video_url' ).val( attachment.url );
		$( '#fmp-remove-video' ).prop( 'disabled', false );
	} );

	initMediaPicker( '#fmp-select-video-poster', 'Choose Video Poster', 'image', function ( attachment ) {
		$( '#fmp_video_poster_id' ).val( attachment.id );
		$( '#fmp-video-poster-preview' ).html( '<img src="' + attachment.url + '" alt="" />' );
		$( '#fmp-remove-video-poster' ).prop( 'disabled', false );
	} );

	$( document ).on( 'click', '#fmp-remove-video', function ( e ) {
		e.preventDefault();
		$( '#fmp_video_url' ).val( '' );
		$( this ).prop( 'disabled', true );
	} );

	$( document ).on( 'click', '#fmp-remove-video-poster', function ( e ) {
		e.preventDefault();
		$( '#fmp_video_poster_id' ).val( '' );
		$( '#fmp-video-poster-preview' ).empty();
		$( this ).prop( 'disabled', true );
	} );

	$( document ).on( 'click', '#fmp-add-variation', function ( e ) {
		e.preventDefault();
		var template = document.getElementById( 'fmp-variation-row-template' );
		if ( ! template ) {
			return;
		}
		$( '#fmp-variations-rows' ).append( template.innerHTML );
	} );

	$( document ).on( 'click', '.fmp-remove-variation', function ( e ) {
		e.preventDefault();
		var $rows = $( '#fmp-variations-rows .fmp-variation-row' );
		if ( $rows.length <= 1 ) {
			$( this ).closest( '.fmp-variation-row' ).find( 'input' ).val( '' );
			return;
		}
		$( this ).closest( '.fmp-variation-row' ).remove();
	} );

	$( function () {
		initSortable();
	} );
} )( jQuery );
