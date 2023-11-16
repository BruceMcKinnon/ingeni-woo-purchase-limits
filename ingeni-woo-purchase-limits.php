<?php
/*
Plugin Name: Ingeni Woo Purchase Limits
Version: 2023.03
Plugin URI: http://ingeni.net
Author: Bruce McKinnon - ingeni.net
Author URI: http://ingeni.net
Description: Allows the limiting of item purchasing
*/

/*
Copyright (c) 2023 Ingeni Web Solutions
Released under the GPL license
http://www.gnu.org/licenses/gpl.txt

Disclaimer: 
	Use at your own risk. No warranty expressed or implied is provided.
	This program is free software; you can redistribute it and/or modify 
	it under the terms of the GNU General Public License as published by 
	the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 	See the GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA


Requires : Wordpress 3.x or newer ,PHP 5 +


v2023.01 - Initial version
v2023.02 - count_past_orders_by_product() - Now checks for 'completed', 'on hold' and 'processing' order statuses when counting past orders.
v2023.03 - count_past_orders_by_product() - Fixed order status strings - full list at https://woocommerce.wp-a2z.org/oik_api/wc_get_order_statuses/
*/


$iwpl_debug_on = false;

function iwpl_log_console( $msg ) {
    echo '<script>console.log(' . json_encode($msg, JSON_HEX_TAG) . ');</script>';
}


if ( !function_exists("iwpl_log") ) {
    function iwpl_log($msg) {
        GLOBAL $iwpl_debug_on;

        if ( $iwpl_debug_on ) {
            $upload_dir = wp_upload_dir();
            $logFile = $upload_dir['basedir'] . '/iwpl_log.txt';
            date_default_timezone_set('Australia/Sydney');

            // Now write out to the file
            $log_handle = fopen($logFile, "a");
            if ($log_handle !== false) {
                fwrite($log_handle, date("H:i:s").": ".$msg."\r\n");
                fclose($log_handle);
            }
        }

        //iwpl_log_console($msg);
    }

}

if ( !function_exists("bool2str") ) {
    function bool2str($bool_value) {
        $retVal = 'unknown';
        if ( is_bool($bool_value) === true ) {
            if ($bool_value) {
                $retVal = 'true';
            } else {
                $retVal = 'false';
            }
        }
        return $retVal;
    }
}


function iwpl_popup( $msg, $type= '' ) {
    ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            jQuery("#iwpl_message").text( "<?php echo $msg; ?>" );
            jQuery("#iwplModal").show();
        });
    </script>
    <?php
    return;
}




// New Multi Checkbox field for woocommerce backend
// https://stackoverflow.com/questions/50799927/multi-checkbox-fields-in-woocommerce-backend/50802095#50802095
function woocommerce_wp_multi_checkbox( $field ) {
    global $thepostid, $post;

    if( ! $thepostid ) {
        $thepostid = $post->ID;
    }

    $field['value'] = get_post_meta( $thepostid, $field['id'], true );

    $thepostid              = empty( $thepostid ) ? $post->ID : $thepostid;
    $field['class']         = isset( $field['class'] ) ? $field['class'] : 'select short';
    $field['style']         = isset( $field['style'] ) ? $field['style'] : '';
    $field['wrapper_class'] = isset( $field['wrapper_class'] ) ? $field['wrapper_class'] : '';
    $field['value']         = isset( $field['value'] ) ? $field['value'] : array();
    $field['name']          = isset( $field['name'] ) ? $field['name'] : $field['id'];
    $field['desc_tip']      = isset( $field['desc_tip'] ) ? $field['desc_tip'] : false;

    echo '<fieldset id="'. esc_attr( $field['id'] ) .'" name="'. esc_attr( $field['name'] ) .'" class="form-field ' . esc_attr( $field['id'] ) . '">
    <legend>' . wp_kses_post( $field['label'] ) . '</legend>';

    if ( ! empty( $field['description'] ) && false !== $field['desc_tip'] ) {
        echo wc_help_tip( $field['description'] );
    }

    echo '<ul class="wc-radios">';

    foreach ( $field['options'] as $key => $value ) {

        echo '<li><label><input
                name="' . esc_attr( $field['name'] ) . '"
                value="' . esc_attr( $key ) . '"
                type= "checkbox"
                class="' . esc_attr( $field['class'] ) . '"
                style="' . esc_attr( $field['style'] ) . '"
                ' . ( is_array( $field['value'] ) && in_array( $key, $field['value'] ) ? 'checked="checked"' : '' ) . ' /> ' . esc_html( $value ) . '</label>
        </li>';
    }
    echo '</ul>';

    if ( ! empty( $field['description'] ) && false === $field['desc_tip'] ) {
        echo '<span class="description">' . wp_kses_post( $field['description'] ) . '</span>';
    }

    echo '</fieldset>';
}

