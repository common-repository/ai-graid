<?php

namespace AIGrAid\Plugin;

class Http {

	const API_URL = 'https://ai-gr-aid-396d209166a9.herokuapp.com/';

	/**
	 * Performs a POST request
	 * @param $endpoint
	 * @param $data
	 * @param $args
	 *
	 * @return array|\WP_Error
	 */
	public function post( $endpoint, $data = [], $args = [] ) {

		$default = [
			'timeout'     => 90,
			'blocking'    => true,
			'method'      => 'POST',
			'headers'     => [
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
				'X-API-Key'    => $this->get_api_key(),
			]
		];

		$args = wp_parse_args( $args, $default );

		if ( ! empty( $data ) ) {
			$args = array_merge( $args, [
				'body'        => json_encode( $data ),
				'data_format' => 'body',
			] );
		}

		return wp_remote_post( $this->get_url( $endpoint ), $args );
	}

	/**
	 * Performs a GET request
	 * @param $endpoint
	 * @param $data
	 * @param $args
	 *
	 * @return array|\WP_Error
	 */
	public function get( $endpoint, $data = [], $args = [] ) {

		$args = wp_parse_args( $args, [
			'timeout'     => 90,
			'blocking'    => true,
			'method'      => 'GET',
			'headers'     => [
				'Accept'       => 'application/json',
				'X-API-Key'    => $this->get_api_key(),
			]
		] );

		$url = $this->get_url( $endpoint, $data );

		return wp_remote_get( $url, $args );

	}

	/**
	 * Prepares URL for API request
	 * @param $endpoint
	 * @param $query_args
	 *
	 * @return string
	 */
	private function get_url( $endpoint, $query_args = [] ) {
		$url = rtrim( self::API_URL, '/' ) . '/' . $endpoint;
		if ( ! empty( $query_args ) ) {
			$url = add_query_arg( $query_args, $url );
		}
		return $url;
	}

	/**
	 * Obtains the API key
	 * @return false|mixed|null
	 */
	public function get_api_key() {
		return get_option( '_aiga_api_key' );
	}

}