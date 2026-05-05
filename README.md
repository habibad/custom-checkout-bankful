# Custom Checkout Plugin

A production-grade WooCommerce plugin that replaces the checkout flow with a 
fully custom page + custom payment gateway — **zero conflict** with the default 
WooCommerce checkout.

---

## Plugin Structure

```
custom-checkout/
├── custom-checkout.php               ← Main plugin file
├── includes/
│   ├── class-cco-page.php            ← Virtual page (rewrite rule, no WP post needed)
│   ├── class-cco-assets.php          ← CSS/JS loaded ONLY on our page
│   ├── class-cco-payment-gateway.php ← WC payment gateway (ID: cco_custom)
│   └── class-cco-api.php             ← REST endpoints: /wp-json/cco/v1/
├── templates/
│   └── checkout.php                  ← Full HTML checkout template
└── assets/
    ├── css/checkout.css
    └── js/checkout.js
```

---

## How It Avoids Conflicts

| Concern | Solution |
|---|---|
| Page conflict | Uses a **rewrite rule** (`/custom-checkout/`), not a WP page post |
| CSS/JS conflict | Assets enqueued **only when** `get_query_var('cco_checkout')` is true |
| WC checkout untouched | We never hook into `woocommerce_checkout_*` actions |
| Gateway conflict | Registered as a separate gateway ID `cco_custom` |
| Nonce security | Two separate nonces: WC Store API nonce + our own `cco_ajax` nonce |

---

## Checkout Flow

```
User Cart → /custom-checkout/ (virtual page)
    │
    ├─ GET  /wp-json/cco/v1/cart-summary     → loads cart from WC session
    │
    ├─ POST /wp-json/wc/store/v1/cart/apply-coupon  → coupon via Store API
    │
    └─ POST /wp-json/cco/v1/place-order
           │
           └─ internally calls POST /wp-json/wc/store/v1/checkout
                  │
                  └─ WC creates order → calls CCO_Payment_Gateway::process_payment()
                         │
                         └─ redirect to /checkout/order-received/{id}/
```

---

## Installation

1. Upload the `custom-checkout/` folder to `wp-content/plugins/`
2. Activate the plugin
3. Go to **WooCommerce → Settings → Payments** and enable **Custom Checkout Payment**
4. Add your payment API key in the gateway settings
5. Your checkout is live at: `https://yoursite.com/custom-checkout/`

---

## Adding Your Payment Provider

Open `includes/class-cco-payment-gateway.php` and find the `process_payment()` method.

### Example: bKash / Nagad / SSLCommerz

```php
public function process_payment( $order_id ) {
    $order   = wc_get_order( $order_id );
    $api_key = $this->get_option( 'api_key' );
    $amount  = $order->get_total();

    // Call your payment provider SDK or HTTP API here.
    $response = wp_remote_post( 'https://api.yourprovider.com/charge', [
        'body' => json_encode([
            'amount'   => $amount,
            'currency' => get_woocommerce_currency(),
            'order_id' => $order_id,
        ]),
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
    ] );

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( ! empty( $body['transaction_id'] ) ) {
        $order->payment_complete( $body['transaction_id'] );
        WC()->cart->empty_cart();
        return [ 'result' => 'success', 'redirect' => $this->get_return_url( $order ) ];
    }

    wc_add_notice( 'Payment failed: ' . ( $body['message'] ?? 'Unknown error' ), 'error' );
    return [ 'result' => 'failure' ];
}
```

### Extra payment fields (card number, etc.)

In `assets/js/checkout.js`, add your field data to `payload.payment_data`:

```js
payment_data: {
    card_token: document.getElementById('card-token').value,
    // etc.
}
```

Then in `class-cco-api.php`, the `payment_data` is passed through to the Store API
and forwarded to your gateway's `process_payment()` via WC session or order meta.

---

## Redirecting "Add to Cart" to Custom Checkout

To redirect customers to your page instead of the default WC checkout, add this 
to your theme's `functions.php` or a separate plugin:

```php
add_filter( 'woocommerce_get_checkout_url', function( $url ) {
    return home_url( '/custom-checkout/' );
} );
```

---

## Theme Override

Place your own template at:

```
{your-theme}/custom-checkout/checkout.php
```

The plugin will load your theme file instead of the bundled template.

---

## REST Endpoints Reference

### GET /wp-json/cco/v1/cart-summary
Returns cart items, totals, shipping needs. No auth required (cookie session).

### POST /wp-json/cco/v1/place-order
Places the order via WC Store API.

**Headers:** `X-CCO-Nonce: {ajaxNonce}`

**Body:**
```json
{
  "billing": {
    "first_name": "Rahim",
    "last_name": "Uddin",
    "address_1": "123 Main St",
    "city": "Dhaka",
    "country": "BD",
    "email": "rahim@example.com",
    "phone": "+8801711000000"
  },
  "shipping": { ... },
  "order_note": "Please deliver after 6pm",
  "payment_data": {}
}
```

**Response:**
```json
{
  "order_id": 1234,
  "order_key": "wc_order_xxxxx",
  "redirect_url": "https://yoursite.com/checkout/order-received/1234/",
  "status": "pending"
}
```