function save_multi_select_values ( $post_id, $label, $loop_id = -1 ) {
    $post_data = null;

//iwpl_log('all '.print_r($_POST,true));

    if ( $loop_id > -1) {
        $label .= '_'.$loop_id;
    }
//iwpl_log($post_id.' = label '.$label );
    if ( isset( $_POST[ $label ] ) ) {
        $post_data = $_POST[$label];
    }

    // Convert to array if required.
    if ( !is_array($post_data)) {
        $temp_str = $post_data;
        $post_data = array();
        array_push($post_data, $temp_str);
    }



    if ( $post_data ) {
//iwpl_log($post_id.' post_data :' .print_r( $post_data, true ) );

 // $_POST['_ingeni_woo_purchase_limit_role_field'][$i]

        // Data sanitization
        $sanitize_data = array();
        if( is_array($post_data) && sizeof($post_data) > 0 ){
            foreach( $post_data as $value ){
                array_push( $sanitize_data,  esc_attr( $value ) );
            }
        }
//iwpl_log($post_id.' ' .$label . ' : '.print_r( $sanitize_data, true ) );
        update_post_meta( $post_id, $label, $sanitize_data );

    }

}


// Grab the raw user role data directly from the WP database - you really shouldn't do this!
function ingeni_woo_purchase_limits_get_roles_data_raw() {
    global $wpdb;
    $table_prefix = $wpdb->prefix;

    $all_roles_raw = array();
    $all_roles_raw = get_option( $table_prefix.'user_roles', array() );

	return $all_roles_raw;
}




// Get a list of all user roles
function ingeni_woo_purchase_limits_get_user_roles() {
    $my_roles = array( 'all roles' => __( 'All roles', 'woocommerce' ) );
    // Get a list of user roles
    $user_roles = wp_roles()->roles;
    foreach($user_roles as $user_role) {
        $my_roles[strtolower( $user_role['name'])] =  __($user_role['name'], 'woocommerce' );
    }
    return $my_roles;
}

// Get a list of time limits
function ingeni_woo_purchase_limits_get_time_limits() {
    $my_limits = array( 'order' => __( 'Per order', 'woocommerce' ),
        '1mth' => __( '1 month', 'woocommerce' ),
        '3mth' => __( '3 months', 'woocommerce' ),
        '6mth' => __( '6 months', 'woocommerce' ),
        '12mth' => __( '12 months', 'woocommerce' ),
        'lifetime' => __( 'Lifetime', 'woocommerce' ),
    );
    return $my_limits;
}


// Get the current users roles
function ingeni_woo_products_limits_current_users_roles() {
    $users_roles = array();

    if ( is_user_logged_in() ) {
        $user = wp_get_current_user();
        $users_roles = ( array ) $user->roles;
    }
//iwpl_log('user roles:'.print_r($users_roles,true));
    return $users_roles;
}


// Get the current count of a specific item in the cart
function count_products_in_cart( $product_id, $variation_id = 0 ) {
    global $woocommerce;
    $current_count = 0;
//iwpl_log('count_products_in_cart:'.$product_id.' varation:'.$variation_id);

    $items = $woocommerce->cart->get_cart();
//iwpl_log('items:'.print_r($items,true));
    foreach($items as $item => $values) { 
        $_product =  wc_get_product( $values['data']->get_id()); 

        if ( $product_id == $values['product_id']) {
            $current_count += $values['quantity'];
//iwpl_log('  found '.$product_id . '  =  '.$current_count);
        }
    }

    return $current_count;
}


