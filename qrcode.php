<?php
/**
* Plugin Name: QrCode
* Plugin URI: https://github.com/
* Description: A plugin to generate qr code wallet system for every user.
* Version: 1.0.4
* Author: Rakesh Bokde
* Text Domain: qrcode
* License: GPL v2 or later
*/

defined( 'ABSPATH' ) || exit;

include 'phpqrcode/qrlib.php';

register_activation_hook(__FILE__, 'user_qrcode_shortcode');



// Hook to run the function when the plugin is activated
function activate_wallet_recharge_plugin() {
    create_wallet_recharge_product();
    user_qrcode_shortcode();
}
register_activation_hook(__FILE__, 'activate_wallet_recharge_plugin');


// Function to create a wallet recharge product programmatically
function create_wallet_recharge_product() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return;
    }

    // Check if the product already exists by its title
    $product_title = 'Wallet Recharge'; // Customize the product title as needed
    $product_id = wc_get_product_id_by_sku('wallet-recharge'); // Customize the SKU as needed

    if (!$product_id) {
        // Load WooCommerce functions
        include_once(WC()->plugin_path() . '/includes/admin/wc-admin-functions.php');

        // Create the product
        $product = new WC_Product();

        $product->set_name($product_title);
        $product->set_status('draft'); 
        $product->set_regular_price(100.00); // Customize the recharge amount as needed
        $product->set_sku('wallet-recharge'); // Customize the SKU as needed
        $product->set_virtual(true); // Set as 'true' for a virtual product
        // $product->set_downloadable(true); // Set as 'true' for a downloadable product

        // Add a download file (optional for downloadable products)
        // $download_file_url = 'https://example.com/download/wallet-recharge-file.zip'; // Customize the download file URL as needed
        // $product->set_downloads(array(array(
        //     'name' => $product_title,
        //     'file' => $download_file_url,
        // )));

        $product->save();
        update_option('wallet_recharge_sku', 'wallet-recharge');
    }
}


// Shortcode to display QR codes
function user_qrcode_shortcode() {

    ob_start();    
    // Retrieve users
    $users = get_users();
    foreach ($users as $user) {

        $user_id = $user->ID; // Replace 123 with the actual user ID        
        $user_meta = get_user_meta($user_id); // Get user all metadata
        $user_qr_url = get_user_meta($user_id, 'user_qr_url', true);// metadata by specifying

        // Check if the meta value is empty or null
        if ( empty($user_qr_url) && $user_qr_url == null ) {

            $user_qr_url = qr_code_generate($user_id);
            $parts = explode('/', rtrim($user_qr_url, '/'));
            $qr_code_file_name = array_pop($parts);
            
            update_user_meta($user->ID, 'user_qr_url', $user_qr_url);
            update_user_meta($user->ID, 'user_qr_file_name', $qr_code_file_name);
            update_user_meta($user->ID, 'user_qr_wallet', 0);
            
        }

    }    
    return ob_get_clean();

}


function my_custom_user_registration_hook( $user_id ) {
    
    $user_meta = get_user_meta($user_id); // Get user all metadata
    $user_qr_url = get_user_meta($user_id, 'user_qr_url', true);// metadata by specifying

    // Check if the meta value is empty or null
    if ( empty($user_qr_url) && $user_qr_url == null ) {

        $user_qr_url = qr_code_generate($user_id);
        $parts = explode('/', rtrim($user_qr_url, '/')); 
        $qr_code_file_name = array_pop($parts);
        
        update_user_meta($user_id, 'user_qr_url', $user_qr_url);
        update_user_meta($user_id, 'user_qr_file_name', $qr_code_file_name);
        update_user_meta($user_id, 'user_qr_wallet', 0);

    }

}
add_action( 'user_register', 'my_custom_user_registration_hook' );


