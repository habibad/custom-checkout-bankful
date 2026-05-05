<?php
/**
 * CCO_Payment_Gateway
 *
 * Bankful Payment Gateway integration.
 */

defined( 'ABSPATH' ) || exit;

class CCO_Payment_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'bankful';
        $this->method_title       = __( 'Bankful Payment', 'custom-checkout' );
        $this->method_description = __( 'Accept credit card payments via Bankful.', 'custom-checkout' );
        $this->has_fields         = true;
        $this->supports           = [ 'products' ];

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title', 'Credit Card (Bankful)' );
        $this->description = $this->get_option( 'description' );
        $this->api_key     = $this->get_option( 'api_key' );
        $this->secret_key  = $this->get_option( 'secret_key' );
        $this->test_mode   = 'yes' === $this->get_option( 'test_mode' );

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [ $this, 'process_admin_options' ]
        );
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled'     => [
                'title'   => __( 'Enable/Disable', 'custom-checkout' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Bankful Payment', 'custom-checkout' ),
                'default' => 'yes',
            ],
            'test_mode'   => [
                'title'   => __( 'Test Mode', 'custom-checkout' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Sandbox/Test Mode', 'custom-checkout' ),
                'default' => 'no',
            ],
            'title'       => [
                'title'   => __( 'Title', 'custom-checkout' ),
                'type'    => 'text',
                'default' => __( 'Credit Card (Bankful)', 'custom-checkout' ),
            ],
            'description' => [
                'title'   => __( 'Description', 'custom-checkout' ),
                'type'    => 'textarea',
                'default' => __( 'Pay securely via your credit card.', 'custom-checkout' ),
            ],
            'api_key'     => [
                'title'       => __( 'API Key', 'custom-checkout' ),
                'type'        => 'text',
                'description' => __( 'Your Bankful API Key.', 'custom-checkout' ),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'secret_key' => [
                'title'       => __( 'Secret Key', 'custom-checkout' ),
                'type'        => 'password',
                'description' => __( 'Your Bankful Secret Key.', 'custom-checkout' ),
                'default'     => '',
                'desc_tip'    => true,
            ],
        ];
    }

    /**
     * Render payment fields for the checkout page.
     */
    public function payment_fields() {
        if ( $this->description ) {
            echo wpautop( wptexturize( $this->description ) );
        }
        ?>
        <fieldset id="bankful-card-form" class="cco-bankful-fields">
            <div class="cco-field">
                <label>Card Number <span class="required">*</span></label>
                <input id="bankful-card-number" type="text" autocomplete="off" name="bankful_card_num" placeholder="0000 0000 0000 0000">
            </div>
            <div class="cco-row">
                <div class="cco-field">
                    <label>Expiry Date (MM/YY) <span class="required">*</span></label>
                    <input id="bankful-card-expiry" placeholder="MM / YY" type="text" name="bankful_card_expiry">
                </div>
                <div class="cco-field">
                    <label>Card Code (CVC) <span class="required">*</span></label>
                    <input id="bankful-card-cvc" type="password" name="bankful_card_cvc" placeholder="***">
                </div>
            </div>
        </fieldset>
        <?php
    }

    /**
     * Process payment — calls Bankful API with full null/error safety.
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wc_add_notice( 'Order not found.', 'error' );
            return [ 'result' => 'failure' ];
        }

        // ── 1. Extract card data ──────────────────────────────────────
        // Priority: global set by CCO_API::place_order → $_POST → raw body
        $params = [];

        if ( ! empty( $GLOBALS['cco_payment_data'] ) && is_array( $GLOBALS['cco_payment_data'] ) ) {
            $params = $GLOBALS['cco_payment_data'];
        } elseif ( ! empty( $_POST['bankful_card_num'] ) ) {
            $params = $_POST;
        } else {
            $raw    = file_get_contents( 'php://input' );
            $json   = json_decode( $raw, true );
            $params = $json['payment_data'] ?? [];
        }

        $card_num   = preg_replace( '/\s+/', '', $params['bankful_card_num'] ?? '' );
        $expiry_str = trim( $params['bankful_card_expiry'] ?? '' );
        $cvc        = trim( $params['bankful_card_cvc'] ?? '' );

        // ── 2. Validate card fields ───────────────────────────────────
        if ( empty( $card_num ) ) {
            wc_add_notice( 'Card number is required.', 'error' );
            return [ 'result' => 'failure' ];
        }
        if ( empty( $expiry_str ) ) {
            wc_add_notice( 'Card expiry date is required.', 'error' );
            return [ 'result' => 'failure' ];
        }
        if ( empty( $cvc ) ) {
            wc_add_notice( 'Card CVC is required.', 'error' );
            return [ 'result' => 'failure' ];
        }

        $expiry = array_map( 'trim', explode( '/', $expiry_str ) );
        if ( count( $expiry ) < 2 || empty( $expiry[0] ) || empty( $expiry[1] ) ) {
            wc_add_notice( 'Invalid expiry date format. Use MM/YY.', 'error' );
            return [ 'result' => 'failure' ];
        }

        // ── 3. Validate API credentials ───────────────────────────────
        if ( empty( $this->api_key ) ) {
            wc_add_notice( 'Payment gateway is not configured (missing API key). Please contact support.', 'error' );
            error_log( 'CCO Bankful: api_key is empty. Configure it in WooCommerce → Settings → Payments → Bankful.' );
            return [ 'result' => 'failure' ];
        }

        // ── 4. Build API payload ──────────────────────────────────────
        $api_url = $this->test_mode
            ? 'https://api.sandbox.bankful.com/v1/transaction'
            : 'https://api.bankful.com/v1/transaction';

        $year = $expiry[1];
        if ( strlen( $year ) === 2 ) {
            $year = '20' . $year;
        }

        $payload = [
            'amount'          => number_format( (float) $order->get_total(), 2, '.', '' ),
            'currency'        => get_woocommerce_currency(),
            'card_number'     => $card_num,
            'expiry_month'    => str_pad( $expiry[0], 2, '0', STR_PAD_LEFT ),
            'expiry_year'     => $year,
            'cvv'             => $cvc,
            'order_id'        => (string) $order_id,
            'billing_details' => [
                'first_name' => $order->get_billing_first_name(),
                'last_name'  => $order->get_billing_last_name(),
                'email'      => $order->get_billing_email(),
                'phone'      => $order->get_billing_phone(),
                'address_1'  => $order->get_billing_address_1(),
                'city'       => $order->get_billing_city(),
                'state'      => $order->get_billing_state(),
                'postcode'   => $order->get_billing_postcode(),
                'country'    => $order->get_billing_country(),
            ],
        ];

        // ── 5. Call Bankful API ───────────────────────────────────────
        error_log( 'CCO Bankful: Sending request to ' . $api_url );
        error_log( 'CCO Bankful: Payload (masked) — amount=' . $payload['amount'] . ' currency=' . $payload['currency'] . ' order=' . $order_id );

        $response = wp_remote_post( $api_url, [
            'method'    => 'POST',
            'headers'   => [
                'Authorization' => 'Basic ' . base64_encode( $this->api_key . ':' . $this->secret_key ),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'      => wp_json_encode( $payload ),
            'timeout'   => 45,
            'sslverify' => true,
        ] );

        // ── 6. Handle WP HTTP errors (connection refused, DNS, etc.) ──
        if ( is_wp_error( $response ) ) {
            $error_msg = $response->get_error_message();
            error_log( 'CCO Bankful: WP_Error — ' . $error_msg );
            wc_add_notice( 'Could not connect to payment provider: ' . esc_html( $error_msg ), 'error' );
            return [ 'result' => 'failure' ];
        }

        $http_code     = (int) wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        error_log( 'CCO Bankful: HTTP ' . $http_code . ' — Response: ' . $response_body );

        // ── 7. Decode JSON — MUST null-check before any property access ──
        $body = json_decode( $response_body );

        if ( $body === null ) {
            // json_decode returned null — response was not valid JSON (could be
            // an HTML error page, plain-text, or empty body from the sandbox).
            error_log( 'CCO Bankful: json_decode returned null. Raw body: ' . $response_body );
            wc_add_notice(
                'Payment provider returned an unexpected response (HTTP ' . $http_code . '). Please try again or contact support.',
                'error'
            );
            $order->add_order_note( 'Bankful: invalid JSON response (HTTP ' . $http_code . '): ' . substr( $response_body, 0, 500 ) );
            $order->save();
            return [ 'result' => 'failure' ];
        }

        // ── 8. Handle API-level errors (4xx / 5xx with JSON body) ─────
        if ( $http_code >= 400 ) {
            // Safe property access with null coalescing (PHP 7.4+ compatible).
            $api_msg = $body->message ?? ( $body->error ?? ( $body->detail ?? 'Unknown API error.' ) );
            error_log( 'CCO Bankful: API error HTTP ' . $http_code . ' — ' . $api_msg );
            wc_add_notice( 'Payment error: ' . esc_html( $api_msg ), 'error' );
            $order->add_order_note( 'Bankful payment failed (HTTP ' . $http_code . '): ' . $api_msg );
            $order->save();
            return [ 'result' => 'failure' ];
        }

        // ── 9. Check approval status ──────────────────────────────────
        // Use isset() before accessing — safe for both PHP 7 and PHP 8.
        $status         = isset( $body->status )         ? (string) $body->status         : '';
        $transaction_id = isset( $body->transaction_id ) ? (string) $body->transaction_id : '';

        if ( $status === 'approved' && ! empty( $transaction_id ) ) {
            $order->set_transaction_id( $transaction_id );
            $order->payment_complete( $transaction_id );
            $order->add_order_note( 'Bankful payment approved. Transaction ID: ' . $transaction_id );
            $order->save();

            WC()->cart->empty_cart();

            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            ];
        }

        // ── 10. Declined / unexpected status ─────────────────────────
        $decline_msg = $body->message ?? ( $body->error ?? 'Payment was declined.' );
        error_log( 'CCO Bankful: Payment not approved — status=' . $status . ' msg=' . $decline_msg );
        wc_add_notice( 'Payment declined: ' . esc_html( $decline_msg ), 'error' );
        $order->add_order_note( 'Bankful payment declined. Status: ' . $status . '. Message: ' . $decline_msg );
        $order->save();
        return [ 'result' => 'failure' ];
    }

    public function validate_fields() {
        return true;
    }
}