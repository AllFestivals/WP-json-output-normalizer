<?php
/**
* Plugin Name: WP json output normalizer
* Plugin URI: https://github.com/AllFestivals/WP-json-output-normalizer
* Description: Filtering posts by meta data of post. Adding by us selected items to JSON.
* Version: 1.1.0
* Author: marek
* License: GPL2
*/

/**
 * Filtering posts by meta data of post.
 */

add_filter( 'json_query_vars', 'slug_allow_meta' );

function slug_allow_meta( $valid_vars ) {
	$valid_vars = array_merge( $valid_vars, array( 'meta_key', 'meta_value' ) );
	return $valid_vars;
}

/**
 * Adding by us selected items to JSON.
 */

function my_api_init($server) {
    global $wp_json_media;
	remove_filter( 'json_prepare_post',    array( $wp_json_media, 'add_thumbnail_data' ), 10);
}

add_action( 'wp_json_server_before_serve', 'my_api_init', 11, 1 );


/*
 code requires php 5.3+
*/
// the filter
add_filter('json_prepare_post', 'json_normalizer_prepare_post', 100);

function json_normalizer_prepare_post($post) {
    $mapper = new Post_to_Object($post);
    $post = $mapper->map();

    return $post;
}

class Post_to_Object {
    public $post = null;
    // Fields selected to be included in map
    public $map = array('ID', 'title', 'link', 'content', 'date', 'author', 'featured_image');
    public $author_map = array('ID', 'username', 'name', 'firstname', 'lastname', 'avatar');
    public function __construct($post) {
        $this->post = $post;
    }

    public function map() {
        $mapped = array();
        $mapped_author = array();
        $map = $this->map;
        $author_map = $this->author_map;

        foreach ($map as $key) {
			/**
			 * Selected author items
			 */
			if ($key == 'author') {
				foreach ($author_map as $am_key) {
					$mapped_author = $this->set_data($am_key, $mapped_author, true);
				}
				$mapped['author'] = $mapped_author;
			}

            if (is_array($key)) {
                foreach($key as $k => $v) {
                    $mapped = $this->set_data($k, $mapped, false, $v);
                }
                continue;
            }

            // no an array
            if ($key != 'author') {
				$mapped = $this->set_data($key, $mapped, false);            	
            }

        }

		return array_filter($mapped);
    }

    public function set_data($k, $mapped, $isAuthor, $v = false ) {
        $post = $this->post;

        if (!$v) {
            $v = $k;
        }

		$array_key_exists = $isAuthor ? array_key_exists($k, $post['author']) : array_key_exists($k, $post);

        if ( $array_key_exists ) {

        	if (!$isAuthor) {
        		$post2 = $post[$k];
        	}

            $mapped[$v] = $isAuthor ? $post['author'][$k] : $post2;
        } else if ($v == 'featured_image') {
			$mapped[$v]	= $this->get_featured_image($post['ID']);
        }

        return $mapped;
    }

    public function get_featured_image($post_id) {

		if (has_post_thumbnail( $post_id ) ):

		$thumb_id = get_post_thumbnail_id( $post_id );
		$thumb_url_array = wp_get_attachment_image_src($thumb_id, 'thumbnail-size', true);
		$image = $thumb_url_array[0];

		return $image;
		endif;
	}

}