// Hook into the delete_user action
function custom_delete_user_and_meta($user_id) {
    // Delete the user metadata
    $user_qr_url = get_user_meta($user_id, 'user_qr_url', true);
    $user_qr_file_name = get_user_meta($user_id, 'user_qr_file_name', true);

    // Check if the meta value is empty or null
    if ( !empty($user_qr_url) && $user_qr_url !== null ) {

        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir']."/qrcode/".$user_qr_file_name;
        
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    delete_user_meta($user_id, 'user_qr_url');
    delete_user_meta($user_id, 'user_qr_file_name');
    delete_user_meta($user_id, 'user_qr_wallet');

}
add_action('delete_user', 'custom_delete_user_and_meta', 10, 1);






// Generate a qr code for user
function qr_code_generate($id){

    $user = get_user_by('ID', $id);
    $domain = home_url();  
    $id         = $user->ID;
    $username   = $user->user_login; 
    $email      = $user->user_email;
    $user_url   = $domain.'/user-profile/'.$id;

    $upload_dir = wp_upload_dir();
    $upload_basedir = $upload_dir['basedir']."/qrcode/";
    $upload_baseurl = $upload_dir['baseurl']."/qrcode/";

    // Check if the folder doesn't already exist
    if (!is_dir($upload_basedir)) {
        mkdir($upload_basedir, 0777, true);
    }
    
    $file_name = "user-qr-".$id.".png";
    $upload_dir = $upload_basedir.$file_name;
    
    // Check if the file already exists in the custom folder
    if (file_exists($upload_dir)) {
        // Delete the existing file
        unlink($upload_dir);
        // if (unlink($upload_dir)) {
        //     echo "File deleted";
        // }else{
        //     echo "File not deleted";
        // }
    }

    // $parts = explode('/', rtrim($upload_dir, '/')); 
    // $qr_code_file_name = array_pop($parts);
    // echo $qr_code_file_name;

    $img_path = $upload_baseurl.$file_name;

    // $ecc stores error correction capability('L')
    $ecc = 'L';
    $pixel_Size = 10;
    $frame_Size = 1;
    $text = $user_url;    

    // Generates QR Code and Stores it in directory given
    QRcode::png($text, $upload_dir, $ecc, $pixel_Size, $frame_Size); 

    return $img_path;
}


// require_once( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' );

function custom_account_menu_items($items) {

    if (!class_exists('WooCommerce')) {
        return false;
    }

    $desired_key = "wallet";
    // Check if the desired key exists in the array
    if (is_array($items) && !array_key_exists($desired_key, $items)) {

        $new_items = array(
            'wallet' => 'Wallet', // Label for the custom item
        );
    
        // Merge the custom menu items after the "Dashboard" menu item
        $position = array_search('dashboard', array_keys($items));
        if ($position !== false) {
            $items = array_slice($items, 0, $position + 1, true) +
                $new_items +
                array_slice($items, $position + 1, null, true);
        } else {
            // If "Dashboard" is not found, simply add the custom item at the end
            $items = $items + $new_items;
        }

    }
    return $items;
}
add_filter('woocommerce_account_menu_items', 'custom_account_menu_items');


// register permalink endpoint
add_action( 'init', 'misha_add_endpoint' );
function misha_add_endpoint() {
    add_rewrite_endpoint( 'wallet', EP_PAGES );
    flush_rewrite_rules();
}

// content for the new page in My Account, woocommerce_account_{ENDPOINT NAME}_endpoint
add_action( 'woocommerce_account_wallet_endpoint', 'misha_my_account_endpoint_content' );
function misha_my_account_endpoint_content() { 
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $user_qr_url = get_user_meta($user->ID, 'user_qr_url', true);
        $user_qr_wallet = get_user_meta($user->ID, 'user_qr_wallet', true);
    ?>

        <div class="custom-my-account">
            <p>Welcome to your wallet <strong><?php echo esc_html($user->display_name); ?>!</strong></p>
            
            <div class="wallet">
                <table class="table table-border">
                    <thead>
                        <tr>
                            <th scope="col">Label</th>
                            <th scope="col">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Qr Code</td>
                            <td>
                                <p><img src="<?php echo $user_qr_url; ?>" class="img" style="width:100%; max-width:120px;"/></p>
                                <button type="button" class="button">Regenerate Wallet QR Code</button>
                                <button type="button" class="button" id="your-custom-button-id">Recharge Wallet</button>
                            </td>
                        </tr>
                        <tr>
                            <td>Wallet Balance</td>
                            <td><p> <?php echo $user_qr_wallet; ?> </p></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <form id="custom-account-form" method="post">
                <h3>Transfer Wallet balance to Any User using user name</h3>
                
                <?php 
                    display_form_errors();
                ?>

                <?php 
                    if (isset($_GET['success']) && $_GET['success'] === '1') {
                        echo '<div class="woocommerce-message success">Your wallet balance has been successfully transfered.</div>';
                    }
                ?>
                <div class="form-group mb-3">
                    <label for="username">Username</label>                
                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter Username/Email">
                </div>
                <div class="form-group mb-3">
                    <label for="wallet_amt">Wallet Amount</label>                
                    <input type="number" class="form-control" id="wallet_amt" name="wallet_amt" placeholder="Wallet Amount">
                </div>
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
            
        </div>
    <?php
    } else {
        echo 'Please log in to access your account.';
    }

}


