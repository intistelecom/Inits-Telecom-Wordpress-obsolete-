<html>
  <?php include INTIS_PATH . 'templates/header.php'; ?>
  <body class='login wp-core-ui'>
    <div id="login">
      <h1>
        <a href="http://wordpress.org/" title="Powered by WordPress"><?php echo get_bloginfo( 'name' ); ?></a>
      </h1>
      <h3 style="text-align: center; margin-bottom:10px;">Intis Telecom Two-Factor Authentication</h3>
      <p class="message">
        We sent you a token via SMS message to your phone number: <strong><?=$phonenumber?></strong>
      </p>
      <form method="POST" id="intis" action="<?php echo wp_login_url(); ?>">
        <label for="sms_code">
          SMS code
          <br>
          <input type="text" name="sms_code" id="sms_code" class="input" value="" size="20" autofocus="true" />
        </label>
        <input type="hidden" name="signature" value="<?=$signature?>" />
        <input type="hidden" name="step" value="2" />
        <input type="hidden" name="redirect_to" value="<?=$redirect_to?>"/>
        <input type="hidden" name="username" value="<?=$username?>"/>
        <input type="hidden" name="rememberme" value="<?=$remember_me?>"/>
        
        <p class="submit">
          <input type="submit" value="<?php echo esc_attr_e( 'Login' ) ?>" id="wp_submit" class="button button-primary button-large" />
          <input type="submit" name="reset_code" value="<?php echo esc_attr_e( 'Send SMS code again' ) ?>" class="button button-large" />
        </p>
      </form>
    </div>
  </body>
</html>