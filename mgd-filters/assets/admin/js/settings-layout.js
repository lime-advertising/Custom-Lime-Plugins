( function ( $ ) {
	'use strict';

	var settings = window.mgdFiltersAdmin || {};

    function initLayoutManager( $form ) {
        var $orderInput = $form.find( '.mgd-card-layout__order-input' );
        var $list = $form.find( '.mgd-card-layout__list' );
        var toggleLabel = settings.l10n && settings.l10n.toggleSettings ? settings.l10n.toggleSettings : 'Toggle settings';

        if ( ! $orderInput.length || ! $list.length ) {
            return;
        }

        $form.find( '.mgd-card-layout__toggle-settings' ).attr( 'aria-label', toggleLabel );

		function updateOrder() {
			var order = $list
				.children()
				.map( function () {
					return $( this ).data( 'element' );
				} )
				.get()
				.join( ',' );

			$orderInput.val( order );
		}

		$list.sortable( {
			handle: '.mgd-card-layout__handle',
			update: updateOrder,
		} );

		updateOrder();

		$list.on( 'change', '.mgd-card-layout__visibility input[type="checkbox"]', function () {
			var $item = $( this ).closest( '.mgd-card-layout__item' );
			$item.toggleClass( 'is-disabled', ! this.checked );
		} );

		$list.on( 'click', '.mgd-card-layout__toggle-settings', function ( event ) {
			event.preventDefault();
			var $button = $( this );
			var $settingsPanel = $button.closest( '.mgd-card-layout__item' ).find( '.mgd-card-layout__settings' );
			var isHidden = $settingsPanel.attr( 'hidden' );

			if ( isHidden ) {
				$settingsPanel.hide().removeAttr( 'hidden' ).slideDown( 150 );
			} else {
				$settingsPanel.slideUp( 150, function () {
					$settingsPanel.attr( 'hidden', 'hidden' );
				} );
			}

			$button.attr( 'aria-expanded', isHidden ? 'true' : 'false' );
		} );
	}

	$( function () {
		$( '.mgd-card-layout' ).each( function () {
			initLayoutManager( $( this ) );
		} );
	} );
}( jQuery ) );
