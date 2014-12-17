<?php

if( !class_exists( 'NinjaAdmin' ) ){

	class NinjaAdmin {

		static protected $instance  = null;
		
		public $settings  		= array();
		public $pages 			= array();
		protected $currentPage 	= null;
		
		/**
		 * Return the instance of the Class
		 */
		public function getInstance() 
		{
			if( null === self::$instance )
			{
				self::$instance = new self;
			}
			
			return self::$instance;
		}

		/**
		 * Get the main view of the administration
		 */
		public function getAdministration()
		{
			
			$this->currentPage = 'general';
			
			if( strtoupper( $_SERVER['REQUEST_METHOD'] ) == 'POST' )
			{
				$this->settings->save();
			}
			
			include NinjaApp::getInstance()->apppath() . '/views/index.phtml';
		}

		/**
		 * Register all admin scripts and styles
		 */
		public static function registerAdminScripts()
		{
			wp_register_script( 'ninja', NinjaApp::getInstance()->appurl() . '/assets/scripts/ninja.js', array( 'jquery', 'media-upload', 'thickbox' ) );
			wp_register_style( 'ninja', NinjaApp::getInstance()->appurl() . '/assets/style/ninja.css' );
		}
		
		/**
		 * Add all admin assets to Wordpress Admin Head
		 */
		public static function addAssetsToAdminHead()
		{
		
			wp_enqueue_script( 'ninja' );
			wp_enqueue_script( 'media-upload' );
			wp_enqueue_script( 'media-upload' );
			wp_enqueue_script( 'thickbox' );
			
			wp_enqueue_style( 'ninja' );
			wp_enqueue_style( 'thickbox' );
		
		}
		
	}

}