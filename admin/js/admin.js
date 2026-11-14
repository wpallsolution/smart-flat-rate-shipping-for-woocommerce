/* Smart Flat Rate Shipping admin: condition group repeater and simulator rows. */
( function ( $ ) {
	'use strict';

	var data = window.sfrsAdmin || { types: {}, i18n: {} };

	function initSelects() {
		$( document.body ).trigger( 'wc-enhanced-select-init' );
	}

	/* Show only the value inputs relevant to the chosen type and rebuild operators. */
	function syncCondition( $row, keepOp ) {
		var type = $row.find( '[data-role="type"]' ).val();
		var $op  = $row.find( '[data-role="op"]' );
		var info = data.types[ type ];
		if ( ! info ) { return; }
		if ( ! keepOp ) {
			var current = $op.val();
			$op.empty();
			$.each( info.ops, function ( key, label ) {
				$op.append( $( '<option>' ).val( key ).text( label ) );
			} );
			if ( info.ops[ current ] ) { $op.val( current ); }
		}
		$row.find( '[data-for]' ).each( function () {
			this.hidden = $( this ).attr( 'data-for' ) !== type;
		} );
		var showMin = ( type === 'products' || type === 'categories' ) && $op.val() !== 'none';
		$row.find( '[data-for-min]' ).each( function () { this.hidden = ! showMin; } );
	}

	function renumberGroups() {
		var $groups = $( '#sfrs-groups [data-group]' );
		$( '#sfrs-groups .sfrs-or' ).remove();
		$groups.each( function ( i ) {
			$( this ).find( '[data-group-number]' ).text( 'Group ' + ( i + 1 ) );
			if ( i > 0 ) {
				$( this ).before( '<p class="sfrs-or" aria-hidden="true"><span>' + data.i18n.orSeparator + '</span></p>' );
			}
		} );
	}

	/* Re-index names so groups[g][c] stay contiguous and unique. */
	function reindex() {
		$( '#sfrs-groups [data-group]' ).each( function ( g ) {
			$( this ).find( '[data-condition]' ).each( function ( c ) {
				$( this ).find( '[name]' ).each( function () {
					this.name = this.name.replace( /^groups\[[^\]]*\]\[[^\]]*\]/, 'groups[' + g + '][' + c + ']' );
				} );
				$( this ).find( '[id]' ).each( function () {
					this.id = this.id.replace( /^sfrs-c-[^-]+-[^-]+-/, 'sfrs-c-' + g + '-' + c + '-' );
				} );
				$( this ).find( 'label[for]' ).each( function () {
					var f = $( this ).attr( 'for' );
					$( this ).attr( 'for', f.replace( /^sfrs-c-[^-]+-[^-]+-/, 'sfrs-c-' + g + '-' + c + '-' ) );
				} );
			} );
		} );
	}

	function addCondition( $group ) {
		var html = $( '#sfrs-condition-template' ).html();
		var $row = $( html );
		$group.find( '[data-conditions]' ).append( $row );
		reindex();
		syncCondition( $row, false );
		initSelects();
	}

	$( function () {
		var $groups = $( '#sfrs-groups' );
		if ( $groups.length ) {
			$groups.find( '[data-condition]' ).each( function () { syncCondition( $( this ), true ); } );

			$( document ).on( 'change', '[data-role="type"]', function () { syncCondition( $( this ).closest( '[data-condition]' ), false ); } );
			$( document ).on( 'change', '[data-role="op"]', function () { syncCondition( $( this ).closest( '[data-condition]' ), true ); } );

			$( document ).on( 'click', '[data-action="add-group"]', function () {
				var $group = $( $( '#sfrs-group-template' ).html() );
				// The template group ships with one placeholder condition row.
				$groups.append( $group );
				reindex();
				renumberGroups();
				$group.find( '[data-condition]' ).each( function () { syncCondition( $( this ), false ); } );
				initSelects();
				$group.find( 'select' ).first().trigger( 'focus' );
			} );
			$( document ).on( 'click', '[data-action="add-condition"]', function () {
				addCondition( $( this ).closest( '[data-group]' ) );
			} );
			$( document ).on( 'click', '[data-action="remove-condition"]', function () {
				var $group = $( this ).closest( '[data-group]' );
				$( this ).closest( '[data-condition]' ).remove();
				if ( ! $group.find( '[data-condition]' ).length ) { $group.remove(); renumberGroups(); }
				reindex();
			} );
			$( document ).on( 'click', '[data-action="remove-group"]', function () {
				if ( window.confirm( data.i18n.confirmGroup ) ) {
					$( this ).closest( '[data-group]' ).remove();
					renumberGroups();
					reindex();
				}
			} );
			// Keep indexes unique even before the first interaction.
			reindex();
		}

		$( document ).on( 'submit', 'form[data-confirm="delete"]', function ( e ) {
			if ( ! window.confirm( data.i18n.confirmDelete ) ) { e.preventDefault(); }
		} );

		/* Simulator rows. */
		var $lines = $( '[data-lines]' );
		if ( $lines.length ) {
			$( document ).on( 'click', '[data-action="add-line"]', function () {
				var next = $lines.children().length;
				var html = $( '#sfrs-line-template' ).html().replace( /__I__/g, next );
				$lines.append( $( html ).find( 'tr' ) );
				initSelects();
			} );
			$( document ).on( 'click', '[data-action="remove-line"]', function () {
				if ( $lines.children().length > 1 ) { $( this ).closest( 'tr' ).remove(); } else {
					$( this ).closest( 'tr' ).find( 'select' ).val( null ).trigger( 'change' );
				}
			} );
		}
	} );
}( jQuery ) );
