<?php
/*
 Plugin Name: Post-tag automaton
Plugin URI: http://elearn.jp/wpman/column/post-tag-automaton.html
Description: The post-tag is added automatically if that is found a content when saving post. Moreover, some similar words can be set to a post-tag.
Author: tmatsuur
Version: 1.0.1
Author URI: http://12net.jp/
*/

/*
 Copyright (C) 2012 tmatsuur (Email: takenori dot matsuura at 12net dot jp)
This program is licensed under the GNU GPL Version 2.
*/

define( 'POST_TAG_AUTOMATON_DOMAIN', 'post-tag-automaton' );
define( 'POST_TAG_AUTOMATON_DB_VERSION_NAME', 'post-tag-automaton-db-version' );
define( 'POST_TAG_AUTOMATON_DB_VERSION', '1.0.1' );

$plugin_post_tag_automaton = new post_tag_automaton();
class post_tag_automaton {
	var $similar;
	var $removed_post_tags;
	var $new_post_tags;

	function __construct() {
		$this->similar = get_option( POST_TAG_AUTOMATON_DOMAIN, array() );
		load_plugin_textdomain( POST_TAG_AUTOMATON_DOMAIN, false, plugin_basename( dirname( __FILE__ ) ).'/languages' );
		register_activation_hook( __FILE__ , array( &$this , 'init' ) );
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_action( 'wp_ajax_do_post_tag_automaton', array( &$this, 'do_post_tag_automaton' ) );
	}
	function init() {
		if ( get_option( POST_TAG_AUTOMATON_DB_VERSION_NAME ) != POST_TAG_AUTOMATON_DB_VERSION ) {
			update_option( POST_TAG_AUTOMATON_DB_VERSION_NAME, POST_TAG_AUTOMATON_DB_VERSION );
		}
	}
	function admin_init() {
		global $pagenow;
		if ( $pagenow == 'edit-tags.php' && $_REQUEST['taxonomy'] == 'post_tag' ) {
			add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_script' ) );
			add_action( 'after-post_tag-table', array( &$this, 'after_post_tag_table' ) );
			add_action( 'post_tag_add_form_fields', array( &$this, 'post_tag_add_form_fields' ) );
			add_action( 'edit_tag_form_fields', array( &$this, 'edit_tag_form_fields' ) );

			add_filter( 'edited_post_tag', array( &$this, 'edited_post_tag' ), 10, 2 );
		}

		add_filter( 'created_post_tag', array( &$this, 'created_post_tag' ), 10, 2 );	// wp_insert_term
		add_action( 'pre_post_update', array( &$this, 'keep_prev_post_tag' ), 5, 1 ); // wp_insert_post
		add_action( 'save_post', array( &$this, 'added_post_tag' ), 5, 2 ); // wp_insert_post
	}
	function after_post_tag_table() {
?>
<script type="text/javascript">
/* similar words extend */
var similar_words = {
<?php
$similar_words = array();
foreach ( $this->similar as $tag_id=>$words )
	$similar_words[] = $tag_id.': "'.implode( ',', $words ).'"';
echo implode( ',', $similar_words );
?>
	};
jQuery( '.wp-list-table thead th.column-name' ).after( '<th scope="col" id="similar"><span><?php _e( 'Similar words', POST_TAG_AUTOMATON_DOMAIN ); ?></span></th>' );
jQuery( '.wp-list-table tfoot th.column-name' ).after( '<th scope="col"><span><?php _e( 'Similar words', POST_TAG_AUTOMATON_DOMAIN ); ?></span></th>' );
jQuery( '.wp-list-table tbody tr' ).each( function () {
	var tag_id = parseInt( jQuery(this).attr( 'id' ).replace( 'tag-', '' ), 10 );
	var words = '';
	if ( tag_id in similar_words )
		words = similar_words[tag_id].replace( '&', '&amp;' ).replace( '<', '&lt;' ).replace( '>', '&gt;' );
	jQuery(this).children( 'td:first' ).after( '<td class="similar column-similar">'+words+'</td>' );
} );
function response_message( total_posts, modified_posts, added_tags ) {
	message = ( total_posts == 1 )? '<?php _e( '1 post is confirmed', POST_TAG_AUTOMATON_DOMAIN ); ?>': dprintf( '<?php _e( '%d posts are confirmed', POST_TAG_AUTOMATON_DOMAIN ); ?>', total_posts );
	if ( modified_posts > 0 ) {
		message += '<?php _e( ', ', POST_TAG_AUTOMATON_DOMAIN ); ?>';
		message += ( modified_posts == 1 )? '<?php _e( '1 post is modified', POST_TAG_AUTOMATON_DOMAIN ); ?>': dprintf( '<?php _e( '%d posts are modified', POST_TAG_AUTOMATON_DOMAIN ); ?>', modified_posts );
		message += ( added_tags == 1 )? '<?php _e( '( 1 tag is added )', POST_TAG_AUTOMATON_DOMAIN ); ?>': dprintf( '<?php _e( '( %d tags are added )', POST_TAG_AUTOMATON_DOMAIN ); ?>', added_tags );
	}
	message += '<?php _e( '.', POST_TAG_AUTOMATON_DOMAIN ); ?>';
	return message;
}
function dprintf( format, num ) {
	return format.replace( '%d', num );
}
</script>
<div class="form-wrap">
<p><?php printf( __( 'The contents of all posts are checked, and <a href="%s">all tags contained in it are added</a>.', POST_TAG_AUTOMATON_DOMAIN ), 'javascript:do_post_tag_automaton(0)') ;?></p>
</div>
<?php
	}
	function post_tag_add_form_fields() {
?>
<script type="text/javascript">
/* support ajax response */
jQuery(document).ready( function($) {
	$( '#tag-description' ).attr( 'rows', '3' );
	$( '#submit' ).click( function( e ) {
		var form = $(this).parents( 'form' );
		var keep_similar = jQuery.trim( $( '#tag-similar', form ).val() );
		setTimeout( function () {
			$( '.wp-list-table tbody tr' ).each( function () {
				if ( $(this).children( 'td.similar' ).length == 0 ) {
					var tag_id = parseInt( $(this).attr( 'id' ).replace( 'tag-', '' ), 10 );
					var words = '';
					if ( keep_similar != '' ) {
						similar_words[tag_id] = keep_similar;
						words = keep_similar;
					}
					$(this).children( 'td:first' ).after( '<td class="similar column-similar">'+words+'</td>' );
				}
			} );
		} , 2000 );
	} );
	$( '#inline-edit a.save' ).click( function( e ) {
		setTimeout( function () {
			$( '.wp-list-table tbody tr' ).each( function () {
				if ( $(this).children( 'td.similar' ).length == 0 ) {
					var tag_id = parseInt( $(this).attr( 'id' ).replace( 'tag-', '' ), 10 );
					var words = '';
					if ( tag_id in similar_words )
						words = similar_words[tag_id];
					$(this).children( 'td:first' ).after( '<td class="similar column-similar">'+words+'</td>' );
				}
			} );
		} , 2000 );
	} );
} );
</script>
<div class="form-field">
	<label for="tag-similar"><?php _e( 'Similar words', POST_TAG_AUTOMATON_DOMAIN ); ?></label>
	<textarea name="similar" id="tag-similar" rows="2" cols="40"></textarea>
	<p><?php _e( 'Separate words with commas.', POST_TAG_AUTOMATON_DOMAIN ); ?></p>
</div>
<?php
	}
	function edit_tag_form_fields( $tag ) {
		$similar = isset( $this->similar[$tag->term_id] )? esc_html( implode( ',', $this->similar[$tag->term_id] ) ): '';
?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="tag-similar"><?php _e( 'Similar words', POST_TAG_AUTOMATON_DOMAIN ); ?></label></th>
			<td><textarea name="similar" id="tag-similar" rows="2" cols="40"><?php echo esc_html( $similar ); ?></textarea><br />
			<span class="description"><?php _e( 'Separate words with commas.', POST_TAG_AUTOMATON_DOMAIN ); ?></span></td>
		</tr>
<?php
	}
	function created_post_tag( $term_id, $tt_id ) {
		if ( isset( $_POST['action'] ) && 'add-tag' == $_POST['action'] &&
			is_string( $_POST['similar'] ) && trim( $_POST['similar'] ) != '' ) {
			$this->similar[$term_id] = explode( ',', trim( $_POST['similar'] ) );
			update_option( POST_TAG_AUTOMATON_DOMAIN, $this->similar );
		}
		return $parent;
	}
	function edited_post_tag( $term_id, $tt_id ) {
		if ( isset( $_POST['action'] ) && 'editedtag' == $_POST['action'] &&
			is_string( $_POST['similar'] ) && trim( $_POST['similar'] ) != '' ) {
			$this->similar[$term_id] = explode( ',', trim( $_POST['similar'] ) );
			update_option( POST_TAG_AUTOMATON_DOMAIN, $this->similar );
		}
		return $parent;
	}
	function keep_prev_post_tag( $post_id ) {
		$prev_post_tags = get_the_tags( $post_id );
		$this->removed_post_tags = array();
		if ( is_array( $prev_post_tags ) ) foreach ( $prev_post_tags as $post_tag )
			$this->removed_post_tags[$post_tag->term_id] = $post_tag;
	}
	function added_post_tag( $post_id, $post ) {
		if ( !( $post->post_status == 'publish' || $post->post_status == 'future' ) )
			return;
		$pure_text = htmlspecialchars( trim( strip_tags( $post->post_title."\n".$post->post_content ) ), ENT_QUOTES );
		if ( $pure_text != '' ) {
			$post_tags = array();
			// updated post-tag
			$cur_post_tags = get_the_tags( $post_id );
			if ( is_array( $cur_post_tags ) && count( $cur_post_tags ) > 0 ) {
				foreach ( $cur_post_tags as $post_tag ) {
					$post_tags[$post_tag->term_id] = $post_tag;
					if ( isset( $this->removed_post_tags[$post_tag->term_id] ) )
						unset( $this->removed_post_tags[$post_tag->term_id] );
				}
			}
			$this->new_post_tags = array();
			foreach ( get_terms( 'post_tag', 'get=all' ) as $post_tag ) {
				if ( !isset( $post_tags[$post_tag->term_id] ) &&
					!isset( $this->removed_post_tags[$post_tag->term_id] ) ) {
					$words = isset( $this->similar[$post_tag->term_id] )? $this->similar[$post_tag->term_id]: array();
					$words[] = $post_tag->name;
					foreach ( $words as $key=>$word )
						$words[$key] = preg_quote( htmlspecialchars( trim( $word ), ENT_QUOTES ) );
					if ( preg_match_all( '/('.implode( '|', $words ).')+/iu', $pure_text, $matches ) )
						$this->new_post_tags[] = $post_tag->name;
				}
			}
			if ( count( $this->new_post_tags ) > 0 )
				wp_add_post_tags( $post_id, implode( ',', $this->new_post_tags ) );
		}
	}
	function enqueue_script() {
		wp_enqueue_script( 'post-tag-automaton-onload', plugins_url( basename( dirname( __FILE__ ) ) ).'/js/do.js', array( 'jquery' ) );
		wp_localize_script( 'post-tag-automaton-onload', 'PTA', array( 'endpoint' => admin_url( 'admin-ajax.php' ), 'action' => 'do_post_tag_automaton' ) );
	}
	function do_post_tag_automaton() {
		$start = isset( $_REQUEST['start'] )? $_REQUEST['start']: 0;
		$result = array( 'status' => false, 'start' => $_REQUEST['start'], 'total' => 0, 'checked' => 0, 'time' => 0, 'modified' => 0, 'added' => 0, 'results' => array(), 'tagcloud' => '' );
		$posts = get_posts( array( 'numberposts' => -1, 'post_type' => array_keys( get_post_types( array( 'public' => true, 'capability_type' => 'post' ) ) ), 'post_status' => array( 'publish', 'future' ), 'order' => 'ASC' ) );
		if ( is_array( $posts ) ) {
			$time = (float)time() + microtime( true );
			$result['total'] = count( $posts );
			foreach ( $posts as $post ) {
				if ( $post->ID > $start ) {
					$result['checked']++;
					$this->keep_prev_post_tag( $post->ID );
					$this->added_post_tag( $post->ID, $post );
					if ( count( $this->new_post_tags ) > 0 ) {
						$result['modified']++;
						$result['added'] += count( $this->new_post_tags );
						foreach ( $this->new_post_tags as $post_tag ) {
							if ( isset( $result['results'][$post_tag] ) )
								$result['results'][$post_tag]++;
							else
								$result['results'][$post_tag] = 1;
						}
					}
				}
			}
			$result['time'] = (float)time()+microtime( true )-$time;
		}
		if ( $result['added'] > 0 ) {
			$tax = get_taxonomy( 'post_tag' );
			$result['tagcloud'] = '<h3>'.$tax->labels->popular_items."</h3>\n";
			$result['tagcloud'] .= wp_tag_cloud( array( 'taxonomy' => 'post_tag', 'echo' => false, 'link' => 'edit' ) );
		}

		header( 'Content-Type: application/json; charset=utf-8' );
		echo json_encode( $result );
		die();
	}
}
?>