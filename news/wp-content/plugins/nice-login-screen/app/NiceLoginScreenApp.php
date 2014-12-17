<?php

if( !class_exists( 'NiceLoginScreen' ) ){

	final class NiceLoginScreen {

		static protected $instance = NULL;
		
		/**
		 * Handler for the action 'init'. Instantiates this class.
		 *
		 * @access public
		 * @since 2.0.0
		 * @return object $classobj
		 */
		public function getInstance() 
		{
			if ( NULL === self::$instance )
			{
				self::$instance = new self;
			}
			
			return self::$instance;
		}

		/**
		 * Constructor, init on defined hooks of WP and include second class
		 *
		 * @access public
		 * @since 2.0.0
		 * @uses add_filter, add_action
		 * @return void
		 */
		public function __construct()
		{

			NinjaAdmin::getInstance()->pages = array(
				array( 
					'slug' => 'general', 
					'name' => 'General' 
				),
				array( 
					'slug' => 'themes', 
					'name' => 'Themes' 
				)
			);
			
			NinjaAdmin::getInstance()->themes = array( 'cloud', 'bigG', 'tweet', 'clean', 'blocknotes' );
			
			NinjaAdmin::getInstance()->settings = new NinjaSettings( 
				'ninja_nls', 
				array(
					'custom_logo_status',
					'custom_logo_url',
					'custom_logo_link',
					'custom_logo_width',
					'custom_logo_height',
					'term_of_service_status',
					'term_of_service_link',
					'custom_copyright_status',
					'custom_copyright_text',
					'shaker_effect_status',
					'back_to_blog_status',
					'theme_name'
				)
			);

			add_action( 'admin_init', 'NinjaAdmin::registerAdminScripts' );
			add_action( 'admin_menu', array( $this, 'addPluginPageToMenu' ) );
			add_action( 'login_head', array( $this, 'addAssetsToLoginHead' ) );
			add_action( 'login_form', array( $this, 'addContentToForm' ) );
			add_action( 'login_footer', array( $this, 'addContentToFoot' ) );
			add_action( 'login_headerurl', array( $this, 'changeLogoLink' ) );
			
			if( in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ) ) && NinjaAdmin::getInstance()->settings->get( 'theme_name' ) )
				add_action( 'style_loader_tag', array( $this, 'removeLoginCss' ) );

		}
		
		/**
		 * Wordpress Action Callback
		 *
		 * add the Nice Login Screen menu to admin sidebar.
		 */
		public function addPluginPageToMenu()
		{

			$page = add_menu_page( 'Nice Login Screen', 'NiceLoginScreen', 'activate_plugins', 'nice-login-screen', array( $this, 'showOptionsPage' ) );
			
			add_action( 'admin_print_styles-' . $page, 'NinjaAdmin::addAssetsToAdminHead' );
		}

		/**
		 * Wordpress Action Callback
		 *
		 * show the plugin options page
		 */
		public function showOptionsPage()
		{
			print NinjaAdmin::getInstance()->getAdministration();
		}

		/**
		 * Wordpress Action Callback
		 *
		 * remove all the login tags if we are in login page
		 */
		public function removeLoginCss()
		{
			return '';
		}

		/**
		 * Wordpress Action Callback
		 *
		 * add the plugin assets to login head
		 */
		public function addAssetsToLoginHead()
		{
			if( NinjaAdmin::getInstance()->settings->get( 'custom_logo_status' ) )
			{
				$url 	= NinjaAdmin::getInstance()->settings->get( 'custom_logo_url' );
				$width 	= NinjaAdmin::getInstance()->settings->get( 'custom_logo_width' );
				$height	= NinjaAdmin::getInstance()->settings->get( 'custom_logo_height' );
				
				print '<style type="text/css">' . "\n";
				
				printf( '#login h1 a { display: block; text-indent: -9999px; margin: 0 auto; background: url( %s ) no-repeat!important; width: %spx!important; height: %spx!important; }', $url, $width, $height );
				
				print '</style>' . "\n";
			}
			
			if( !NinjaAdmin::getInstance()->settings->get( 'back_to_blog_status' ) )
			{
				print '<style type="text/css">' . "\n";
				print '#backtoblog { display: none!important; } #login { padding-bottom: 10px; }' . "\n";
				print '</style>';
			}
			
			if( !NinjaAdmin::getInstance()->settings->get( 'shaker_effect_status' ) )
			{
				remove_action( 'login_head', 'wp_shake_js', 12 );
			}
			
			if( $skinName = NinjaAdmin::getInstance()->settings->get( 'theme_name' ) )
			{
				
				print '<link rel="stylesheet" href="'.  NinjaApp::getInstance()->appurl() . '/assets/style/login-reset.css" media="all" />' . "\n";
				print '<link rel="stylesheet" href="'.  NinjaApp::getInstance()->appurl() . '/themes/'. $skinName .'/style.css" media="all" />' . "\n";
			}
			else {
				print '<link rel="stylesheet" href="'.  NinjaApp::getInstance()->appurl() . '/themes/default/style.css" media="all" />' . "\n";
			}
		}

		/**
		 * Wordpress Action Callback
		 *
		 * change the custom link url to the logo.
		 */
		public function changeLogoLink()
		{
			$link = NinjaAdmin::getInstance()->settings->get( 'custom_logo_link' );
			
			return !$link ? 'http://www.wordpress.org' : $link;
		}

		/**
		 * Wordpress Action Callback
		 *
		 * add the plugin content to the login form
		 */		
		public function addContentToForm()
		{
			//print NinjaAdmin::getInstance()->settings->get( 'custom_copyright_text' );
		}
		
		/**
		 * Wordpress Action Callback
		 *
		 * add the plugin content to the login foot
		 */		
		public function addContentToFoot()
		{
			print '<div id="login-close"><!-- --></div>';
			
			if( NinjaAdmin::getInstance()->settings->get( 'custom_copyright_status' ) )
			{
				printf( '<div id="custom-copyright-message"><p>%s</p></div>', NinjaAdmin::getInstance()->settings->get( 'custom_copyright_text' ) );
			}
		}
	}

}