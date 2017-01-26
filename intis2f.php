<?php
/**
 * Plugin Name: Intis Two Factor Authentication
 * Author: Konstantin Shlyk
 * Version: 1.0.0
 * License: GPL2+
 * Text Domain: intis2f

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!defined('ABSPATH')) exit();

if (!class_exists('Intis2f')) {

  class Intis2f {
    private static $__instance = null;
    private $intis_client = null;

    private function __construct() {
      define('INTIS_PATH', plugin_dir_path(__FILE__));

      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/Balance.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/MessageSendingResult.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/Originator.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/PhoneBase.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/PhoneBaseItem.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/DeliveryStatus.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/RemoveTemplateResponse.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/StopList.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/MessageSending.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/MessageSendingSuccess.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/MessageSendingError.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/Template.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/DailyStats.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/Stats.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/HLRResponse.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/HLRStatItem.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/Network.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Entity/IncomingMessage.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Exception/AddTemplateException.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Exception/AddToStopListException.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Exception/BalanceException.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Exception/DailyStatsException.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Exception/DeliveryStatusException.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Exception/HLRResponseException.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Exception/HLRStatItemException.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Exception/IncomingMessageException.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Exception/MessageSendingResultException.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Exception/NetworkException.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Exception/OriginatorException.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Exception/PhoneBaseException.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Exception/PhoneBaseItemException.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Exception/StopListException.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/Exception/TemplateException.php');      

      require_once(INTIS_PATH . 'vendor/Intis/SDK/AClient.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/IApiConnector.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/IClient.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/HttpApiConnector.php');
      require_once(INTIS_PATH . 'vendor/Intis/SDK/IntisClient.php');

      // Read in existing option value from database
      $api_login = get_option( 'intis_api_login' );
      $api_key = get_option( 'intis_api_key' );
      $api_host = get_option( 'intis_api_host' );
      $enable_2f_auth = get_option( 'intis_enable_2f' );
      $enable_woo_notif = get_option( 'intis_enable_woo_notif' );
      
      $this->intis_client = new Intis\SDK\IntisClient($api_login, $api_key, $api_host);

      if ( $enable_2f_auth ) {
        add_filter( 'authenticate', array( $this, 'authenticate_user' ), 10, 3 );
        add_action( 'register_form', array( $this, 'register_form') );
        add_filter( 'registration_errors', array( $this, 'registration_errors'), 10, 3 );
        add_action( 'user_register', array( $this, 'user_register') );
        add_action( 'show_user_profile', array( $this, 'show_extra_profile_fields') );
        add_action( 'edit_user_profile', array( $this, 'show_extra_profile_fields') );
        add_action( 'personal_options_update', array( $this, 'save_extra_profile_fields') );
        add_action( 'edit_user_profile_update', array( $this, 'save_extra_profile_fields') );
      }

      if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        if ($enable_woo_notif) {
          add_action( 'woocommerce_thankyou', array( $this, 'new_order') );
          add_action( 'woocommerce_order_status_completed', array( $this, 'order_completed') );
        }
      }

      add_action( 'admin_menu', array($this, 'add_pages') );
    }

    public function authenticate_user( $user = '', $username_login = '', $password = '' ) {
      $step = $this->arr_get($_POST, 'step', "1");
      $remember_me = $this->arr_get($_POST, 'rememberme');
      $redirect_to = $this->arr_get($_POST, 'redirect_to');
      $reset_code = $this->arr_get($_POST, 'reset_code', false);

      $sms_code = $this->arr_get($_POST, 'sms_code');
      $signature_entered = $this->arr_get($_POST, 'signature');
      $username = $this->arr_get($_POST, 'username');

      if($reset_code!=false){
        $step = "1";
        $reset_code = true;
      }

      if($step == "1") {
        $this->intercept_authentication();

        if(isset($signature_entered)){
          $user = get_user_by( 'login', $username );
        
          if( empty($user) ) {
            $user = get_user_by( 'email', $username );
          }

          $username_login = $username;

          $signature = get_user_meta( $user->ID, 'intis_signature', true );
          if ( empty($signature) || !isset($signature['signature']) || !isset($signature['signed_at']) || !isset($signature['sms_code'])) {
            return new WP_Error( 'denied', "ERROR: Signature incorrect" );
          }

          $in_timelapse = (time() - $signature['signed_at']) <= 300;
          
          if(!$in_timelapse || $signature['signature'] != $signature_entered){
            return new WP_Error( 'denied', "ERROR: Signature incorrect" ); 
          }
        } else {
          $user = wp_authenticate_username_password( $user, $username_login, $password );

          if ( is_wp_error($user) ) {
            return $user; // there was an error
          }
        }

        $phonenumber = get_user_meta( $user->ID, 'intis_phone_number', true );
        if(isset($phonenumber)){
          $phonenumber = preg_replace('/[^0-9]/', '', $phonenumber);
        }

        if(!isset($phonenumber) || $phonenumber==''){
          return $user;
        }

        if(!$this->set_user_signature( $user->ID, $phonenumber, $reset_code )){
          return new WP_Error( 'denied', "ERROR: Can't generate signature and send SMS" );
        }

        $signature = get_user_meta( $user->ID, 'intis_signature', true );
        $sms_code = isset($signature['sms_code']) ? $signature['sms_code'] : null;
        $signature = isset($signature['signature']) ? $signature['signature'] : null;

        if(!isset($signature)){
          return new WP_Error( 'denied', "ERROR: Can't generate signature" );
        }

        echo $this->render_template('login/token_form', array(
          'username' => $username_login,
          'phonenumber' => $phonenumber,
          'signature' => $signature,
          'remember_me' => $remember_me,
          'redirect_to' => $redirect_to
        ));

        exit();
      } elseif ($step == "2"){
        $this->intercept_authentication();

        $sms_code = $this->arr_get($_POST, 'sms_code');
        $signature_entered = $this->arr_get($_POST, 'signature');
        $username = $this->arr_get($_POST, 'username');
        
        $user_wp = get_user_by( 'login', $username );
        if( empty($user_wp) ) {
            $user_wp = get_user_by( 'email', $username );
        }

        $signature = get_user_meta( $user_wp->ID, 'intis_signature', true );
        if ( empty($signature) || !isset($signature['signature']) || !isset($signature['signed_at']) || !isset($signature['sms_code'])) {
          return new WP_Error( 'denied', "ERROR: Signature incorrect" );
        }

        $in_timelapse = (time() - $signature['signed_at']) <= 300;
        
        if(!$in_timelapse || $signature['signature'] != $signature_entered){
          return new WP_Error( 'denied', "ERROR: Signature incorrect" ); 
        }

        if($sms_code!=$signature['sms_code']){
          return new WP_Error( 'denied', "ERROR: SMS code incorrect" );  
        }
        
        $remember_me = ($remember_me == 'forever') ? true : false;
        wp_set_auth_cookie( $user_wp->ID, $remember_me );
        if(!isset($redirect_to)){
          $redirect_to = '/';
        }
        wp_safe_redirect( $redirect_to );
        
        return $user_wp;
      } 

      return $user;
    }

    public function add_pages(){
      add_options_page("2factor authentication by Intis Telecom", "Intis 2factor auth", 10, 'intis-2f-auth', array( $this, 'general_settings') );
    }

    public function general_settings() {
      // variables for the field and option names 
      $api_login_opt_name = 'intis_api_login';
      $api_key_opt_name = 'intis_api_key';
      $api_host_opt_name = 'intis_api_host';
      $enable_2f_auth_opt_name = 'intis_enable_2f';
      $enable_notifications_opt_name = 'intis_enable_woo_notif';

      // Read in existing option value from database
      $api_login_val = get_option( $api_login_opt_name );
      $api_key_val = get_option( $api_key_opt_name );
      $api_host_val = get_option( $api_host_opt_name );
      $enable_2f_auth_val = get_option( $enable_2f_auth_opt_name );
      $enable_notifications_val = get_option( $enable_notifications_opt_name );

      // See if the user has posted us some information
      // If they did, this hidden field will be set to 'Y'
      if( $_POST[ 'intis_submit_hidden' ] == 'Y' ) {
        // Read their posted value
        $api_login_val = $_POST[ $api_login_opt_name ];
        $api_key_val = $_POST[ $api_key_opt_name ];
        $api_host_val = $_POST[ $api_host_opt_name ];
        $enable_2f_auth_val = $_POST[ $enable_2f_auth_opt_name ];
        $enable_notifications_val = $_POST[ $enable_notifications_opt_name ];

        // Save the posted value in the database
        update_option( $api_login_opt_name, $api_login_val );
        update_option( $api_key_opt_name, $api_key_val );
        update_option( $api_host_opt_name, $api_host_val );
        update_option( $enable_2f_auth_opt_name, $enable_2f_auth_val );
        update_option( $enable_notifications_opt_name, $enable_notifications_val );

        // Put an options updated message on the screen
        ?>
        <div class="updated"><p><strong><?php _e('Options saved.', 'intis' ); ?></strong></p></div>
        <?php
      }

      // Now display the options editing screen

      echo '<div class="wrap">';

      // header

      echo "<h2>" . __( '2-factor authentication settings', 'intis' ) . "</h2>";

      // options form
      
      ?>

      <form name="form1" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
      <input type="hidden" name="intis_submit_hidden" value="Y">

      <p>
        <label for="<?php echo $api_login_opt_name; ?>"><?php _e("Intis API login:", 'intis' ); ?></label><br/>
        <input type="text" id="<?php echo $api_login_opt_name; ?>" name="<?php echo $api_login_opt_name; ?>" value="<?php echo $api_login_val; ?>" size="50">
      </p>
      <p>
        <label for="<?php echo $api_key_opt_name; ?>"><?php _e("Intis API key:", 'intis' ); ?></label><br/>
        <input type="text" id="<?php echo $api_key_opt_name; ?>" name="<?php echo $api_key_opt_name; ?>" value="<?php echo $api_key_val; ?>" size="50">
      </p>
      <p>
        <label for="<?php echo $api_host_opt_name; ?>"><?php _e("Intis API host:", 'intis' ); ?></label><br/>
        <input type="text" id="<?php echo $api_host_opt_name; ?>" name="<?php echo $api_host_opt_name; ?>" value="<?php echo $api_host_val; ?>" size="50">
      </p>
      <p>
        <input type="checkbox" id="<?php echo $enable_2f_auth_opt_name; ?>" name="<?php echo $enable_2f_auth_opt_name; ?>" <?php echo ($enable_2f_auth_val ? 'checked' : ''); ?>>
        <label for="<?php echo $enable_2f_auth_opt_name; ?>"><?php _e("enable 2 factor authentication", 'intis' ); ?></label>
      </p>

      <hr />

      <h2>WooCommerce notifications</h2>

      <p>
        <input type="checkbox" id="<?php echo $enable_notifications_opt_name; ?>" name="<?php echo $enable_notifications_opt_name; ?>" <?php echo ($enable_notifications_val ? 'checked' : ''); ?>>
        <label for="<?php echo $enable_notifications_opt_name; ?>"><?php _e("notify customer to change the order status", 'intis' ); ?></label>
      </p>

      <hr />

      <p class="submit">
      <input type="submit" name="Submit" value="<?php _e('Update Options', 'intis' ) ?>" />
      </p>

      </form>
      </div>

      <?php   
    }

    public static function render_template($name, $variables=false) {
      if ($variables) {
        extract($variables, EXTR_SKIP);
      }

      ob_start();
      require(plugin_dir_path( __FILE__ ) . 'templates/' . $name . '.php');
      return ob_get_clean();
    }

    public function register_form() {
      $intis_phone_number = ( ! empty( $_POST['intis_phone_number'] ) ) ? trim( $_POST['intis_phone_number'] ) : '';

      ?>
      <p>
        <label for="intis_phone_number">Phone number<br />
        <input type="text" name="intis_phone_number" id="intis_phone_number" class="input" required value="<?php echo esc_attr( wp_unslash( $intis_phone_number ) ); ?>" size="25" /></label>
      </p>
      <?php
    }

    public function registration_errors( $errors, $sanitized_user_login, $user_email ) {
      if ( empty( $_POST['intis_phone_number'] ) || ! empty( $_POST['intis_phone_number'] ) && trim( $_POST['intis_phone_number'] ) == '' ) {
        $errors->add( 'intis_phone_number_error', __( '<strong>ERROR</strong>: You must include a phone number.', 'mydomain' ) );
      }

      return $errors;
    }

    public function user_register( $user_id ) {
      if ( ! empty( $_POST['intis_phone_number'] ) ) {
        update_user_meta( $user_id, 'intis_phone_number', trim( $_POST['intis_phone_number'] ) );
      }
    }
 
    public function new_order( $order_id ) {
      $order = new WC_Order( $order_id );
      $phonenumber = $order->billing_phone;

      if(isset($phonenumber)){
        $phonenumber = preg_replace('/[^0-9]/', '', $phonenumber);
      }

      if(isset($phonenumber) && $phonenumber!=''){
        $this->intis_client->sendMessage([$phonenumber], "INFO", "Thank you for your order #".$order_id."!");
      }
    }

    public function order_completed( $order_id ) {
      $order = new WC_Order( $order_id );
      $phonenumber = $order->billing_phone;
      if(isset($phonenumber)){
        $phonenumber = preg_replace('/[^0-9]/', '', $phonenumber);
      }

      if(isset($phonenumber) && $phonenumber!=''){
        $this->intis_client->sendMessage([$phonenumber], "INFO", "Your order #".$order_id." has been completed.");
      }
    }

    public function show_extra_profile_fields( $user ) { 
      ?>
      <h3>Intis 2-factor authentication</h3>
   
      <table class="form-table">
          <tr>
              <th><label for="intis_phone_number">Phone number</label></th>
              <td>
                  <input type="text" name="intis_phone_number" id="intis_phone_number" value="<?php echo esc_attr( get_the_author_meta( 'intis_phone_number', $user->ID ) ); ?>" class="regular-text" /><br />
                  <span class="description">Please enter your phone number.</span>
              </td>
          </tr>
      </table>
      <?php 
    }
 
    public function save_extra_profile_fields( $user_id ) {
      if ( !current_user_can( 'edit_user', $user_id ) )
          return false;

      /* Copy and paste this line for additional fields. Make sure to change 'twitter' to the field ID. */
      update_usermeta( absint( $user_id ), 'intis_phone_number', wp_kses_post( $_POST['intis_phone_number'] ) );
    }

    public static function arr_get($array, $key, $default = null) {
      return isset($array[$key]) ? $array[$key] : $default;
    }

    protected function intercept_authentication() {
      // from here we take care of the authentication.
      remove_action( 'authenticate', 'wp_authenticate_username_password', 20 );
    }

    protected function set_user_signature( $user_id, $phonenumber, $reset_code ) {
      if(!isset($user_id)) {
        return false;
      }

      $signature = get_user_meta( $user_id, 'intis_signature', true );
      if ( !empty($signature) && isset($signature['signature']) && isset($signature['signed_at']) && isset($signature['sms_code'])) {
        $in_timelapse = (time() - $signature['signed_at']) <= 300;
        if($in_timelapse && !$reset_code){
          return true;
        }
      }      

      $signed_at = time();
      $signature = wp_generate_password(64, false, false);
      $sms_code = random_int( 1000, 9999 );

      $result = update_user_meta( 
          $user_id, 
          'intis_signature', 
          array('signature' => $signature, 'sms_code' => $sms_code, 'signed_at' => $signed_at) 
      );

      if(!$result){
        return false;
      }
    
      //send sms code
      $results = $this->intis_client->sendMessage([$phonenumber], "INFO", "Your sms code: " . $sms_code);
      if (!count($results) or !$results[0]->isOk()) {
        return false;
      }
      
      return true;
    }

    public static function instance() {
      if( ! is_a( self::$__instance, 'Intis2f' ) ) {
        self::$__instance = new Intis2f;
      }
      return self::$__instance;
    }
  }

}

Intis2f::instance();

?>