function count_past_orders_by_product( $user_id, $product_id, $variation_id, $time_limit ) {
    $past_item_order_count = 0;
	global $wpdb;


    $earliest_order = '';
    $mth_limit = intval($time_limit);
    if ($mth_limit > 0) {
        $earliest_order = date( 'Y-m-d', strtotime('-'.$mth_limit.' months') );
    }
    if ( strtolower( $time_limit ) == 'lifetime' ) {
        $earliest_order = '2020-00-00';
    }

    $args = array(
        'customer_id' => $user_id,
        'limit' => -1, // to retrieve _all_ orders by this user
        'date_created' => '>='.$earliest_order,
        'return' => 'ids',
        'status' => array('wc-processing','wc-on-hold','wc-completed'), // Filter by order status (e.g., 'completed', 'processing', 'on hold', 'pending', etc.). Set to 'any' for all statuses.
        'meta_query' => array(
            array(
                'key' => '_product_id', // Meta key to filter by product ID
                'value' => array($product_id), // Product IDs to filter
                'compare' => 'IN', // Use 'IN' to find orders that contain any of the specified product IDs
            ),
        ),
    );
//iwpl_log('args:'.print_r($args,true));
    $past_orders = wc_get_orders( $args );
//iwpl_log('sql:'.$wpdb->last_query);
//iwpl_log('my orders:'.print_r($past_orders,true));

    foreach($past_orders as $past_order_id) {
        $single_past_order = wc_get_order( $past_order_id );

        // Iterating through each WC_Order_Item_Product objects
        foreach ($single_past_order->get_items() as $item_key => $item ) {
//iwpl_log('item:'.print_r($item,true));
            $order_product_id   = $item->get_product_id(); // the Product id
//iwpl_log('order prod id '.$order_product_id. ' = '.$product_id );
            if ( $order_product_id == $product_id ) {
                $order_variation_id = $item->get_variation_id();

                if ( $order_variation_id > 0 ) {
                    // If variable, make sure we ar ecounting the correct variable product
                    if ( $variation_id == $order_variation_id ) {
                        $past_item_order_count += $item->get_quantity(); 
//iwpl_log('variation found: '.$order_variation_id. ' past order:'.$item->get_quantity() );
                    }
                } else {
                    // Otherwise, count the simple product
                    $past_item_order_count += $item->get_quantity(); 
                }

//iwpl_log('order:'. $past_order_id . ' = ' . $item->get_quantity());
            }
        }
    }

    return $past_item_order_count;
}




// For simple products
//
// Simple products load
add_action('woocommerce_product_options_inventory_product_data', 'ingeni_woo_product_limit_custom_fields');
function ingeni_woo_product_limit_custom_fields() {
    global $product;
    global $woocommerce, $post;

    if ( $post->post_type == 'product' ) {
        $product = wc_get_product( $post->ID );

        if ( $product->is_type( 'simple' ) ) {
            echo '<div class="options_group ingeni_woo_product_limit_field">';

            // Max qty
            $max_qty = get_post_meta( $post->ID, '_ingeni_woo_purchase_limit_qty_field', true );
            if ( !$max_qty ) {
                $max_qty = -1;
            }
            woocommerce_wp_text_input( array( 
                'id'    => '_ingeni_woo_purchase_limit_qty_field', 
                'label' => __( 'Max Purchase Qty', 'woocommerce' ), 
                'placeholder'   => '', 
                'description'    => __( 'Max. items that may be purchased. Set to -1 to disable this feature.', 'woocommerce' ),
                'type'  => 'number', 
                'value' => $max_qty,
                'custom_attributes' => array( 'step' => 'any', 'min' => '-1' ) 
            ) );


            //
            // Time Period
            //
            $time_limit = get_post_meta( $post->ID, '_ingeni_woo_purchase_limit_time_field', true );
            if ( !$time_limit ) {
                $time_limit = 'order';
            }
            woocommerce_wp_select( array(
                'id'          => '_ingeni_woo_purchase_limit_time_field',
                'label'       => __( 'Limit to this Time Period', 'iwpl_time' ),
                'desc_tip'    => true,
                'description' => __( 'Is there a time limit?', 'iwplt' ),
                'options'     => ingeni_woo_purchase_limits_get_time_limits(), // <== Here we call our options function
                'value' => $time_limit,
                'selected' => true,
            ) ); 


            //
            // User Roles
            //
            woocommerce_wp_multi_checkbox( array(
                'id' => '_ingeni_woo_purchase_limit_role_field',
                'label' => __( 'Apply to User Roles', 'iwpl_roles' ),
                'name' => '_ingeni_woo_purchase_limit_role_field[]',
                'class' => 'checkbox',
                'options' => ingeni_woo_purchase_limits_get_user_roles(), // <== Here we call our options function
            ) );


            // Allow other users to purchase
            woocommerce_wp_checkbox( array( 
                'id'            => '_ingeni_woo_purchase_allow_other_roles_field', 
                'wrapper_class' => '', 
                'label'         => __('Allow other Roles', 'woocommerce' ), 
                'description'   => __( 'Allow other Roles to purchase this product without limitation', 'woocommerce' ) ,    
                'value' => get_post_meta( $post->ID, '_ingeni_woo_purchase_allow_other_roles_field', true ),
            ) );

            echo '</div>';
        }
    }
}



