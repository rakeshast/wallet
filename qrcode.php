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
    // Key you want to check
    $desired_key = 'wallet';
    // Check if the desired key exists in the array
    if (is_array($items) && array_key_exists($desired_key, $items)) {
        // The key exists, do something
        // echo $value = $items[$desired_key];
        // ... your code here ...
    } else {
        $items['wallet'] = __('Wallet', 'your-textdomain');
    }
    return $items;
}
add_filter('woocommerce_account_menu_items', 'custom_account_menu_items');


// register permalink endpoint
add_action( 'init', 'misha_add_endpoint' );
function misha_add_endpoint() {
    add_rewrite_endpoint( 'wallet', EP_PAGES );
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
                            <td><p><img src="<?php echo $user_qr_url; ?>" class="img" style="width:100%; max-width:120px;"/></p><button type="button" class="button">Regenerate Wallet QR Code</button></td>
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
                <?php display_form_errors(); ?>
                <div class="form-group mb-3">
                    <label for="username">Username</label>                
                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter Username">
                    <span class="error"><?php echo isset($errors['username']) ? $errors['username'] : ''; ?></span>
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
        $username = sanitize_text_field($_POST['username']);
        $amt_to_transfer = $_POST['wallet_amt'];
       
        
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

            // session_start();
            // $_SESSION['form_success'] = "Successfully transfer wallet amount to ". $user->user_login;

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
    }else{
        $success = $_SESSION['form_success'];
        echo '<p class="error" style="color:green;">' . $success . '</p>';
    }
}




// Function to display user meta fields
function custom_user_profile_fields($user) {
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

// Hook to display fields on user profile edit page
add_action('show_user_profile', 'custom_user_profile_fields');
add_action('edit_user_profile', 'custom_user_profile_fields');

function save_custom_user_field($user_id) {

    if (isset($_POST['update_user_nonce']) && wp_verify_nonce($_POST['update_user_nonce'], 'update_user_metadata') && current_user_can('edit_user_metadata') ) {
       
        $user_qr_wallet = (int)($_POST['user_qr_wallet']);    
        if (!empty($user_qr_wallet)) {
            update_user_meta($user_id, 'user_qr_wallet', $user_qr_wallet);
        }
        if (is_int($user_qr_wallet) && $user_qr_wallet < 0) {
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
