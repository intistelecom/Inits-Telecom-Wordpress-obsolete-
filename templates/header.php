<head>
  <?php
    global $wp_version;
    if ( version_compare( $wp_version, '3.3', '<=' ) ): 
  ?>
      <link rel="stylesheet" type="text/css" href="<?php echo admin_url( 'css/login.css' ); ?>" />
      <link rel="stylesheet" type="text/css" href="<?php echo admin_url( 'css/colors-fresh.css' ); ?>" />
  <?php
    elseif ( version_compare( $wp_version, '3.8', '<=' ) ):
      wp_admin_css("wp-admin", true);
      wp_admin_css("colors-fresh", true);
      wp_admin_css("ie", true);
    else:
      wp_admin_css("login", true);
    endif;
  ?>
</head>