// Simple products save
//
add_action('woocommerce_process_product_meta', 'ingeni_woo_product_simple_custom_fields_save');
function ingeni_woo_product_simple_custom_fields_save($post_id) {

    $ingeni_woo_purchase_limit_qty_field = $_POST['_ingeni_woo_purchase_limit_qty_field'];
    update_post_meta($post_id, '_ingeni_woo_purchase_limit_qty_field', esc_attr($ingeni_woo_purchase_limit_qty_field));

    $ingeni_woo_purchase_limit_time_field = $_POST['_ingeni_woo_purchase_limit_time_field'];
    update_post_meta($post_id, '_ingeni_woo_purchase_limit_time_field', esc_attr($ingeni_woo_purchase_limit_time_field));

    save_multi_select_values( $post_id, '_ingeni_woo_purchase_limit_role_field' );

    $ingeni_woo_purchase_allow_other_roles = isset( $_POST['_ingeni_woo_purchase_allow_other_roles_field'] ) ? 'yes' : 'no';
    update_post_meta( $post_id, '_ingeni_woo_purchase_allow_other_roles_field', $ingeni_woo_purchase_allow_other_roles );
}



//
// Variable products
//
// Variable products load
add_action('woocommerce_variation_options_inventory', 'ingeni_woo_product_variable_limit_custom_fields', 10, 3 );
function ingeni_woo_product_variable_limit_custom_fields( $loop, $variation_data, $variation ) {
    global $product;
    global $woocommerce, $post;


    echo '<div class="options_group ingeni_woo_product_limit_field">';
        //echo '<div class="inline notice woocommerce-message"><p><strong>Purchase Limits</strong></p></div>';
        //
        // Max qty
        //
        $max_qty = get_post_meta( $variation->ID, '_ingeni_woo_purchase_limit_qty_field', true );
        if ( !$max_qty ) {
            $max_qty = -1;
        }
        woocommerce_wp_text_input( array( 
            'id' => '_ingeni_woo_purchase_limit_qty_field[' . $loop . ']',
            'label' => __( 'Max Qty', 'woocommerce' ), 
            'placeholder'   => '', 
            'description'    => __( 'Max. items that may be purchased. Set to -1 to disable this feature.', 'woocommerce' ),
            'type'  => 'number', 
            'value' => $max_qty,
            'custom_attributes' => array( 'step' => 'any', 'min' => '-1' ) 
        ) );


        //
        // Time Period
        //
        $time_limit = get_post_meta( $variation->ID, '_ingeni_woo_purchase_limit_time_field', true );
        if ( !$time_limit ) {
            $time_limit = 'order';
        }
        woocommerce_wp_select( array(
            'id'          => '_ingeni_woo_purchase_limit_time_field[' . $loop . ']',
            'label'       => __( 'Limit to this Time Period', 'iwpl_time' ),
            'desc_tip'    => true,
            'description' => __( 'Select an option.', 'iwplt' ),
            'options'     => ingeni_woo_purchase_limits_get_time_limits(), // <== Here we call our options function
            'value' => $time_limit,
            'selected' => true,
        ) ); 


        //
        // User Roles
        //
        woocommerce_wp_select( array(
            'id'          => '_ingeni_woo_purchase_limit_role_field[' . $loop . ']',
            'label'       => __( 'Limit to this User Role', 'iwpl_roles' ),
            'desc_tip'    => true,
            'description' => __( 'Select an option.', 'iwplr' ),
            'options'     => ingeni_woo_purchase_limits_get_user_roles(), // <== Here we call our options function
            'value' => get_post_meta( $variation->ID, '_ingeni_woo_purchase_limit_role_field', true ),
            'selected' => true,
        ) ); 
/*
        woocommerce_wp_multi_checkbox( array(
            'id' => '_ingeni_woo_purchase_limit_role_field_' . $loop,
            'label' => __( 'Apply to User Roles', 'iwpl_roles' ),
            'name' => '_ingeni_woo_purchase_limit_role_field_' . $loop,
            'class' => 'checkbox',
            'wrapper_class' => 'variable_roles',
            'options' => ingeni_woo_purchase_limits_get_user_roles(), // <== Here we call our options function
        ) );
*/
        // Allow other users to purchase
        woocommerce_wp_checkbox( array( 
            'id'            => '_ingeni_woo_purchase_allow_other_roles_field[' . $loop . ']', 
            'wrapper_class' => 'variable_allow_others_checkbox', 
            'label'         => __('Allow other Roles', 'woocommerce' ), 
            'description'   => __( 'Allow other Roles to purchase this product without limitation', 'woocommerce' ) ,
            'value' => get_post_meta( $variation->ID, '_ingeni_woo_purchase_allow_other_roles_field', true ),
        ) );

    echo '</div>';
}