function custom_form_validation() {
    $errors = array();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $current_user_id = get_current_user_id();
        $current_user_info = get_userdata($current_user_id);
        $current_user_login = $current_user_info->user_login;
        $current_user_email = $current_user_info->user_email;
        
        $username = sanitize_text_field($_POST['username']);
        $amt_to_transfer = $_POST['wallet_amt'];
       
        if ($username === $current_user_login) {
            $errors['username'] = "You can not transfer wallet balance to your account. " .$username;
        }

        if ($username === $current_user_email) {
            $errors['username'] = "You can not transfer wallet balance to your account. " .$username;
        }

        if (empty($username)) {
            $errors['username'] = "Username is required.";
        }

        if ( is_email( $username ) ) {
            $user = get_user_by('email', $username);
        } else {
            $user = get_user_by('login', $username);
        }

        if (empty($user)) {
            $errors['username'] = "Invalid username or email";
        }
        
        if (empty($amt_to_transfer) || !is_numeric($amt_to_transfer) || $amt_to_transfer < 0) {
            $errors['amount'] = "Please enter the valid wallet amount";
        }

        $current_user_wallet_amount = get_user_meta($current_user_id, 'user_qr_wallet', true);
        if ($amt_to_transfer <= $current_user_wallet_amount) {
            
        }else{
            $errors['amount'] = "Your tranferable amount greater then your wallet amount";
        }

        $user_id_to_tranfer_wallet_amount = $user->ID;

        
        if (empty($errors)) {
            $current_user_wallet_amount = get_user_meta($current_user_id, 'user_qr_wallet', true);
            $deposit_user_wallet_amount = get_user_meta($user_id_to_tranfer_wallet_amount, 'user_qr_wallet', true);
           
            $current_user_wallet_balance = $current_user_wallet_amount - $amt_to_transfer;$beneficial_user_wallet_balance = $deposit_user_wallet_amount + $amt_to_transfer;

            update_user_meta($current_user_id, 'user_qr_wallet', $current_user_wallet_balance);
            update_user_meta($user_id_to_tranfer_wallet_amount, 'user_qr_wallet', $beneficial_user_wallet_balance);               
            
            $submission_success = true;
            wp_redirect(add_query_arg('success', $submission_success, wc_get_account_endpoint_url('wallet')));
            exit;   

        }else{
           
            session_start();
            $_SESSION['form_errors'] = $errors;
           
        }      
       
    }
}

add_action('init', 'custom_form_validation');



function display_form_errors() {
    session_start();
    if (isset($_SESSION['form_errors'])) {
        $errors = $_SESSION['form_errors'];
        foreach ($errors as $field => $message) {
            echo '<p class="error" style="color:red;">' . $message . '</p>';
        }
        unset($_SESSION['form_errors']);
    }
}



