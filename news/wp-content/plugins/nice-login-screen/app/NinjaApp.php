<?php

if( !class_exists( 'NinjaApp' ) ){
	
	class NinjaApp {
		
		static protected $instance = null;
		
		public function getInstance() 
		{
			if( null === self::$instance )
			{
				self::$instance = new self;
			}
			
			return self::$instance;
		}
	
		public function appurl()
		{
			
			return str_replace( '/app', '', plugins_url( '', __FILE__ ) );
		}
		
		public function apppath()
		{
			return dirname( __FILE__ );
		}
	
	}

}