// Variable products save
add_action('woocommerce_save_product_variation', 'ingeni_woo_purchase_limits_variable_custom_fields_save', 10, 2 );
function ingeni_woo_purchase_limits_variable_custom_fields_save( $variation_id, $i) {

    $ingeni_woo_purchase_limit_qty_field = $_POST['_ingeni_woo_purchase_limit_qty_field'][$i];
    update_post_meta($variation_id, '_ingeni_woo_purchase_limit_qty_field', esc_attr($ingeni_woo_purchase_limit_qty_field));

    $ingeni_woo_purchase_limit_time_field = $_POST['_ingeni_woo_purchase_limit_time_field'][$i];
    update_post_meta($variation_id, '_ingeni_woo_purchase_limit_time_field', esc_attr($ingeni_woo_purchase_limit_time_field));

    $ingeni_woo_purchase_limit_user_role_field = strtolower( $_POST['_ingeni_woo_purchase_limit_role_field'][$i] );
    update_post_meta($variation_id, '_ingeni_woo_purchase_limit_role_field', esc_attr($ingeni_woo_purchase_limit_user_role_field));

    //save_multi_select_values( $variation_id, '_ingeni_woo_purchase_limit_role_field', $i );

    $ingeni_woo_purchase_allow_other_roles = isset( $_POST['_ingeni_woo_purchase_allow_other_roles_field'][$i] ) ? 'yes' : 'no';
    update_post_meta($variation_id, '_ingeni_woo_purchase_allow_other_roles_field', $ingeni_woo_purchase_allow_other_roles );
}



//
//
// This function hooks updates made on the cart page, and hands validation back to ingeni_woo_purchase_limits_validate().
//
//
add_filter( 'woocommerce_update_cart_validation', 'ingeni_woo_purchase_limits_cart_update_validation', 1, 4 );
function ingeni_woo_purchase_limits_cart_update_validation( $valid, $cart_item_key, $values, $quantity ) {
    //iwpl_log('product:'.$values['product_id'].'  variation:'.$values['variation_id'].'  qty:'.$quantity);
    //iwpl_log('values:'.print_r($values,true));

    $valid = ingeni_woo_purchase_limits_validate( $valid, $values['product_id'], $quantity, $values['variation_id'], $values['variation'], $quantity );

    return $valid;
}





