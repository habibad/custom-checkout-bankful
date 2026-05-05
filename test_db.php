<?php
require_once 'C:/xampp/htdocs/peptide/wp-load.php';
global $wpdb;
$res = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates");
echo count($res) . " rows.\n";
print_r($res);
