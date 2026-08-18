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
