<?php

namespace AIGrAid\Plugin;

use Carbon_Fields\Container;
use Carbon_Fields\Field;


class Metadata {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'carbon_fields_register_fields', [ $this, 'register' ] );
		add_action( 'add_meta_boxes', [ $this, 'register_custom_metaboxes' ] );
	}

	/**
	 * Register the meta fields
	 * @return void
	 */
	public function register() {

		Container::make( 'post_meta', __( 'AI GrAid', 'ai-graid' ) )
		         ->where( 'post_type', '=', 'sfwd-question' )
		         ->add_fields(
			         $this->get_question_fields()
		         )
		         ->set_priority( 'high' );
	}


	/**
	 * Register custom metaboxes
	 * @return void
	 */
	public function register_custom_metaboxes() {
		add_meta_box(
			'aiga_grading_outcome',
			__( 'AI GrAid', 'ai-graid' ),
			[ $this, 'get_grading_outcome' ],
			'sfwd-essays',
			'side',
			'high'
		);
	}


	/**
	 * Returns the question fields
	 * @return array
	 */
	private function get_question_fields() {
		global $post;

		$default_passing_score = (int) carbon_get_theme_option( 'aiga_passing_score' );

		return array(

			Field::make( 'select', 'aiga_enable', 'Enable AI Engine Grades' )
			     ->add_options( array(
				     'yes' => __( 'Yes', 'ai-graid' ),
				     'no'  => __( 'No', 'ai-graid' ),
			     ) )
			     ->set_default_value( 'no' ),

			Field::make( 'textarea', 'aiga_expected_answer', __( 'Expected Answer', 'ai-graid' ) )
			     ->set_help_text( __( 'Please enter the expected answer for this question. Our AI Engine will try to find similarities with the submitted answer and grade it for you.', 'ai-graid' ) ),

			Field::make( 'text', 'aiga_passing_percentage', __( 'Sensitivity Percentage', 'ai-graid' ) )
			     ->set_help_text( __( 'Please enter the sensitivity percentage for this question specifically.', 'ai-graid' ) )
			     ->set_attribute( 'type', 'number' )
			     ->set_attribute( 'min', 1 )
			     ->set_attribute( 'max', 100 )
			     ->set_default_value( $default_passing_score )
		);
	}

	/**
	 * Returns the grading outcome
	 *
	 * @param $post
	 *
	 * @return string
	 */
	public function get_grading_outcome( $post ) {

		$post_id     = $post->ID;
		$question_id = get_post_meta( $post_id, 'question_post_id', true );
		$enabled     = carbon_get_post_meta( $question_id, 'aiga_enable' ) === 'yes';

		echo '<div id="aiga-grading-outcome">';
		echo wp_kses( Utils::get_view( 'grading-outcome', [
				'essay_id'      => $post_id,
				'question_id'   => $question_id,
				'enabled'       => $enabled,
			]
		), Utils::kses_allowed_html());
		echo '</div>';

	}

}