<?php
require_once 'C:/xampp/htdocs/peptide/wp-load.php';

$products = wc_get_products(array('limit' => 2));
foreach($products as $product) {
    echo "Product: " . $product->get_name() . " (ID: " . $product->get_id() . ")\n";
    $meta = get_post_meta($product->get_id());
    foreach($meta as $k => $v) {
        if (stripos($k, 'tax') !== false) {
            echo "  $k => " . print_r($v, true);
        }
    }
}
