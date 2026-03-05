<?php
/**
 * Plugin Name: Custom WooCommerce Fields
 * Description: Adds custom fields to WooCommerce products and handles their display and pricing on the frontend.
 * Version: 1.0.0
 * Author: Karthick M
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Custom_WooCommerce_Addons {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_custom_woocommerce_addons_script' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'display_custom_fields_frontend' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'save_custom_fields_to_cart_item' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'update_cart_item_price' ), 10, 1 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_custom_fields_to_cart_item' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_custom_fields_cart' ), 10, 2 );
		add_action( 'woocommerce_order_item_meta_end', array( $this, 'display_custom_fields_admin' ), 10, 3 );
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'display_custom_fields_admin' ), 10, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_custom_fields_to_order_items' ), 10, 4 );
	}

	// Enqueue the frontend script.
	public function enqueue_custom_woocommerce_addons_script() {
		if ( is_product() ) {
			wp_enqueue_script(
				'custom-woocommerce-addons',
				plugin_dir_url( __FILE__ ) . 'js/script.js',
				array( 'jquery' ),
				filemtime( plugin_dir_path( __FILE__ ) . 'js/script.js' ),
				true
			);

			wp_localize_script(
				'custom-woocommerce-addons',
				'custom_fields_vars',
				array(
					'currency_symbol' => get_woocommerce_currency_symbol(),
				)
			);

			wp_enqueue_style( 'custom-fields-frontend-style', plugins_url( 'css/frontend.css', __FILE__ ) );
		}
	}

	// Enqueue admin scripts.
	public function enqueue_admin_scripts() {
		wp_enqueue_style( 'custom-fields-style', plugins_url( 'css/admin.css', __FILE__ ) );
	}

	// Display custom fields on the frontend product page.
	public function display_custom_fields_frontend() {
		global $product;
		$product_id    = $product->get_id();
		$meta_keys     = get_post_meta( $product_id );
		$custom_fields = array();

		foreach ( $meta_keys as $key => $value ) {
			if ( strpos( $key, 'addon_' ) === 0 ) {
				$custom_fields[] = array(
					'name'  => str_replace( 'addon_', '', $key ),
					'price' => $value[0],
				);
			}
		}

		$base_price = $product->get_price();

		if ( ! empty( $custom_fields ) && is_array( $custom_fields ) ) {
			echo '<div class="custom-fields-wrapper">';
			wp_nonce_field( 'custom_fields_action', 'custom_fields_nonce' );
			echo '<h3>' . esc_html__( 'Custom Options', 'woocommerce-product-fields' ) . '</h3>';
			echo '<ul class="custom-fields-list">';
			foreach ( $custom_fields as $index => $field ) {
				echo '<li class="custom-field-item">';
				echo '<input type="checkbox" id="custom_field_' . esc_attr( $index ) . '" name="custom_fields[' . esc_attr( $field['name'] ) . ']" value="' . esc_attr( $field['price'] ) . '" class="custom-field-checkbox" />';
				echo '<label for="custom_field_' . esc_attr( $index ) . '">' . esc_html( $field['name'] ) . ' (' . wp_kses_post( wc_price( $field['price'] ) ) . ')</label>';
				echo '</li>';
			}
			echo '</ul>';
			echo '</div>';
		}

		echo '<div id="price-summary" class="price-summary">';
		echo '<div>' . esc_html__( 'Product Price:', 'woocommerce-product-fields' ) . ' <span id="product-price" data-base-price="' . esc_attr( $base_price ) . '">' . wp_kses_post( wc_price( $base_price ) ) . '</span></div>';
		echo '<div>' . esc_html__( 'Options Price:', 'woocommerce-product-fields' ) . ' <span id="custom-fields-price">' . wp_kses_post( wc_price( 0 ) ) . '</span></div>';
		echo '<div>' . esc_html__( 'Total Price:', 'woocommerce-product-fields' ) . ' <span id="total-price">' . wp_kses_post( wc_price( $base_price ) ) . '</span></div>';
		echo '</div>';
	}

	// Save custom fields when the product is added to the cart.
	public function save_custom_fields_to_cart_item( $cart_item_data, $product_id ) {

		if ( ! isset( $_POST['custom_fields_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['custom_fields_nonce'] ), 'custom_fields_action' ) ) {
			return $cart_item_data;
		}

		if ( isset( $_POST['custom_fields'] ) && is_array( $_POST['custom_fields'] ) ) {
            $custom_fields = $_POST['custom_fields']; //phpcs:ignore
			$custom_fields_data = array();

			foreach ( $custom_fields as $key => $price ) {
				$name_key             = 'addon_' . $key;
				$custom_fields_data[] = array(
					'name'  => sanitize_text_field( $name_key ),
					'price' => floatval( $price ),
				);
			}

			$cart_item_data['custom_fields'] = $custom_fields_data;

			// Calculate additional price for custom fields
			$additional_price                      = array_sum( array_column( $custom_fields_data, 'price' ) );
			$cart_item_data['custom_fields_price'] = $additional_price;

			// Add custom price to product price
			$product                          = wc_get_product( $product_id );
			$base_price                       = $product->get_price();
			$cart_item_data['original_price'] = $base_price;
			$cart_item_data['total_price']    = $base_price + $additional_price;

			// Ensure each item is unique in the cart
			$cart_item_data['unique_key'] = md5( microtime() . wp_rand() );
		}
		return $cart_item_data;
	}

	// Update the cart item price with the custom fields price.
	public function update_cart_item_price( $cart_object ) {
		if ( ! WC()->session->__isset( 'reload_checkout' ) ) {
			foreach ( $cart_object->get_cart() as $cart_item ) {
				if ( isset( $cart_item['custom_fields_price'] ) ) {
					$additional_price = $cart_item['custom_fields_price'];
					$original_price   = $cart_item['original_price'];
					$cart_item['data']->set_price( $original_price + $additional_price );
				}
			}
		}
	}

	// Add custom fields to cart item.
	public function add_custom_fields_to_cart_item( $cart_item_data, $product_id ) {
		// Verify nonce
		if ( ! isset( $_POST['custom_fields_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['custom_fields_nonce'] ), 'custom_fields_action' ) ) {
			return $cart_item_data;
		}

		$custom_fields_data = array();
		$meta_keys          = get_post_meta( $product_id );

		foreach ( $meta_keys as $key => $value ) {
			if ( strpos( $key, 'addon_' ) === 0 ) {
				$addon_name  = str_replace( 'addon_', '', $key );
				$addon_price = sanitize_text_field( $value[0] );

				if ( isset( $_POST['custom_fields'][ $addon_name ] ) ) {
					$custom_fields_data[] = array(
						'name'  => sanitize_text_field( $addon_name ),
						'price' => $addon_price,
					);
				}
			}
		}

		if ( ! empty( $custom_fields_data ) ) {
			$cart_item_data['custom_fields'] = $custom_fields_data;
			$cart_item_data['unique_key']    = md5( microtime() . wp_rand() );
		}

		// Handle variable product pricing
		if ( isset( $_POST['variation_id'] ) ) {
			$variation_id = sanitize_text_field( $_POST['variation_id'] );
			if ( $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( $variation ) {
					$cart_item_data['data'] = $variation;
				}
			}
		}

		return $cart_item_data;
	}

	// Display custom fields in cart.
	public function display_custom_fields_cart( $item_data, $cart_item ) {
		if ( isset( $cart_item['custom_fields'] ) && is_array( $cart_item['custom_fields'] ) ) {
			foreach ( $cart_item['custom_fields'] as $field ) {
				if ( is_array( $field ) && isset( $field['name'], $field['price'] ) ) {
					$item_data[] = array(
						'name'  => esc_html( $field['name'] ),
						'value' => wc_price( $field['price'] ),
					);
				}
			}
		}
		return $item_data;
	}

	// Display custom fields in admin order item details.
	public function display_custom_fields_admin( $item_id ) {
		$custom_fields = wc_get_order_item_meta( $item_id, 'custom_fields', true );
		if ( ! empty( $custom_fields ) && is_array( $custom_fields ) ) {
			echo '<div class="custom-fields-admin-wrapper">';
			echo '<h4>' . esc_html__( 'Custom Fields', 'woocommerce-product-fields' ) . '</h4>';
			echo '<ul class="custom-fields-admin-list">';
			foreach ( $custom_fields as $custom_field ) {
				if ( is_array( $custom_field ) && isset( $custom_field['name'], $custom_field['price'] ) ) {
					echo '<li class="custom-field-admin-item">';
					echo '<span class="custom-field-admin-name">' . esc_html( $custom_field['name'] ) . '</span>';
					echo '<span class="custom-field-admin-price">+ ' . esc_html( get_woocommerce_currency_symbol() ) . esc_html( number_format( $custom_field['price'], 2 ) ) . '</span>';
					echo '</li>';
				}
			}
			echo '</ul>';
			echo '</div>';
		}
	}

	// Add custom fields data to order items.
	public function add_custom_fields_to_order_items( $item, $cart_item_key, $values ) {
		if ( isset( $values['custom_fields'] ) ) {
			$item->update_meta_data( 'custom_fields', $values['custom_fields'] );
		}
	}

}

// Initialize the plugin.
new Custom_WooCommerce_Addons();


