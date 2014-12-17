jQuery( function( $ )
{
	
	$( '#ninja-page' ).children( '.menu' ).find( 'a' ).click(function()
	{
		if( $( this ).parent().hasClass( 'current-page' ) )
		{
			return false;
		}
		
		$( this ).parent().addClass( 'current-page' ).siblings().removeClass( 'current-page' );
		
		var $target = $( $( this ).attr( 'href' ).replace( '#', '#ninjapage-' ) );
		
		$target.siblings().slideUp( 150 );
		$target.slideDown( 300 );
		
		
		return false;
	});
	
	$( '.ninja-numbers' ).bind( 'keypress', function( e ) 
	{
		return ( e.which!=8 && e.which!=0 && ( e.which<48 || e.which>57 ) ) ? false : true ;
	});
	
	
	$( '.ninja-checkbox' ).each( function()
	{
		var $this = $( this ).hide();
		
		var labels = {
			on  : $this.data( 'on' ),
			off : $this.data( 'off' )
		};
		
		var checkedClass = $this[0].checked ? ' checked' : '';
		
		var $ninjacheckbox = $( '<div />', 
		{
			'class' : 'ninja-checkbox ' + checkedClass,
			'html'  : '<span class="label-off">'+ labels.off +'</span><span class="handle"></span><span class="label-on">'+ labels.on +'</span>'
		} ).insertAfter( $this ).click(function()
		{
			$ninjacheckbox.toggleClass( 'checked' );
			$this[ 0 ].checked = $ninjacheckbox.hasClass( 'checked' );
		});
		
		$this.change( function()
		{
			$ninjacheckbox.trigger( 'click' );
		});

	} );

	$( '#themes-list' ).find( 'a' ).click( function()
	{
		var currentClassName = 'current-theme';
		var $parent 		 = $( this ).parent();
		
		if( $parent.hasClass( currentClassName ) )
		{
			return false;
		}
		
		$parent.addClass( currentClassName ).siblings().removeClass( currentClassName ).find( 'input' ).attr( 'checked', false );
		
		
		$( this ).next().attr( 'checked', true );
		
		return false;
	} );
	
	$( '#upload-logo-button' ).click(function()
	{
		formfield = $( '#custom-logo' ).attr( 'name' );
		tb_show( '', 'media-upload.php?type=image&amp;TB_iframe=true' );
		
		return false;
	});

	window.send_to_editor = function( html )
	{
		imgurl = $( 'img', html ).attr( 'src' );
		$( '#custom-logo' ).val( imgurl );
		tb_remove();
	}

});