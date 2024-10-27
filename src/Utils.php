<?php

namespace AIGrAid\Plugin;

class Utils {


	/**
	 * Return's a view
	 *
	 * @param $view
	 * @param $data
	 *
	 * @return string|void
	 */
	public static function get_view( $view, $data = [] ) {

		$path = AIGA_PATH . 'views/' . str_replace( '.php', '', $view ) . '.php';
		$path = str_Replace( '/', DIRECTORY_SEPARATOR, $path );

		if ( ! file_exists( $path ) ) {
			return '';
		}

		ob_start();
		if ( ! empty( $data ) ) {
			extract( $data );
		}

		include( $path );

		return ob_get_clean();

	}

	/**
	 * Allowed html tags
	 * @param $view
	 *
	 * @return array
	 */
	public static function kses_allowed_html() {
		$tags = wp_kses_allowed_html('post');

		$tags = array_merge_recursive( $tags, [
			'h1'     => [ 'style' => true, 'class' => true ],
			'h2'     => [ 'style' => true, 'class' => true ],
			'h3'     => [ 'style' => true, 'class' => true ],
			'h4'     => [ 'style' => true, 'class' => true ],
			'h5'     => [ 'style' => true, 'class' => true ],
			'h6'     => [ 'style' => true, 'class' => true ],
			'div'    => [ 'style' => true, 'class' => true ],
			'a'      => [ 'style' => true, 'class' => true ],
			'p'      => [ 'style' => true, 'class' => true ],
			'span'   => [ 'style' => true, 'class' => true ],
			'button' => [ 'data-id' => true, 'data-ltext' => true, 'id' => true, 'class' => true, 'type' => true ],
			'td'     => [ 'style' => true, 'class' => true ],
		] );

		return apply_filters('aigraid_kses_allowed_html', $tags);
	}

}