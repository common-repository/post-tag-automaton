function do_post_tag_automaton( num ) {
	jQuery.post( PTA.endpoint, { action: PTA.action, start: num }, function( response ) {
		jQuery( '#message' ).remove();
		jQuery( '#ajax-response' ).before( '<div id="message" class="updated below-h2"><p>' + response_message( response.checked, response.modified, response.added ) + '</p></div>'  );
		if ( response.added > 0 ) {
			jQuery( '.wp-list-table tbody tr' ).each( function () {
				var tag_name = jQuery(this).find( 'td.name strong a' ).text();
				if ( tag_name in response.results ) {
					jQuery(this).find( 'td.posts a' ).each( function () {
						jQuery(this).html( parseInt( jQuery(this).text(), 10 )+response.results[tag_name] );
					} );
				}
			} );
			if ( jQuery( '#col-left .tagcloud' ).length > 0 )
				jQuery( '#col-left .tagcloud' ).html( response.tagcloud );
			else
				jQuery( '#col-left .col-wrap .form-wrap' ).before( '<div class="tagcloud">'+response.tagcloud+'</div>' );
		}
	} );
}
