window.QS = window.QS || {};

jQuery(function( $ ) {
	// Create the sortable options
	var sortableOptions = {
		tabSize:          16,
		cursor:           'move',
		handle:           'div',
		helper:           'clone',
		items:            'li',
		opacity:          0.6,
		placeholder:      'qs-placeholder',
		revert:           true,
		tolerance:        'pointer',
		toleranceElement: '> div',
	};

	// Create the nestedSortable options
	// a copy of the sortable options + an update event for the parent value
	var nestedSortableOptions = $.extend( {}, sortableOptions, {
		update: function( event, ui ) {
			var parent = ui.item.parent();
			if ( parent.prev( '.inner' ).length > 0 ) {
				parent = parent.prev( '.inner' ).find( '.qs-order-id' ).val();
			} else {
				parent = 0;
			}
			ui.item.find( '> .inner .qs-order-parent' ).val( parent );
		}
	} );

	// Apply the sortable options
	// to order managers NOT using the qs-nested class
	$( '.qs-order-manager' )
		.not( '.qs-nested' )
		.children( 'ol' )
		.sortable( sortableOptions );

	// Apply the nestedSotrable options
	// ONLY to order managers using the qs-nested class
	$( '.qs-order-manager' )
		.filter( '.qs-nested' )
		.children( 'ol' )
		.nestedSortable( nestedSortableOptions );

	// Quick Sort buttons
	$( '.qs-sort button' ).click(function(){
		var method = $(this).val();

		if ( method ) {
			QS.helpers.sortItems( '.qs-order-manager > ol', '> li', method );
		}
	});
});