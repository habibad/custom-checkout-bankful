<?php
/**
 * Template Name: Custom Checkout Page
 *
 * This is a standalone page template that does NOT extend any theme.
 * It only uses wp_head() / wp_footer() so theme styles still load.
 *
 * Override by placing this file at:
 *   {your-theme}/custom-checkout/checkout.php
 */

defined( 'ABSPATH' ) || exit;

// If cart is empty, redirect to shop.
if ( WC()->cart->is_empty() ) {
    wp_redirect( wc_get_page_permalink( 'shop' ) );
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php wp_title( '|', true, 'right' ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="cco-checkout-body">

<div id="cco-checkout-wrap">

    <!-- HEADER -->
    <header class="cco-header">
        <div class="cco-container">
            <div class="cco-header-inner">
                <div class="cco-logo-wrap">
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
                        <img src="<?php echo esc_url( CCO_PLUGIN_URL . 'assets/images/logo.png' ); ?>" alt="Pure Peptides" class="cco-logo-img">
                    </a>
                </div>
                <a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>" class="cco-back-to-shop">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 12H5M5 12L12 5M5 12L12 19" stroke="#334155" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php esc_html_e( 'Go to Shop', 'custom-checkout' ); ?>
                </a>
            </div>
        </div>
    </header>

    <!-- MAIN LAYOUT -->
    <main class="cco-main cco-container">
        
        <!-- NOTICES -->
        <div id="cco-notices" role="alert" aria-live="polite"></div>

        <!-- Mobile Summary Toggle (visible only on mobile) -->
        <div class="cco-mobile-summary-header" id="cco-mobile-summary-toggle">
            <div class="cco-mobile-summary-left">
                <span class="cco-mobile-summary-icon">🛒</span>
                <span class="cco-mobile-summary-text">Show order summary</span>
                <span class="cco-mobile-summary-arrow">▼</span>
            </div>
            <div class="cco-mobile-summary-total" id="cco-mobile-total-display">$0.00</div>
        </div>

        <div class="cco-layout-grid">
            
            <!-- LEFT: FORM -->
            <div class="cco-form-column">
                
                <!-- Countdown Alert -->
                <div class="cco-alert-box">
                    <div class="cco-alert-text">
                        <strong>Limited time offer!</strong> Complete your purchase within 5 minutes to maintain FREE Express Shipping with your purchase.
                    </div>
                    <div class="cco-alert-timer" id="cco-countdown">05m 00s</div>
                </div>

                <!-- Express Checkout -->
                <section class="cco-express-section">
                    <h3>Express checkout</h3>
                    <p class="cco-section-desc">Skip the form and checkout faster with one of these options.</p>
                    <button type="button" class="cco-apple-pay-btn">
                         Pay with Apple Pay
                    </button>
                    <div class="cco-divider"><span>OR</span></div>
                </section>

                <!-- Contact -->
                <section class="cco-section">
                    <h3>Contact</h3>
                    <p class="cco-section-desc">We'll use this information to send you updates about your order.</p>
                    <div class="cco-field">
                        <input type="email" id="cco-email" placeholder="Email" required>
                    </div>
                    <label class="cco-checkbox-wrap">
                        <input type="checkbox" id="cco-newsletter" checked>
                        <span>Email me with news and offers</span>
                    </label>
                </section>

                <!-- Delivery -->
                <section class="cco-section">
                    <h3>Delivery</h3>
                    <p class="cco-section-desc">Enter the address where you'd like your order delivered.</p>
                    
                    <div class="cco-field">
                        <select id="cco-country" required>
                            <?php
                            $countries = WC()->countries->get_allowed_countries();
                            $default   = WC()->countries->get_base_country();
                            foreach ( $countries as $code => $name ) {
                                printf('<option value="%s"%s>%s</option>', esc_attr($code), selected($code, $default, false), esc_html($name));
                            }
                            ?>
                        </select>
                    </div>

                    <div class="cco-row">
                        <div class="cco-field"><input type="text" id="cco-first-name" placeholder="First name" required></div>
                        <div class="cco-field"><input type="text" id="cco-last-name" placeholder="Last name" required></div>
                    </div>

                    <div class="cco-field"><input type="text" id="cco-address1" placeholder="Address" required></div>
                    <div class="cco-field"><input type="text" id="cco-address2" placeholder="Apartment, suite, etc. (optional)"></div>

                    <div class="cco-row cco-row--three">
                        <div class="cco-field"><input type="text" id="cco-city" placeholder="City" required></div>
                        <div class="cco-field">
                            <select id="cco-state">
                                <option value="">State</option>
                            </select>
                        </div>
                        <div class="cco-field"><input type="text" id="cco-postcode" placeholder="ZIP code"></div>
                    </div>

                    <div class="cco-field"><input type="tel" id="cco-phone" placeholder="Phone" required></div>

                    <label class="cco-checkbox-wrap">
                        <input type="checkbox" id="cco-ship-to-different">
                        <span>My billing address is different from my shipping address</span>
                    </label>

                    <div id="cco-shipping-fields" style="display:none;">
                        <div class="cco-shipping-fields-inner">
                            <h4 class="cco-shipping-fields-title">Billing address</h4>

                            <div class="cco-field">
                                <select id="cco-ship-country">
                                    <?php
                                    foreach ( $countries as $code => $name ) {
                                        printf('<option value="%s"%s>%s</option>', esc_attr($code), selected($code, $default, false), esc_html($name));
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="cco-row">
                                <div class="cco-field"><input type="text" id="cco-ship-first-name" placeholder="First name"></div>
                                <div class="cco-field"><input type="text" id="cco-ship-last-name" placeholder="Last name"></div>
                            </div>

                            <div class="cco-field"><input type="text" id="cco-ship-address1" placeholder="Address"></div>
                            <div class="cco-field"><input type="text" id="cco-ship-address2" placeholder="Apartment, suite, etc. (optional)"></div>

                            <div class="cco-row cco-row--three">
                                <div class="cco-field"><input type="text" id="cco-ship-city" placeholder="City"></div>
                                <div class="cco-field">
                                    <select id="cco-ship-state">
                                        <option value="">State</option>
                                    </select>
                                </div>
                                <div class="cco-field"><input type="text" id="cco-ship-postcode" placeholder="ZIP code"></div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Shipping Method -->
                <section class="cco-section">
                    <h3>Shipping method</h3>
                    <p class="cco-section-desc">Choose how you'd like to receive your order.</p>
                    <div id="cco-shipping-methods">
                        <div class="cco-shipping-method-card cco-is-selected">
                            <label class="cco-method-info">
                                <input type="radio" name="shipping_method_static" value="express" checked>
                                <div class="cco-method-text">
                                    <strong>Express Shipping (Australia)</strong>
                                    <span>Tracked delivery around 1-2 business days</span>
                                </div>
                            </label>
                            <div class="cco-method-price">Free</div>
                        </div>
                    </div>
                </section>

                <!-- Payment -->
                <section class="cco-section">
                    <h3>Payment</h3>
                    <p class="cco-section-desc">All transactions are secure and encrypted.</p>
                    
                    <div class="cco-payment-box">
                        <label class="cco-payment-method cco-payment-method--active">
                            <input type="radio" name="payment_method" value="bankful" checked>
                            <div class="cco-payment-label-wrap">
                                <span class="cco-payment-icon">💳</span>
                                <div class="cco-payment-text">
                                    <strong>Pay with Card</strong>
                                    <span>Complete your payment securely via Bankful</span>
                                    <!-- <div class="cco-payment-logos">
                                        <img src="<?php echo esc_url( CCO_PLUGIN_URL . 'assets/images/Visa_Inc.-Logo.wine.png' ); ?>" alt="Visa">
                                        <img src="<?php echo esc_url( CCO_PLUGIN_URL . 'assets/images/Mastercard-Logo.wine.png' ); ?>" alt="Mastercard">
                                        <img src="<?php echo esc_url( CCO_PLUGIN_URL . 'assets/images/American_Express-Logo.wine.png' ); ?>" alt="Amex">
                                        <img src="<?php echo esc_url( CCO_PLUGIN_URL . 'assets/images/Apple_Inc.-Logo.wine.png' ); ?>" alt="Apple Pay">
                                        <img src="<?php echo esc_url( CCO_PLUGIN_URL . 'assets/images/Google_Pay-Logo.wine.png' ); ?>" alt="Google Pay">
                                    </div> -->
                                </div>
                            </div>
                        </label>
                        <div id="cco-card-element" class="cco-payment-details">
                            <div class="cco-bankful-fields-wrap" style="padding: 20px;">
                                <div class="cco-field">
                                    <input type="text" id="bankful-card-num" placeholder="Card Number" autocomplete="cc-number">
                                </div>
                                <div class="cco-row">
                                    <div class="cco-field">
                                        <input type="text" id="bankful-card-expiry" placeholder="MM / YY" autocomplete="cc-exp">
                                    </div>
                                    <div class="cco-field">
                                        <input type="password" id="bankful-card-cvc" placeholder="CVC" autocomplete="cc-csc">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- <label class="cco-payment-method">
                            <input type="radio" name="payment_method" value="bacs">
                            <div class="cco-payment-label-wrap">
                                <span class="cco-payment-icon">🏦</span>
                                <div class="cco-payment-text">
                                    <strong>Direct Bank Transfer</strong>
                                    <span>Card Payments Down for 24hr - Quickest Way To Receive Your Order!</span>
                                </div>
                            </div>
                        </label> -->
                    </div>
                </section>

                <!-- Submit -->
                <div class="cco-submit-wrap">
                    <button type="button" id="cco-place-order" class="cco-btn-complete">
                        <span class="cco-btn-text">Complete order</span>
                        <span class="cco-btn-spinner" style="display:none;" aria-hidden="true">⏳</span>
                    </button>
                    <div class="cco-secure-footer">
                        🔒 Secure checkout powered by <img src="<?php echo esc_url( CCO_PLUGIN_URL . 'assets/images/shop.png' ); ?>" alt="TagadaPay" class="cco-secure-logo">
                        <span style="margin: 0 10px; opacity: 0.3;">|</span>
                        <div class="cco-footer-payment-logos">
                            <img src="<?php echo esc_url( CCO_PLUGIN_URL . 'assets/images/Visa_Inc.-Logo.wine.png' ); ?>" alt="Visa">
                            <img src="<?php echo esc_url( CCO_PLUGIN_URL . 'assets/images/Mastercard-Logo.wine.png' ); ?>" alt="Mastercard">
                            <img src="<?php echo esc_url( CCO_PLUGIN_URL . 'assets/images/American_Express-Logo.wine.png' ); ?>" alt="Amex">
                            <img src="<?php echo esc_url( CCO_PLUGIN_URL . 'assets/images/Apple_Inc.-Logo.wine.png' ); ?>" alt="Apple Pay">
                            <img src="<?php echo esc_url( CCO_PLUGIN_URL . 'assets/images/Google_Pay-Logo.wine.png' ); ?>" alt="Google Pay">
                        </div>
                    </div>
                </div>


                <!-- <footer class="cco-footer-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Refund Policy</a>
                    <a href="#">Shipping Policy</a>
                    <a href="#">Payment Methods</a>
                </footer> -->

            </div>

            <div class="cco-sidebar-wrapper">
                <!-- Order Summary -->
                <div class="cco-summary-card">
                    <h2>Order summary</h2>
                    <p class="cco-subtitle">Review your items and total before completing your purchase.</p>
                    <div id="cco-cart-items"></div>
                    
                    <div class="cco-coupon-row">
                        <input type="text" id="cco-coupon-input" placeholder="Discount code">
                        <button type="button" id="cco-apply-coupon" class="cco-btn-secondary">Apply</button>
                    </div>

                    <div id="cco-cart-totals"></div>
                </div>

                <!-- Trust Section -->
                <div class="cco-trust-section">
                    <h3>Why choose us?</h3>
                    <div class="cco-trust-item">
                        <div class="cco-trust-icon">🎧</div>
                        <div class="cco-trust-content">
                            <strong>Customer Service</strong>
                            <p>We answer your questions Monday to Friday from 9am to 6pm.</p>
                        </div>
                    </div>
                    <div class="cco-trust-item">
                        <div class="cco-trust-icon">🛡️</div>
                        <div class="cco-trust-content">
                            <strong>30-Day Money Back Guarantee</strong>
                            <p>Not satisfied? Easy refund with no conditions. Your satisfaction is our priority.</p>
                        </div>
                    </div>
                    <div class="cco-trust-item">
                        <div class="cco-trust-icon">🚚</div>
                        <div class="cco-trust-content">
                            <strong>48h Shipping</strong>
                            <p>Get ultra-fast shipping with tracking in just 48 hours.</p>
                        </div>
                    </div>
                </div>
            </div>

            </div>
        </div>
    </main>

</div><!-- #cco-checkout-wrap -->

<?php wp_footer(); ?>
</body>
</html>
