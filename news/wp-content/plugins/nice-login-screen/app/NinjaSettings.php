<?php

if( !class_exists( 'NinjaSettings' ) ){

	class NinjaSettings {
	
		static protected $prefix   = '';
		static protected $settings = array();
		static protected $names    = array();
		static protected $defaults = array();
		
		public function __construct( $prefix, $names, $defaults = null )
		{
		
			self::$prefix = $prefix;
			self::$names  = $names;

			if( is_array( $defaults ) )
			{
				foreach( $defaults as $key => $default )
				{
					self::$defaults[ $key ] = $default;
				}
			}

			if( is_array( $names ) )
			{
				foreach( $names as $name )
				{
					self::$settings[ $name ] = get_option( self::$prefix . '_' . $name, self::$defaults[ $name ] );
				}
			}
			
		}
		
		public function save()
		{
			
			foreach( self::$names as $name )
			{
				$key   = self::$prefix . '_' . $name;
				$value = $_POST[ self::$prefix ][ $name ];
				
				if( false === get_option( $key ) )
				{
					add_option( $key, $value );
				}
				else {
					update_option( $key, $value );
				}
				
				self::$settings[ $name ] = get_option( $key, self::$defaults[ $name ] );
				
			}
		}
		
		public function field( $name )
		{
			print self::$prefix . "[$name]";
		}
		
		public function get( $key )
		{

			if( !array_key_exists( $key, self::$settings ) )
			{
				throw new NinjaException( 'Option not found.' );
			}
			
			$value = self::$settings[ $key ];
			
			return $value;
		}

	}
	
}