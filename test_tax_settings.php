<?php
require_once 'C:/xampp/htdocs/peptide/wp-load.php';

echo "wc_tax_enabled: " . (wc_tax_enabled() ? 'yes' : 'no') . "\n";
echo "woocommerce_calc_taxes: " . get_option('woocommerce_calc_taxes') . "\n";
echo "woocommerce_tax_based_on: " . get_option('woocommerce_tax_based_on') . "\n";
echo "Rates for AU: "; print_r(WC_Tax::find_rates(['country' => 'AU']));
echo "Rates for US: "; print_r(WC_Tax::find_rates(['country' => 'US']));
echo "All rates: "; 
global $wpdb;
print_r($wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates"));