// Function to display user meta fields
function custom_user_profile_fields($user) {
    if(current_user_can('edit_user_metadata')){
    ?>
        <h3><?php _e('QR User', 'text-domain'); ?></h3>
        <table class="form-table">
            <?php 
                $nonce = wp_create_nonce('update_user_metadata');
                echo '<input type="hidden" name="update_user_nonce" value="' . esc_attr($nonce) . '" />';
            ?>
            <tr>
                <th><label for="user_qr_code"><?php _e('QR Code', 'text-domain'); ?></label></th>
                <td>
                    <img class="img user_qr_url" src="<?php echo esc_attr(get_the_author_meta('user_qr_url', $user->ID)); ?>" style="width:100%; max-width:150px;"/>
                    <p class="description"><?php _e('Unique Qr Code.', 'text-domain'); ?></p>
                    <button type="button" class="button">Regenerate Wallet QR Code</button>
                    
                </td>
            </tr>
            <tr>
                <th><label for="user_qr_wallet"><?php _e('Wallet Balance', 'text-domain'); ?></label></th>user_qr_wallet
                <td>
                    <input type="number" name="user_qr_wallet" value="<?php echo esc_attr(get_the_author_meta('user_qr_wallet', $user->ID)); ?>" <?php echo $variable = (current_user_can('edit_user_metadata')) ? "" : "readonly"; ?>  />               
                    <p class="description"><?php _e('Wallet Balance.', 'text-domain'); ?></p>
                </td>
            </tr>
        </table>
    <?php
    }
}

// Hook to display fields on user profile edit page
add_action('show_user_profile', 'custom_user_profile_fields');
add_action('edit_user_profile', 'custom_user_profile_fields');

function save_custom_user_field($user_id) {

    if (isset($_POST['update_user_nonce']) && wp_verify_nonce($_POST['update_user_nonce'], 'update_user_metadata') && current_user_can('edit_user_metadata') ) {
        

        $user_qr_wallet = (int)($_POST['user_qr_wallet']);    
        if (!empty($user_qr_wallet)) {
            update_user_meta($user_id, 'user_qr_wallet', $user_qr_wallet);
        }
        if (is_int($user_qr_wallet) && $user_qr_wallet <= 0) {
            update_user_meta($user_id, 'user_qr_wallet', 0);
        }
    }
    
}
add_action('personal_options_update', 'save_custom_user_field');
add_action('edit_user_profile_update', 'save_custom_user_field');



// Add custom capability for editing user metadata
function add_custom_capabilities() {
    $roles = array('shop_manager', 'administrator'); // Adjust role names as needed
    
    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->add_cap('edit_user_metadata');
        }
    }
}
add_action('init', 'add_custom_capabilities');



function custom_user_column( $columns ) {
    $columns['wallet_balance'] = 'Wallet Balance';
    return $columns;
}
add_filter( 'manage_users_columns', 'custom_user_column' );

function custom_user_column_content( $value, $column_name, $user_id ) {
    if ( 'wallet_balance' === $column_name ) {
        $user_qr_wallet = get_user_meta($user_id, 'user_qr_wallet', true);        
        return $user_qr_wallet;
    }
    return $value;
}
add_action( 'manage_users_custom_column', 'custom_user_column_content', 10, 3 );























































function enqueue_jquery() {
    wp_enqueue_script('jquery');
}
add_action('wp_enqueue_scripts', 'enqueue_jquery');


