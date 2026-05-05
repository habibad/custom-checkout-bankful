<?php
/**
 * CCO_API
 *
 * Registers custom REST endpoints under /wp-json/cco/v1/
 * These are separate from the WC Store API and handle plugin-specific operations.
 *
 * Endpoints:
 *   GET  /wp-json/cco/v1/cart-summary     — fetch cart data via Store API (server-side)
 *   POST /wp-json/cco/v1/place-order      — create order via WC Store API checkout
 */

defined( 'ABSPATH' ) || exit;

class CCO_API {

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );

        // Add static 10% GST fee to the cart.
        // This ensures the tax is calculated correctly for both the UI and the final order.
        add_action( 'woocommerce_cart_calculate_fees', [ __CLASS__, 'add_static_tax_fee' ] );
    }

    /**
     * Adds a flat 10% GST fee based on the taxable subtotal.
     */
    public static function add_static_tax_fee( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        $taxable_amount = (float) $cart->get_subtotal() - (float) $cart->get_discount_total();
        if ( $taxable_amount > 0 ) {
            $gst_amount = round( $taxable_amount * 0.10, 2 );
            $cart->add_fee( __( 'GST (10%)', 'custom-checkout' ), $gst_amount );
        }
    }

    public static function register_routes() {

        register_rest_route( 'cco/v1', '/cart-summary', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_cart_summary' ],
            'permission_callback' => '__return_true',
        ] );

        // Returns states/provinces for a given country code.
        // GET /wp-json/cco/v1/states?country=AU
        register_rest_route( 'cco/v1', '/states', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_states' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'country' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );

        register_rest_route( 'cco/v1', '/apply-coupon', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'apply_coupon' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'cco/v1', '/remove-coupon', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'remove_coupon' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'cco/v1', '/place-order', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'place_order' ],
            'permission_callback' => [ __CLASS__, 'verify_nonce' ],
            'args'                => self::place_order_args(),
        ] );

        // GET available shipping methods
        register_rest_route( 'cco/v1', '/shipping-methods', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_shipping_methods' ],
            'permission_callback' => '__return_true',
        ] );

        // POST select shipping method
        register_rest_route( 'cco/v1', '/update-shipping', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'update_shipping_method' ],
            'permission_callback' => '__return_true',
        ] );
    }

    // ------------------------------------------------------------------
    // Permission callback
    // ------------------------------------------------------------------

    public static function verify_nonce( WP_REST_Request $request ): bool {
        $nonce = $request->get_header( 'X-CCO-Nonce' )
               ?: $request->get_param( '_cco_nonce' );
        return (bool) wp_verify_nonce( $nonce, 'cco_ajax' );
    }

    // ------------------------------------------------------------------
    // GET /cco/v1/states?country=AU
    // ------------------------------------------------------------------

    public static function get_states( WP_REST_Request $request ): WP_REST_Response {
        $country = strtoupper( $request->get_param( 'country' ) );
        $all_states = WC()->countries->get_states( $country );

        if ( empty( $all_states ) || ! is_array( $all_states ) ) {
            // Country has no states (e.g. Singapore) — return empty array.
            return new WP_REST_Response( [], 200 );
        }

        $states = [];
        foreach ( $all_states as $code => $name ) {
            $states[] = [
                'code' => $code,
                'name' => html_entity_decode( $name ),
            ];
        }

        return new WP_REST_Response( $states, 200 );
    }

    // ------------------------------------------------------------------
    // GET /cco/v1/cart-summary
    // ------------------------------------------------------------------

    public static function get_cart_summary( WP_REST_Request $request ): WP_REST_Response {
        // Ensure WooCommerce cart is initialized.
        if ( is_null( WC()->cart ) ) {
            if ( ! function_exists( 'wc_load_cart' ) ) {
                include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
            }
            wc_load_cart();
        }

        // Ensure session is loaded from cookie.
        if ( ! is_null( WC()->session ) && ! WC()->session->has_session() ) {
            WC()->session->init_session_cookie();
        }

        $session_data = ! is_null( WC()->session )
            ? (array) WC()->session->get( 'customer', [] )
            : [];

        if ( ! is_null( WC()->cart ) ) {
            if ( 0 === WC()->cart->get_cart_contents_count() ) {
                WC()->cart->get_cart_from_session();
            }

            // Recalculate totals to ensure items/discounts are fresh.
            WC()->cart->calculate_totals();
        }

        $cart = WC()->cart;

        if ( is_null( $cart ) ) {
            return new WP_REST_Response( [ 'message' => 'Could not initialize WooCommerce cart.' ], 500 );
        }

        $items = [];
        foreach ( $cart->get_cart() as $item_key => $item ) {
            $product  = $item['data'];
            $items[]  = [
                'key'        => $item_key,
                'product_id' => $item['product_id'],
                'name'       => $product->get_name(),
                'quantity'   => $item['quantity'],
                'price'      => (float) $product->get_price(),
                'line_total' => (float) $item['line_total'],
                'image'      => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: wc_placeholder_img_src(),
            ];
        }

        // Shipping rates.
        $shipping_rates = [];
        if ( $cart->needs_shipping() ) {
            $packages = $cart->get_shipping_packages();
            WC()->shipping()->calculate_shipping( $packages );

            $shipping_packages = WC()->shipping()->get_packages();
            foreach ( $shipping_packages as $i => $package ) {
                if ( isset( $package['rates'] ) ) {
                    foreach ( $package['rates'] as $rate_id => $rate ) {
                        $shipping_rates[] = [
                            'id'       => $rate_id,
                            'label'    => $rate->get_label(),
                            'cost'     => (float) $rate->get_cost(),
                            'selected' => ( $rate_id === current( $cart->get_shipping_methods() ) ),
                        ];
                    }
                }
            }
        }

        // Build applied coupon data (code + discount amount).
        $coupon_data = [];
        foreach ( $cart->get_applied_coupons() as $coupon_code ) {
            $coupon        = new WC_Coupon( $coupon_code );
            $coupon_data[] = [
                'code'   => $coupon_code,
                'label'  => strtoupper( $coupon_code ),
                'amount' => (float) $cart->get_coupon_discount_amount( $coupon_code, $cart->display_prices_including_tax() ),
            ];
        }

        // Build individual tax lines from WooCommerce's own tax totals.
        // get_tax_totals() returns one entry per distinct tax rate configured in
        // WooCommerce (e.g. "GST 10%", "PST 5%", "Federal 2%"), each with its
        // admin label and calculated amount. This is the same data WC uses on
        // the cart page to display itemised taxes.
        // Reuse $session_data fetched in the tax-location block above.
        $has_address = ! empty( $session_data['country'] );
        $prefix      = $has_address ? '' : 'Estimated ';


        // ── Tax/GST Handling ───────────────────────────────────────────
        // We now use a native WC fee (see add_static_tax_fee) to handle the 
        // 10% GST. This ensures it's included in WC's own totals and order 
        // creation. We display it in the 'tax_lines' array for the UI.
        $tax_lines = [];
        foreach ( $cart->get_fees() as $fee ) {
            $tax_lines[] = [
                'label'       => $prefix . $fee->name,
                'amount'      => (float) $fee->total,
                'is_compound' => false,
            ];
        }

        return new WP_REST_Response( [
            'items'              => $items,
            'subtotal'           => (float) $cart->get_subtotal(),
            'discount_total'     => (float) $cart->get_discount_total(),
            'shipping_total'     => (float) $cart->get_shipping_total(),
            'tax_total'          => (float) array_sum( wp_list_pluck( $tax_lines, 'amount' ) ),
            'tax_lines'          => $tax_lines,
            'tax_enabled'        => true,
            'total'              => (float) $cart->get_total( 'edit' ),
            'currency_symbol'    => get_woocommerce_currency_symbol(),
            'needs_shipping'     => $cart->needs_shipping(),
            'shipping_rates'     => $shipping_rates,
            'coupons'            => $cart->get_applied_coupons(),
            'coupon_data'        => $coupon_data,
            'item_count'         => $cart->get_cart_contents_count(),
            'prices_include_tax' => wc_prices_include_tax(),
        ], 200 );
    }


    // ------------------------------------------------------------------
    // POST /cco/v1/apply-coupon
    // ------------------------------------------------------------------

    public static function apply_coupon( WP_REST_Request $request ): WP_REST_Response {
        // Ensure cart is available.
        if ( is_null( WC()->cart ) ) {
            if ( ! function_exists( 'wc_load_cart' ) ) {
                include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
            }
            wc_load_cart();
        }

        if ( ! is_null( WC()->session ) && ! WC()->session->has_session() ) {
            WC()->session->init_session_cookie();
        }

        $body = $request->get_json_params();
        $code = isset( $body['code'] ) ? sanitize_text_field( $body['code'] ) : '';

        if ( empty( $code ) ) {
            return new WP_REST_Response( [ 'message' => 'Coupon code is required.' ], 400 );
        }

        // Check if already applied.
        if ( WC()->cart->has_discount( $code ) ) {
            return new WP_REST_Response( [ 'message' => 'Coupon "' . strtoupper( $code ) . '" is already applied.' ], 400 );
        }

        // Suppress WC notices – capture them instead.
        $result = WC()->cart->apply_coupon( $code );

        if ( ! $result ) {
            $notices = wc_get_notices( 'error' );
            $msg     = ! empty( $notices ) ? wp_strip_all_tags( $notices[0]['notice'] ) : 'Invalid coupon code.';
            wc_clear_notices();
            return new WP_REST_Response( [ 'message' => $msg ], 400 );
        }

        wc_clear_notices();
        WC()->cart->calculate_totals();

        return new WP_REST_Response( [ 'success' => true, 'message' => 'Coupon applied!' ], 200 );
    }

    // ------------------------------------------------------------------
    // POST /cco/v1/remove-coupon
    // ------------------------------------------------------------------

    public static function remove_coupon( WP_REST_Request $request ): WP_REST_Response {
        if ( is_null( WC()->cart ) ) {
            if ( ! function_exists( 'wc_load_cart' ) ) {
                include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
            }
            wc_load_cart();
        }

        if ( ! is_null( WC()->session ) && ! WC()->session->has_session() ) {
            WC()->session->init_session_cookie();
        }

        $body = $request->get_json_params();
        $code = isset( $body['code'] ) ? sanitize_text_field( $body['code'] ) : '';

        if ( empty( $code ) ) {
            return new WP_REST_Response( [ 'message' => 'Coupon code is required.' ], 400 );
        }

        WC()->cart->remove_coupon( $code );
        wc_clear_notices();
        WC()->cart->calculate_totals();

        return new WP_REST_Response( [ 'success' => true, 'message' => 'Coupon removed.' ], 200 );
    }

    // ------------------------------------------------------------------
    // POST /cco/v1/place-order
    // Proxies to WC Store API POST /wc/store/v1/checkout
    // ------------------------------------------------------------------

    public static function place_order( WP_REST_Request $request ): WP_REST_Response {
        $body = $request->get_json_params();

        if ( empty( $body ) ) {
            return new WP_REST_Response( [ 'message' => 'Empty request body.' ], 400 );
        }

        $billing  = self::sanitize_address( $body['billing'] ?? [] );
        $shipping = self::sanitize_address( $body['shipping'] ?? $body['billing'] ?? [] );

        // Sync address to WC session so shipping/tax calculate correctly.
        WC()->customer->set_props( [
            'billing_first_name'  => $billing['first_name'],
            'billing_last_name'   => $billing['last_name'],
            'billing_address_1'   => $billing['address_1'],
            'billing_address_2'   => $billing['address_2'],
            'billing_city'        => $billing['city'],
            'billing_state'       => $billing['state'],
            'billing_postcode'    => $billing['postcode'],
            'billing_country'     => $billing['country'],
            'billing_email'       => $billing['email'],
            'billing_phone'       => $billing['phone'],
            'shipping_first_name' => $shipping['first_name'],
            'shipping_last_name'  => $shipping['last_name'],
            'shipping_address_1'  => $shipping['address_1'],
            'shipping_address_2'  => $shipping['address_2'],
            'shipping_city'       => $shipping['city'],
            'shipping_state'      => $shipping['state'],
            'shipping_postcode'   => $shipping['postcode'],
            'shipping_country'    => $shipping['country'],
        ] );
        WC()->customer->save();

        // If shipping is required but none chosen (static checkout), auto-select first available.
        if ( WC()->cart->needs_shipping() ) {
            WC()->cart->calculate_shipping();
            $packages = WC()->cart->get_shipping_packages();
            $rates    = WC()->shipping()->calculate_shipping_for_package( current( $packages ) );
            if ( ! empty( $rates['rates'] ) ) {
                $first_rate = current( $rates['rates'] );
                WC()->session->set( 'chosen_shipping_methods', [ $first_rate->id ] );
            }
        }

        WC()->cart->calculate_totals();

        // Build payload for WC Store API.
        $payload = [
            'payment_method'  => sanitize_text_field( $body['payment_method'] ?? 'bankful' ),
            'billing_address' => $billing,
            'shipping_address'=> $shipping,
            'customer_note'   => sanitize_textarea_field( $body['order_note'] ?? '' ),
        ];

        // Store payment data in a global so our gateway can pick it up 
        // during the internal rest_do_request call.
        $GLOBALS['cco_payment_data'] = $body['payment_data'] ?? [];

        // Internal WC Store API request.
        $store_request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
        $store_request->set_header( 'Nonce', wp_create_nonce( 'wc_store_api' ) );
        $store_request->set_body( wp_json_encode( $payload ) );
        $store_request->set_header( 'Content-Type', 'application/json' );

        $response = rest_do_request( $store_request );
        $data     = rest_get_server()->response_to_data( $response, false );

        if ( $response->is_error() ) {
            return new WP_REST_Response( [
                'message' => $data['message'] ?? 'Order failed.',
                'code'    => $data['code'] ?? 'api_error',
                'data'    => $data,
            ], $response->get_status() );
        }

        return new WP_REST_Response( [
            'order_id'    => $data['order_id'] ?? null,
            'order_key'   => $data['order_key'] ?? null,
            'redirect_url'=> $data['payment_result']['redirect_url'] ?? wc_get_endpoint_url( 'order-received', $data['order_id'] ?? '', wc_get_page_permalink( 'checkout' ) ),
            'status'      => $data['status'] ?? 'pending',
        ], 200 );
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    // GET /cco/v1/shipping-methods
    // ------------------------------------------------------------------

    public static function get_shipping_methods( WP_REST_Request $request ): WP_REST_Response {
        // Ensure session is initialized.
        if ( ! is_null( WC()->session ) && ! WC()->session->has_session() ) {
            WC()->session->init_session_cookie();
        }

        $store_request = new WP_REST_Request( 'GET', '/wc/store/v1/cart/shipping-rates' );
        $response = rest_do_request( $store_request );
        $data     = rest_get_server()->response_to_data( $response, false );
        
        if ( $response->is_error() ) {
            return new WP_REST_Response( [
                'message' => $data['message'] ?? 'Could not load shipping methods.',
                'code'    => $data['code'] ?? 'shipping_error',
                'data'    => $data
            ], $response->get_status() );
        }

        return new WP_REST_Response( $data, 200 );
    }

    // ------------------------------------------------------------------
    // POST /cco/v1/update-shipping
    // ------------------------------------------------------------------

    public static function update_shipping_method( WP_REST_Request $request ): WP_REST_Response {
        $body = $request->get_json_params();
        $rate_id = sanitize_text_field( $body['rate_id'] ?? '' );

        if ( empty( $rate_id ) ) {
            return new WP_REST_Response( [ 'message' => 'Rate ID is required.' ], 400 );
        }

        $store_request = new WP_REST_Request( 'POST', '/wc/store/v1/cart/select-shipping-rate' );
        $store_request->set_body( wp_json_encode( [ 'rate_id' => $rate_id ] ) );
        $store_request->set_header( 'Content-Type', 'application/json' );

        $response = rest_do_request( $store_request );
        $data     = rest_get_server()->response_to_data( $response, false );

        return new WP_REST_Response( $data, $response->get_status() );
    }

    // ------------------------------------------------------------------

    private static function sanitize_address( array $addr ): array {
        return [
            'first_name' => sanitize_text_field( $addr['first_name'] ?? '' ),
            'last_name'  => sanitize_text_field( $addr['last_name']  ?? '' ),
            'address_1'  => sanitize_text_field( $addr['address_1']  ?? '' ),
            'address_2'  => sanitize_text_field( $addr['address_2']  ?? '' ),
            'city'       => sanitize_text_field( $addr['city']       ?? '' ),
            'state'      => sanitize_text_field( $addr['state']      ?? '' ),
            'postcode'   => sanitize_text_field( $addr['postcode']   ?? '' ),
            'country'    => sanitize_text_field( $addr['country']    ?? '' ),
            'email'      => sanitize_email(      $addr['email']      ?? '' ),
            'phone'      => sanitize_text_field( $addr['phone']      ?? '' ),
        ];
    }

    private static function place_order_args(): array {
        return [
            'billing'      => [ 'required' => true,  'type' => 'object' ],
            'shipping'     => [ 'required' => false, 'type' => 'object' ],
            'order_note'   => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ],
            'payment_data' => [ 'required' => false, 'type' => 'object' ],
        ];
    }
}
