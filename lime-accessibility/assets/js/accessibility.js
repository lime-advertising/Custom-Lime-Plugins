/**
 * Accessibility toolbar script
 *
 * Handles user interactions with the accessibility toolbar. Settings are
 * persisted across page loads using localStorage. CSS classes are toggled on
 * the <body> element to activate various modes such as high contrast,
 * grayscale, negative contrast, underline links, highlight links and
 * dyslexic-friendly font. Font size adjustments are achieved by modifying
 * the root font size percentage.
 */
( function( $ ) {
    'use strict';

    var storageKey = 'limeAccessibilitySettings';

    /**
     * Retrieve settings from localStorage or return defaults.
     *
     * @returns {Object}
     */
    function getSettings() {
        var settings = localStorage.getItem( storageKey );
        if ( settings ) {
            try {
                settings = JSON.parse( settings );
            } catch ( e ) {
                settings = {};
            }
        } else {
            settings = {};
        }
        return $.extend( {
            fontScale: 1,
            highContrast: false,
            grayscale: false,
            negativeContrast: false,
            underlineLinks: false,
            highlightLinks: false,
            dyslexicFont: false
        }, settings );
    }

    /**
     * Save settings to localStorage.
     *
     * @param {Object} settings
     */
    function saveSettings( settings ) {
        localStorage.setItem( storageKey, JSON.stringify( settings ) );
    }

    /**
     * Apply settings to the document.
     *
     * @param {Object} settings
     */
    function applySettings( settings ) {
        // Font scaling: limit scale between 0.5 and 2
        var scale = Math.max( 0.5, Math.min( 2, settings.fontScale ) );
        document.documentElement.style.fontSize = ( scale * 100 ) + '%';

        var body = document.body;
        if ( settings.highContrast ) {
            body.classList.add( 'lime-high-contrast' );
        } else {
            body.classList.remove( 'lime-high-contrast' );
        }
        if ( settings.grayscale ) {
            body.classList.add( 'lime-grayscale' );
        } else {
            body.classList.remove( 'lime-grayscale' );
        }
        if ( settings.negativeContrast ) {
            body.classList.add( 'lime-negative-contrast' );
        } else {
            body.classList.remove( 'lime-negative-contrast' );
        }
        if ( settings.underlineLinks ) {
            body.classList.add( 'lime-underline-links' );
        } else {
            body.classList.remove( 'lime-underline-links' );
        }
        if ( settings.highlightLinks ) {
            body.classList.add( 'lime-highlight-links' );
        } else {
            body.classList.remove( 'lime-highlight-links' );
        }
        if ( settings.dyslexicFont ) {
            body.classList.add( 'lime-dyslexic-font' );
        } else {
            body.classList.remove( 'lime-dyslexic-font' );
        }
    }

    /**
     * Initialization when DOM is ready.
     */
    $( document ).ready( function() {
        var settings = getSettings();
        applySettings( settings );

        var $toggleButton = $( '#lime-accessibility-toggle' );
        var $toolbar      = $( '#lime-accessibility-toolbar' );

        if ( ! $toggleButton.length || ! $toolbar.length ) {
            return;
        }

        // Open/close toolbar
        $toggleButton.on( 'click', function() {
            var expanded = $( this ).attr( 'aria-expanded' ) === 'true';
            expanded     = ! expanded;
            $( this ).attr( 'aria-expanded', expanded );
            $toolbar.toggleClass( 'open', expanded );
        } );

        // Clicking outside toolbar closes it
        $( document ).on( 'click', function( event ) {
            if ( ! $( event.target ).closest( '#lime-accessibility-toolbar, #lime-accessibility-toggle' ).length ) {
                $toggleButton.attr( 'aria-expanded', 'false' );
                $toolbar.removeClass( 'open' );
            }
        } );

        // Handle actions
        $toolbar.on( 'click', 'button[data-action]', function() {
            var action = $( this ).data( 'action' );
            switch ( action ) {
                case 'increase-font':
                    settings.fontScale = ( settings.fontScale + 0.1 ).toFixed( 2 );
                    break;
                case 'decrease-font':
                    settings.fontScale = ( settings.fontScale - 0.1 ).toFixed( 2 );
                    break;
                case 'reset-font':
                    settings.fontScale = 1;
                    break;
                case 'toggle-high-contrast':
                    settings.highContrast = ! settings.highContrast;
                    break;
                case 'toggle-grayscale':
                    settings.grayscale = ! settings.grayscale;
                    break;
                case 'toggle-negative-contrast':
                    settings.negativeContrast = ! settings.negativeContrast;
                    break;
                case 'toggle-underline-links':
                    settings.underlineLinks = ! settings.underlineLinks;
                    break;
                case 'toggle-highlight-links':
                    settings.highlightLinks = ! settings.highlightLinks;
                    break;
                case 'toggle-dyslexic-font':
                    settings.dyslexicFont = ! settings.dyslexicFont;
                    break;
                case 'reset-all':
                    settings = {
                        fontScale: 1,
                        highContrast: false,
                        grayscale: false,
                        negativeContrast: false,
                        underlineLinks: false,
                        highlightLinks: false,
                        dyslexicFont: false
                    };
                    break;
                default:
                    break;
            }
            applySettings( settings );
            saveSettings( settings );
        } );
    } );
} )( jQuery );