function enqueue_custom_scripts() {
    // Enqueue your custom JavaScript file    
    wp_enqueue_script( 'wallet-script', plugin_dir_url( __FILE__ ) . 'assets/js/wallet.js', array('jquery'), time(), true);

    // Pass the AJAX URL to the script
    wp_localize_script('wallet-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'enqueue_custom_scripts');



// Add this code to your theme's functions.php or a custom plugin.
add_action('wp_ajax_create_custom_order', 'create_custom_order');
add_action('wp_ajax_nopriv_create_custom_order', 'create_custom_order');

function create_custom_order() {
    // Check if the user is logged in.
    if (is_user_logged_in()) {
        // Get the current user's ID.
        $current_user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        $billing_address = [
            'first_name' => $current_user->first_name,
            'last_name' => $current_user->last_name,
            'email' => $current_user->user_email,
            'phone' => $current_user->billing_phone,
            'address_1' => $current_user->billing_address_1,
            'address_2' => $current_user->billing_address_2,
            'city' => $current_user->billing_city,
            'state' => $current_user->billing_state,
            'postcode' => $current_user->billing_postcode,
            'country' => $current_user->billing_country,
        ];

        // User is logged in, create a new WooCommerce order and add the wallet recharge product.
        $order = wc_create_order();
        $product_id = wc_get_product_id_by_sku("wallet-recharge"); // Replace with the actual product ID for the wallet recharge product.
        $quantity = 1; // Adjust the quantity as needed.
        $order->add_product(wc_get_product($product_id), $quantity);

        // Calculate totals and save the order.
        $order->calculate_totals();
        $order->save();

        // Set billing details for the order.
        $order->set_address($billing_address, 'billing');
        // Associate the order with the current user.
        update_post_meta($order->get_id(), '_customer_user', $current_user_id);

        // Get the checkout URL for the new order.
        $checkout_url = $order->get_checkout_payment_url();

        // Return the checkout URL as JSON.
        wp_send_json(['checkout_url' => $checkout_url]);
    } else {
        // User is not logged in, return an error message.
        wp_send_json_error('User is not logged in.');
    }
}





// Add this code to your theme's functions.php or a custom plugin.
add_action('woocommerce_order_status_changed', 'check_payment_status_and_update_user_meta', 10, 4);
function check_payment_status_and_update_user_meta($order_id, $old_status, $new_status, $order) {

    // Check if the new order status is 'completed'.
    if ($new_status === 'completed') {

        // Replace 'YOUR_SKU' with the SKU you want to check for.
        $sku_to_check = 'wallet-recharge';

        // Initialize a flag to check if the SKU is found.
        $sku_found = false;
        $subtotal = 0;

        // Loop through the order items to check if the SKU exists.
        foreach ($order->get_items() as $item_id => $item) {
            // Get the product object for the item.
            $product = $item->get_product();

            // Check if the SKU of the product matches the one you want to find.
            if ($product && $product->get_sku() === $sku_to_check) {

                $subtotal = $item->get_subtotal();
            
                // Get the quantity for the item.
                $quantity = $item->get_quantity();

                // Add the details to the results array.
                $results[] = array(
                    'product_name' => $product->get_name(),
                    'subtotal' => $subtotal,
                    'quantity' => $quantity,
                );

                $sku_found = true;
                break; // Exit the loop when the SKU is found.
            }
        }

        // If the SKU is found in the order, take action here.
        if ($sku_found) {
            // Perform actions like updating user meta or sending notifications.
            // For example, you can update a user's meta data:
            $user_id = $order->get_user_id();
            if ($user_id) {
                $user_wallet_balance = get_user_meta($user_id, 'user_qr_wallet', true);
                $user_update_wallet = $user_wallet_balance + $subtotal;
                update_user_meta($user_id, 'user_qr_wallet', $user_update_wallet);              
            }

            // Or send a notification:
            // send_notification_to_admin('SKU found in order');
        }
    }
    
}









// function check_payment_status_and_update_user_meta($order_id, $old_status, $new_status, $order) {
    
//     // Check if the new order status is 'completed'.
//     if ($new_status === 'completed') {
//         // Get the user ID associated with the order.
//         $user_id = $order->get_user_id();

//         // Check if a user is associated with the order.
//         if ($user_id) {
//             // Update user meta data as needed.
//             update_user_meta($user_id, 'user_qr_wallet', 0);
//         }
//     }

// }



// add_action('woocommerce_payment_complete', 'update_user_meta_after_payment');

// function update_user_meta_after_payment($order_id) {
//     // Get the order object.
//     $order = wc_get_order($order_id);

//     // Check the payment status.
//     if ($order->is_paid()) {
//         $user_id = $order->get_user_id();
//         // Payment is completed, update user meta data.
//         update_user_meta($user_id, 'user_qr_wallet', 0);
//         echo "<pre>";
//         print_r($order);
//         echo "</pre>";
//         wp_die();
//         exit();

//         // $user_id = $order->get_user_id();
//         // update_user_meta($user_id, 'payment_status', 'completed');
//     }
// }