//
// Validate Cart contents logic
//
// Validate when product being added to the cart
// NB - Simple products pass 3 params. Variable products pass 5 params.
add_filter( 'woocommerce_add_to_cart_validation', 'ingeni_woo_purchase_limits_validate', 10, 5 );
function ingeni_woo_purchase_limits_validate( $valid, $product_id, $quantity, $variation_id = 0, $request_variation = null, $direct_cart_update = false) {

    iwpl_log('prod='.$product_id.' qty='.$quantity.' variation='.$variation_id.' req var='.print_r($request_variation,true));
   
    // The product_id is the actual product ID. But if we are validating a variable
    // product, change that ID to the variation ID
    $lookup_id = $product_id;
    if ( $variation_id > 0) {
        $lookup_id = $variation_id;
    }

    //
    // Is this product role limited?
    //
    $limit_roles = array();
    $limit_roles = get_post_meta( $lookup_id, '_ingeni_woo_purchase_limit_role_field', true );
    iwpl_log('limit_roles:'.print_r($limit_roles,true));

    if ( is_array($limit_roles) ) {
    foreach($limit_roles as $role) {
        $role = strtolower($role);
    }
}

    $my_roles = ingeni_woo_products_limits_current_users_roles();
    //iwpl_log('roles:'.print_r($my_roles,true));

    // Other limits
    $max_allowed = get_post_meta( $lookup_id, '_ingeni_woo_purchase_limit_qty_field', true );
    $time_limit = get_post_meta( $lookup_id, '_ingeni_woo_purchase_limit_time_field', true );
    $allow_other_roles = false;
    if ( get_post_meta( $lookup_id, '_ingeni_woo_purchase_allow_other_roles_field', true ) == 'yes' ) {
        $allow_other_roles = true;
    }

    if ( ( $my_roles ) && ($max_allowed >= 0)  && ( is_array($limit_roles) ) ) {
        iwpl_log('my roles = '.print_r($my_roles,true));
        $intersect = array_intersect( $limit_roles, $my_roles );
        iwpl_log('intersect = '.print_r($intersect,true) );
        iwpl_log('allow_other_roles = '.bool2str($allow_other_roles));


        // If there is no intersect, as a last resort search the raw roles data from the DB.
        // First grab the raw roles info as and array
        $all_roles = ingeni_woo_purchase_limits_get_roles_data_raw();
        // Now extra the keys
        $all_roles_keys = array_keys($all_roles);
        iwpl_log('fallback to all roles:'.print_r($all_roles_keys,true));

        $intersect = array_intersect( $all_roles_keys, $my_roles );

        if ( ( count($intersect) > 0 ) || in_array( 'all roles', $limit_roles ) ) {

            if ( $variation_id ) {
                $product = wc_get_product( $variation_id );
            } else {
                $product = wc_get_product( $product_id );
            }

            $previously_ordered = 0;
            $current_cart_count = count_products_in_cart( $product_id, $variation_id );
            // Over-ride the cart count if we are in the process of updating the qty directly from the cart.
            if ( $direct_cart_update ) {
                // If the qty is updated on the Cart page, then the quantity is the total qty.
                // Do not use the current cart qty otherwise you'll count it twice
                $current_cart_count = 0;
            }
            iwpl_log('max_allowed:'.$max_allowed.'  time:'.$time_limit. '  current:'.$current_cart_count);

            $error_msg = '';

            if ( strtolower( $time_limit ) != 'per order' ) {
                // There is a time limit in play, so get the historical orders for this user
                $user_id = get_current_user_id();
                iwpl_log('current user id: '.$user_id);

                $previously_ordered = count_past_orders_by_product( $user_id, $product_id, $variation_id, $time_limit );
                iwpl_log('prod_id:'.$product_id. ' var_id:' .$variation_id.' = previously_ordered:'.$previously_ordered);

                if ( $previously_ordered > 0 ) {
                    $error_msg = 'We have limited stock of \''.$product->get_name(). '\'. For further inquiries contact our team.';
                    //$error_msg = 'Sorry you may purchase up to '.($max_allowed - $previously_ordered) . ' \''.$product->get_name(). '\' at this time.';

                } else {
                    $error_msg = 'Sorry you may only purchase '. $max_allowed . ' x \''.$product->get_name(). '\'.';

                }
                if ( $max_allowed < 1 ) {
                    $error_msg = 'Sorry you may not purchase \''.$product->get_name(). '\'.';
                }

                iwpl_log($previously_ordered .'+'.$current_cart_count.'+'.$quantity . ' > '.$max_allowed);

                if ( ($previously_ordered + $current_cart_count + $quantity) > $max_allowed ) {
                    $num_mths = intval($time_limit);
                    $subject = 'months';
                    if ($num_mths == 1) {
                        $subject = 'month';
                    }
                    if ( $num_mths > 0 ) {
                        $extra_msg = '';
                        if ( $max_allowed > 0 ) {
                            $extra_msg = sprintf( __(' There is a maximum limit of %d purchased within the past %d %s.' ), $max_allowed, $num_mths, $subject );
                        }
                        wc_add_notice( sprintf( __( '%s%s' ), $error_msg, $extra_msg ), 'error' );

                    } else {
                        $subject = 'has';
                        if ( $max_allowed > 1 ) {
                            $subject = 'have';
                        }
                        if ($previously_ordered >= $max_allowed) {
                            wc_add_notice( sprintf( __( '%s The maximum limit of %d %s already been purchased.' ), $error_msg, $max_allowed, $subject, $num_mths ), 'error' );
                        } else {
                            wc_add_notice( sprintf( __( '%s' ), $error_msg, $max_allowed, $subject, $num_mths ), 'error' );

                        }
                    }
                    $valid = false;
                }
            } else {
                if ( ($current_cart_count + $quantity) > $max_allowed ) {
                    wc_add_notice( sprintf( __( 'Sorry. There is a limit of %d of \'%s\' per-order.' ), $max_allowed, $product->get_name() ), 'error' );
                    $valid = false;
                }
            }
        } else {
            // This user is not in the specified role
            if ( $allow_other_roles ) {
                // We'll allow others to purchase without limitation
                $valid = true;
            } else {
                // Otherwise we will block them
                wc_add_notice( sprintf( __( 'This product is not available for purchase.' ) ), 'error' );
                $valid = false;
            }
        }
    }

    //iwpl_log('limit_roles: '.$limit_roles.' allow_other_roles:'.bool2str($allow_other_roles).' roles:'.print_r($my_roles,true));

    if ( ( $limit_roles != '' ) && ( !$my_roles) && (!$allow_other_roles) && ($max_allowed >= 0) ) {
        if ( !is_user_logged_in() ) {
            wc_add_notice( sprintf( __( 'You must be logged in to purchase this product.' ) ), 'error' );
 
        } else {
            //wc_add_notice( sprintf( __( 'This product is available only to certain users.' ) ), 'error' );
            wc_add_notice( sprintf( __( 'This product is available only to certain users.' ) ), 'error' );
        }
        $valid = false;
    }

    iwpl_log('is_valid:'.bool2str($valid));
    return $valid;
}



