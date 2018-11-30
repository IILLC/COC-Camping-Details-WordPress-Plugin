// JavaScript Document

jQuery( document ).ready( function( $ ) { 	
	$( function() {
		$( "button[name=btnSearchStock]" )  
        	.click( function( event ) {
            	event.preventDefault();
				$( '#fish-stocking-container' ).html('<i class="fa fa-spinner fa-pulse fa-5x fa-fw"></i>  Searching the database...');
				var nstr = $( '#txbxWaterName' ).val().trim();
				var wlen = nstr.length;
				if ( wlen < 3 ) {
					$( '#txbxWaterName' ).val( 'All' );
				}
				var w = $( '#txbxWaterName' ).val();
        var d = $( '#slctStockDates' ).val();
				var n = $( '#slctNumb' ).val();
				
				if ( w == "All" ) {
					var ftr = " for "+d;
				} else {
					var ftr = ' for "'+w+'"';
				}
				
			var data = {
						action: 'stockInfoAJAX',
						waterName: w,
						stockDate: d,
						numb: n 
					   };

//WP ajax call
			$.post( my_ajax_object.ajax_url, data, function ( response ) {
				$( '#fish-stocking-container' ).html( response );
				$( '#spnFilters' ).text( ftr );
			});
			});
	});	
  
  $( function() {
		$( "select[name='slctStockDates']" )
		.change( function() {
			$( '#txbxWaterName' ).val( 'All' );
		});
	});	
  
});
