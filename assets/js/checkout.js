/**
 * Custom Checkout – Frontend JS
 */

( function ( $ ) {
    'use strict';

    const { storeApiBase, apiBase, wpNonce, nonce, ajaxNonce, i18n, currency } = window.CCO || {};

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    const headers = () => ( {
        'Content-Type': 'application/json',
        'Nonce':        nonce,
    } );

    window.CCO.fmt = ( amount ) =>
        currency + parseFloat( amount ).toFixed( 2 );

    const fmt = window.CCO.fmt;

    function debounce( func, wait ) {
        let timeout;
        return function( ...args ) {
            clearTimeout( timeout );
            timeout = setTimeout( () => func.apply( this, args ), wait );
        };
    }

    async function apiCall( url, options = {} ) {
        const isStoreApi = url.includes( '/wc/store/v1/' );
        const fetchOptions = {
            credentials: 'same-origin',
            ...options,
            headers: {
                ...headers(),
                ...( ! isStoreApi ? { 'X-WP-Nonce': wpNonce, 'X-CCO-Nonce': ajaxNonce } : {} ),
                ...( options.headers || {} ),
            }
        };

        const res = await fetch( url, fetchOptions );
        const data = await res.json();

        if ( ! res.ok ) {
            const error = new Error( data.message || 'API Error' );
            error.response = data;
            throw error;
        }

        return data;
    }

    function showNotice( msg, type = 'error' ) {
        const $el = $( '#cco-notices' );
        $el.html(
            `<div class="cco-notice cco-notice--${type}">${msg}</div>`
        ).show();
        $( 'html, body' ).animate( { scrollTop: $el.offset().top - 20 }, 400 );
    }

    function clearNotice() {
        $( '#cco-notices' ).hide().html( '' );
    }

    function setLoading( loading ) {
        const $btn = $( '#cco-place-order' );
        $btn.prop( 'disabled', loading );
        if ( loading ) {
            $btn.find( '.cco-btn-text' ).text( 'Processing...' );
            $( '.cco-summary-card' ).addClass( 'cco-is-loading' );
        } else {
            $btn.find( '.cco-btn-text' ).text( 'Complete order' );
            $( '.cco-summary-card' ).removeClass( 'cco-is-loading' );
        }
    }

    // ---------------------------------------------------------------
    // Country → State
    // ---------------------------------------------------------------

    const statesCache = {}; // Keyed by country code — avoids duplicate fetches.

    async function populateStates( countryCode, $stateSelect ) {
        if ( ! countryCode ) return;

        // Show a loading placeholder while fetching.
        $stateSelect.empty().append( '<option value="">Loading…</option>' );
        const $row = $stateSelect.closest( '.cco-field' );

        try {
            // Use cached data if available.
            if ( ! statesCache[ countryCode ] ) {
                statesCache[ countryCode ] = await apiCall(
                    `${apiBase}/states?country=${encodeURIComponent( countryCode )}`
                );
            }

            const states = statesCache[ countryCode ];

            if ( ! states || states.length === 0 ) {
                // Country has no states — hide the dropdown entirely.
                $stateSelect.empty();
                $row.hide();
                return;
            }

            $row.show();
            $stateSelect.empty().append( '<option value="">Select state / province…</option>' );
            states.forEach( s => {
                $stateSelect.append(
                    `<option value="${s.code}">${s.name}</option>`
                );
            } );

        } catch ( err ) {
            console.error( 'Failed to load states:', err );
            $stateSelect.empty().append( '<option value="">State</option>' );
        }
    }

    // Delivery country → billing state dropdown.
    $( '#cco-country' ).on( 'change', function () {
        populateStates( $( this ).val(), $( '#cco-state' ) );
    } );

    // Billing-address panel country → billing state dropdown.
    $( '#cco-ship-country' ).on( 'change', function () {
        populateStates( $( this ).val(), $( '#cco-ship-state' ) );
    } );

    // ---------------------------------------------------------------
    // 1. Cart Summary
    // ---------------------------------------------------------------

    async function loadCart() {
        try {
            const data = await apiCall( `${apiBase}/cart-summary` );
            renderCartItems( data.items );
            renderCartTotals( data );
            $( '#cco-place-order' ).prop( 'disabled', data.item_count === 0 );
        } catch ( err ) {
            console.error( 'Error loading cart:', err );
        } finally {
            $( '.cco-summary-card' ).removeClass( 'cco-is-loading' );
        }
    }

    function renderCartItems( items ) {
        if ( ! items || ! items.length ) {
            $( '#cco-cart-items' ).html( '<p>Your cart is empty.</p>' );
            return;
        }

        const html = items.map( item => `
            <div class="cco-cart-item">
                <div class="cco-cart-item__img-wrapper">
                    <img src="${item.image}" alt="${item.name}" class="cco-cart-item__img">
                    <span class="cco-cart-item__qty-badge">${item.quantity}</span>
                </div>
                <div class="cco-cart-item__info">
                    <span class="cco-cart-item__name">${item.name}</span>
                    <span class="cco-cart-item__variant">${item.name}</span>
                </div>
                <span class="cco-cart-item__total">${fmt( item.line_total )}</span>
            </div>
        ` ).join( '' );
        $( '#cco-cart-items' ).html( html );
    }

    function renderCartTotals( data ) {
        let html = `
            <div class="cco-totals-row">
                <span>Subtotal &ndash; ${data.item_count} item${data.item_count !== 1 ? 's' : ''}</span>
                <span class="cco-weight-bold">${fmt( data.subtotal )}</span>
            </div>
        `;

        // Show each applied coupon as a discount row.
        if ( data.coupon_data && data.coupon_data.length ) {
            data.coupon_data.forEach( c => {
                html += `
                    <div class="cco-totals-row cco-discount-row">
                        <span>
                            Discount
                            <span class="cco-coupon-tag">
                                ${c.label}
                                <button type="button" class="cco-remove-coupon" data-code="${c.code}" title="Remove coupon">&#x2715;</button>
                            </span>
                        </span>
                        <span class="cco-discount-amount">-${fmt( c.amount )}</span>
                    </div>
                `;
            } );
        }

        html += `
            <div class="cco-totals-row">
                <span>Shipping</span>
                <span>${ data.shipping_total > 0 ? fmt(data.shipping_total) : 'Free' }</span>
            </div>
        `;

        // Render each tax rate as its own named row (GST, PST, Federal, etc.).
        // tax_lines comes from WC_Cart::get_tax_totals() which returns one entry
        // per distinct tax rate configured in WooCommerce → matches the cart page.
        if ( data.tax_enabled ) {
            if ( data.tax_lines && data.tax_lines.length ) {
                data.tax_lines.forEach( tax => {
                    const compoundClass = tax.is_compound ? ' cco-tax-compound' : '';
                    html += `
                        <div class="cco-totals-row cco-tax-row${compoundClass}">
                            <span class="cco-tax-label">${tax.label}</span>
                            <span>${fmt( tax.amount )}</span>
                        </div>
                    `;
                } );
            } else {
                // Fallback: no itemised lines yet (before calculate_totals runs).
                html += `
                    <div class="cco-totals-row cco-tax-row">
                        <span class="cco-tax-label">Estimated taxes</span>
                        <span>${fmt( data.tax_total || 0 )}</span>
                    </div>
                `;
            }
        }

        // Use server-reported currency code (strip symbol to just letters).
        const currencyCode = window.CCO.currencyCode || 'AUD';

        html += `
            <div class="cco-totals-row cco-totals-row--total">
                <span class="cco-total-label">Total</span>
                <span class="cco-total-price">
                    <small class="cco-currency-code">${currencyCode}</small>${fmt( data.total )}
                </span>
            </div>
        `;

        $( '#cco-cart-totals' ).html( html );

        // Update mobile summary total display.
        $( '#cco-mobile-total-display' ).html( fmt( data.total ) );

        // Console log the total price as requested by the user.
        console.log( 'Cart Summary Loaded. Total Price: ', data.total );
    }


    /**
     * Handle Mobile Summary Toggle
     */
    $( document ).on( 'click', '#cco-mobile-summary-toggle', function () {
        const $header = $( this );
        const $card = $( '.cco-summary-card' );
        const $text = $header.find( '.cco-mobile-summary-text' );
        
        $card.stop().slideToggle( 300, function () {
            if ( $card.is( ':visible' ) ) {
                $text.text( 'Hide order summary' );
                $header.addClass( 'cco-is-active' );
                $header.find( '.cco-mobile-summary-arrow' ).text( '▲' );
            } else {
                $text.text( 'Show order summary' );
                $header.removeClass( 'cco-is-active' );
                $header.find( '.cco-mobile-summary-arrow' ).text( '▼' );
            }
        } );
    } );

    // Initialize toggle state on load for mobile
    if ( $( window ).width() <= 991 ) {
        $( '#cco-mobile-summary-toggle' ).addClass( 'cco-is-active' );
        $( '#cco-mobile-summary-toggle .cco-mobile-summary-text' ).text( 'Hide order summary' );
        $( '#cco-mobile-summary-toggle .cco-mobile-summary-arrow' ).text( '▲' );
        $( '.cco-summary-card' ).show();
    }

    // Prevent issues when switching between media widths (resizing)
    $( window ).on( 'resize', function () {
        if ( $( window ).width() > 991 ) {
            $( '.cco-summary-card' ).css( 'display', '' );
        } else {
            // Restore visibility state for mobile based on toggle class
            if ( $( '#cco-mobile-summary-toggle' ).hasClass( 'cco-is-active' ) ) {
                $( '.cco-summary-card' ).css( 'display', 'block' );
            } else {
                $( '.cco-summary-card' ).css( 'display', 'none' );
            }
        }
    } );



    // ---------------------------------------------------------------
    // 2. Coupon
    // ---------------------------------------------------------------

    $( '#cco-apply-coupon' ).on( 'click', async function () {
        const code = $( '#cco-coupon-input' ).val().trim();
        if ( ! code ) return;
        clearNotice();
        const $btn = $( '#cco-apply-coupon' );
        $btn.prop( 'disabled', true ).text( 'Applying…' );
        try {
            const res = await apiCall( `${apiBase}/apply-coupon`, {
                method: 'POST',
                body: JSON.stringify( { code } )
            } );
            showNotice( res.message || 'Coupon applied!', 'success' );
            $( '#cco-coupon-input' ).val( '' );
            loadCart();
        } catch ( err ) {
            showNotice( err.message );
        } finally {
            $btn.prop( 'disabled', false ).text( 'Apply' );
        }
    } );

    // Remove coupon (delegated – button is injected dynamically).
    $( '#cco-cart-totals' ).on( 'click', '.cco-remove-coupon', async function () {
        const code = $( this ).data( 'code' );
        clearNotice();
        try {
            await apiCall( `${apiBase}/remove-coupon`, {
                method: 'POST',
                body: JSON.stringify( { code } )
            } );
            showNotice( 'Coupon removed.', 'success' );
            loadCart();
        } catch ( err ) {
            showNotice( err.message );
        }
    } );

    // ---------------------------------------------------------------
    // 3. Address Sync
    // ---------------------------------------------------------------

    function isShipToDifferent() {
        return $( '#cco-ship-to-different' ).is( ':checked' );
    }

    function collectAddress() {
        return {
            first_name: $( '#cco-first-name' ).val(),
            last_name:  $( '#cco-last-name'  ).val(),
            address_1:  $( '#cco-address1'   ).val(),
            address_2:  $( '#cco-address2'   ).val(),
            city:       $( '#cco-city'        ).val(),
            state:      $( '#cco-state'       ).val(),
            postcode:   $( '#cco-postcode'    ).val(),
            country:    $( '#cco-country'     ).val(),
            email:      $( '#cco-email'       ).val(),
            phone:      $( '#cco-phone'       ).val(),
        };
    }

    function collectShippingAddress() {
        if ( ! isShipToDifferent() ) {
            // Checkbox not ticked — shipping = billing.
            return collectAddress();
        }
        return {
            first_name: $( '#cco-ship-first-name' ).val() || $( '#cco-first-name' ).val(),
            last_name:  $( '#cco-ship-last-name'  ).val() || $( '#cco-last-name'  ).val(),
            address_1:  $( '#cco-ship-address1'   ).val(),
            address_2:  $( '#cco-ship-address2'   ).val(),
            city:       $( '#cco-ship-city'        ).val(),
            state:      $( '#cco-ship-state'       ).val(),
            postcode:   $( '#cco-ship-postcode'    ).val(),
            country:    $( '#cco-ship-country'     ).val(),
            email:      $( '#cco-email'            ).val(),
            phone:      $( '#cco-phone'            ).val(),
        };
    }

    // Toggle the billing-address panel.
    $( '#cco-ship-to-different' ).on( 'change', function () {
        $( '#cco-shipping-fields' ).slideToggle( 300 );
        syncAddress(); // Re-sync so WC knows the new shipping address.
    } );

    const syncAddress = debounce( async function() {
        try {
            $( '.cco-summary-card' ).addClass( 'cco-is-loading' );
            await apiCall( `${storeApiBase}/cart/update-customer`, {
                method: 'POST',
                body: JSON.stringify( {
                    billing_address:  collectAddress(),
                    shipping_address: collectShippingAddress(),
                } )
            } );
            loadCart();
        } catch ( e ) {
            console.error( 'Address sync failed:', e );
        }
    }, 800 );

    $( document ).on( 'input change', '.cco-form-column input, .cco-form-column select', syncAddress );

    // ---------------------------------------------------------------
    // 4. Timer & Payment Toggles
    // ---------------------------------------------------------------

    function startTimer( durationSeconds ) {
        let timer = durationSeconds, minutes, seconds;
        const $display = $( '#cco-countdown' );
        const interval = setInterval( function () {
            minutes = parseInt( timer / 60, 10 );
            seconds = parseInt( timer % 60, 10 );

            minutes = minutes < 10 ? "0" + minutes : minutes;
            seconds = seconds < 10 ? "0" + seconds : seconds;

            $display.text( minutes + "m " + seconds + "s" );

            if ( --timer < 0 ) {
                clearInterval( interval );
                $display.text( "Expired" );
            }
        }, 1000 );
    }

    $( document ).on( 'change', 'input[name="payment_method"]', function() {
        const val = $( this ).val();
        $( '.cco-payment-method' ).removeClass( 'cco-payment-method--active' );
        $( this ).closest( '.cco-payment-method' ).addClass( 'cco-payment-method--active' );
        
        if ( val === 'bankful' ) {
            $( '#cco-card-element' ).slideDown();
        } else {
            $( '#cco-card-element' ).slideUp();
        }
    } );

    // ---------------------------------------------------------------
    // 5. Place Order
    // ---------------------------------------------------------------

    $( '#cco-place-order' ).on( 'click', async function () {
        clearNotice();
        setLoading( true );
        
        const billing = collectAddress();
        const requiredFields = {
            'cco-first-name': 'First name',
            'cco-last-name':  'Last name',
            'cco-email':      'Email address',
            'cco-address1':   'Address',
            'cco-city':       'City',
            'cco-state':      'State / Province',
            'cco-postcode':   'ZIP / Postcode',
            'cco-phone':      'Phone number'
        };

        let missing = [];
        for ( const [ id, label ] of Object.entries( requiredFields ) ) {
            if ( ! $( `#${id}` ).val().trim() ) {
                missing.push( label );
            }
        }

        if ( missing.length > 0 ) {
            showNotice( 'Please fill in the required fields: ' + missing.join( ', ' ) );
            setLoading( false );
            return;
        }

        const paymentMethod = $( 'input[name="payment_method"]:checked' ).val();
        const payload = {
            payment_method: paymentMethod,
            billing:        collectAddress(),
            shipping:       collectShippingAddress(),
            payment_data:   {},
        };

        // If paying with Bankful, collect the card data.
        if ( paymentMethod === 'bankful' ) {
            payload.payment_data = {
                bankful_card_num:    $( '#bankful-card-num' ).val().replace(/\s/g, ''),
                bankful_card_expiry: $( '#bankful-card-expiry' ).val().trim(),
                bankful_card_cvc:    $( '#bankful-card-cvc' ).val().trim(),
            };
        }

        try {
            const data = await apiCall( `${apiBase}/place-order`, {
                method: 'POST',
                body:   JSON.stringify( payload ),
            } );
            window.location.href = data.redirect_url;
        } catch ( err ) {
            console.error( 'Checkout Failed:', err );
            if ( err.response && err.response.data && err.response.data.errors ) {
                console.table( err.response.data.errors );
            }
            showNotice( err.message || 'Order failed. Please check your details.' );
            setLoading( false );
        }
    } );

    // ---------------------------------------------------------------
    // Init
    // ---------------------------------------------------------------

    $( function () {
        loadCart();
        startTimer( 300 ); // 5 minutes

        // Populate states for the default (pre-selected) country on both dropdowns.
        const defaultCountry = $( '#cco-country' ).val();
        if ( defaultCountry ) {
            populateStates( defaultCountry, $( '#cco-state' ) );
        }
        // Billing address panel starts hidden; populate when it becomes visible.
        $( '#cco-ship-to-different' ).one( 'change', function () {
            if ( $( this ).is( ':checked' ) ) {
                populateStates( $( '#cco-ship-country' ).val(), $( '#cco-ship-state' ) );
            }
        } );
    } );

} )( jQuery );