function iwlp_user_scripts() {
    $plugin_url = plugin_dir_url( __FILE__ );
    wp_enqueue_style( 'iwpl_css',  $plugin_url . "ingeni-woo-purchase-limits.css");
    // Popup JS
    // wp_register_script( 'iwpl_js',  $plugin_url . "ingeni-woo-purchase-limits.js", array( 'jquery' ), '0.1', false );
    //wp_enqueue_script( 'iwpl_js' );
}

add_action( 'admin_print_styles', 'iwlp_user_scripts' );
add_action( 'wp_enqueue_scripts', 'iwlp_user_scripts' );


//
// Generic plugin activate/deactivate stuff
//
function ingeni_load_woo_purchase_limits() {
	// Init auto-update from GitHub repo
	require 'plugin-update-checker/plugin-update-checker.php';
	$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
		'https://github.com/BruceMcKinnon/ingeni-woo-purchase-limits',
		__FILE__,
		'ingeni-woo-purchase-limits'
	);
}
add_action( 'wp_enqueue_scripts', 'ingeni_load_woo_purchase_limits' );


// Plugin activation/deactivation hooks
function ingeni_woo_purchase_limits_activation() {
	flush_rewrite_rules( false );
}
register_activation_hook(__FILE__, 'ingeni_woo_purchase_limits_activation');

function ingeni_woo_purchase_limits_deactivation() {
  flush_rewrite_rules( false );
}
register_deactivation_hook( __FILE__, 'ingeni_woo_purchase_limits_deactivation' );

?>