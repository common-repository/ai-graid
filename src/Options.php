<?php

namespace AIGrAid\Plugin;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

class Options {

	public function __construct() {
		add_action( 'carbon_fields_register_fields', [ $this, 'register' ] );
	}

	public function register() {

		$fields = [];

		$fields[] = Field::make( 'text', 'aiga_api_key', __( 'API Key', 'ai-graid' ) )
		                 ->set_help_text( wp_kses( sprintf( __( 'To use AI GrAid plugin you must have valid API key that can be obtained <a target="_blank" href="%s">here</a>.', 'ai-graid' ), esc_url( AIGA_SIGNUP_URL ) ), Utils::kses_allowed_html() ) );

		$api_key = get_option( '_aiga_api_key' );
		if ( ! empty( $api_key ) ) {
			$fields[] = Field::make( 'html', 'aiga_api_status', __( 'API Status', 'ai-graid' ) )
			                 ->set_html( $this->get_api_status_message( $api_key ) );
		}

		$fields[] = Field::make( 'select', 'aiga_auto_grade', __( 'Auto Grade', 'ai-graid' ) )
		                 ->set_help_text( __( 'Whether to enable auto grading on submission.', 'ai-graid' ) )
		                 ->set_options( array(
			                 'on'  => __( 'Yes, enable auto-grading', 'ai-graid' ),
			                 'off' => __( 'No, I will run AI GrAid manually', 'ai-graid' ),
		                 ) );

		$fields[] = Field::make( 'text', 'aiga_passing_score', __( 'Sensitivity percentage', 'ai-graid' ) )
		                 ->set_help_text( __( 'Select the default percent for a passing score.', 'ai-graid' ) )
		                 ->set_attribute( 'type', 'number' )
		                 ->set_attribute( 'min', 1 )
		                 ->set_attribute( 'max', 100 );

		Container::make( 'theme_options', __( 'AI GrAid', 'ai-graid' ) )
		         ->add_fields( $fields )
		         ->set_icon( 'dashicons-superhero' );
	}

	/**
	 *
	 * @param $api_key
	 *
	 * @return string
	 */
	public function get_api_status_message( $api_key ) {

		$system = new System();

		if ( empty( $api_key ) ) {
			$message = wp_kses( '<span>' . __( 'Not Connected', 'ai-graid' ) . '</span>', Utils::kses_allowed_html() );
		} else {
			$error = '';
			try {
				$data = $system->check_api();
			} catch ( \Exception $e ) {
				$error = $e->getMessage();
			}

			if ( ! empty( $data ) ) {
				$message = wp_kses( sprintf( __( 'The API connection is <span style="color:green;">operational</span>.<br/> <span style="color: gray; font-style: italic;">Expires at %s.</span>', 'ai-graid' ), $data['expires_at'] ), Utils::kses_allowed_html() );
			} else {
				$message = wp_kses( sprintf( __( 'The API connection is <span style="color: red;">failing</span> (%s)', 'ai-graid' ), $error ), Utils::kses_allowed_html() );
			}

		}

		return $message;
	}

}