<?php
/**
 * Plugin Name: Nice Login Screen
 * Plugin URI: http://codecanyon.net/item/wp-nice-screen-login-customize-admin-login-page/236415?ref=ninjascript
 * Description: Allow to customize the Wordpress Login Screen
 * Author:  Nicolas Gastòn Gutierrez
 * Version: 2.1.1
 * Author URI: http://codecanyon.net/user/ninjascript?ref=ninjascript
 * Last Change: 15.08.2011
 */

include dirname( __FILE__ ) . '/app/NinjaException.php';
include dirname( __FILE__ ) . '/app/NinjaApp.php';
include dirname( __FILE__ ) . '/app/NinjaSettings.php';
include dirname( __FILE__ ) . '/app/NinjaAdmin.php';
include dirname( __FILE__ ) . '/app/NiceLoginScreenApp.php';

/**
 * Activating the plugin 
 *
 * :)
 */
add_action( 'plugins_loaded', array( 'NiceLoginScreen', 'getInstance' ) );