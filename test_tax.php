<?php
require_once 'C:/xampp/htdocs/peptide/wp-load.php';

WC()->frontend_includes();
WC()->session = new WC_Session_Handler();
WC()->session->init();
WC()->customer = new WC_Customer( get_current_user_id(), true );
WC()->cart = new WC_Cart();
WC()->cart->get_cart_from_session();

echo "Cart item count: " . WC()->cart->get_cart_contents_count() . "\n";
foreach (WC()->cart->get_cart() as $item) {
    $product = $item['data'];
    echo "Product: " . $product->get_name() . "\n";
    echo "Tax status: " . $product->get_tax_status() . "\n";
    echo "Tax class: " . $product->get_tax_class() . "\n";
    $rates = WC_Tax::get_rates($product->get_tax_class());
    echo "Rates: "; print_r($rates);
}
WC()->cart->calculate_totals();
echo "Tax total: " . WC()->cart->get_taxes_total() . "\n";
echo "Tax totals array: "; print_r(WC()->cart->get_tax_totals());
