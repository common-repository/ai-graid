<?php

namespace AIGrAid\Plugin;

class Hooks {

	const AJAX_EVAL_ACTION = 'aiga_evaluate';
	const AJAX_SOLVE_ACTION = 'aiga_solve_problem';
	const AJAX_ATTENTION_DISMISS = 'aiga_attention_dismiss';

	/**
	 * Constructor
	 */
	public function __construct() {

		// Essay columns
		add_filter( 'manage_sfwd-essays_posts_columns', [ $this, 'manage_essay_columns' ] );
		add_action( 'manage_sfwd-essays_posts_custom_column', [ $this, 'manage_essay_custom_column' ], 10, 2 );

		// Auto grade
		add_filter( 'learndash_quiz_question_result', [ $this, 'set_auto_grade_data' ], 10, 2 );
		add_action( 'learndash_quiz_completed', [ $this, 'quiz_completed' ], 10, 2 );

		// Manual grade
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ], 10 );
		add_action( 'wp_ajax_' . self::AJAX_EVAL_ACTION, [ $this, 'handle_manual_grade' ], 10 );

		// Manual solve
		add_action( 'wp_ajax_' . self::AJAX_SOLVE_ACTION, [ $this, 'handle_manual_solve' ], 10 );

		// Attention Dismiss
		add_action( 'wp_ajax_' . self::AJAX_ATTENTION_DISMISS, [ $this, 'handle_attention_dismiss' ], 10 );

		// Frontend display
		add_filter( 'the_content', [ $this, 'show_results' ], 100, 1 );
		add_action( 'wp_enqueue_scripts', [ $this, 'public_enqueue_scripts' ], 10 );

		// Dashboard widget
		add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widget' ] );
	}

	/**
	 * Add AI gr-aid column status to essays list table
	 *
	 * @param $columns
	 *
	 * @return array
	 */
	public function manage_essay_columns( $columns ) {
		return array_merge( $columns, [ 'ai-graid' => __( 'AI GrAid', 'ai-graid' ) ] );

	}

	/**
	 * Print AI gr-aid column status to essays list table
	 *
	 * @param $column_key
	 * @param $post_id
	 *
	 * @return void
	 */
	public function manage_essay_custom_column( $column_key, $post_id ) {
		if ( $column_key != 'ai-graid' ) {
			return;
		}

		$question_id = get_post_meta( $post_id, 'question_post_id', true );
		$enabled     = carbon_get_post_meta( $question_id, 'aiga_enable' ) === 'yes';

		echo  wp_kses( Utils::get_view( 'grading-status', [
			'essay_id'    => $post_id,
			'system'      => new System(),
			'inline_more' => true,
			'enabled'     => $enabled,
		] ), Utils::kses_allowed_html() );

	}


	/**
	 * Update the essay grading result
	 * @return array
	 */
	public function set_auto_grade_data( $result, $question_id ) {

		if ( 'on' !== carbon_get_theme_option( 'aiga_auto_grade' ) ) {
			Log::info( '- Auto grading is not enabled. Skipping.' );

			return $result;
		}

		$essay_id = isset( $result['e']['graded_id'] ) ? (int) $result['e']['graded_id'] : '';
		$question_post_id = get_post_meta($essay_id, 'question_post_id', true);
		if ( empty( $essay_id ) ) {
			Log::info( 'Grading not executed. Essay ID not found.' );

			return $result;
		}

		$enabled     = carbon_get_post_meta( $question_post_id, 'aiga_enable' ) === 'yes';
		if(!$enabled) {
			Log::info( 'Grading not executed. Ai-GrAid is disabled for question #'.$question_post_id.'.' );
			return $result;
		}

		$system = new System();
		$system->set_queued( $essay_id, true );
		Log::info( 'Auto grading started.' );
		Log::info( '- Processing essay: #' . $essay_id . ' for question: #' . $question_post_id );
		$outcome                      = $system->evaluate( $essay_id, 0, 1 );
		$points                       = isset( $outcome['points_obtained'] ) ? round( (float) $outcome['points_obtained'] ) : 0;
		$passed                       = isset( $outcome['passed'] ) ? (bool) $outcome['passed'] : false;
		$result['c']                  = $passed;
		$result['p']                  = $points;
		$result['e']['graded_status'] = 'graded';
		$result['e']['AnswerMessage'] = 'Automatically graded by Ai-GrAid';
		$system->set_queued( $essay_id, false );
		Log::info( '- Processed item: ' . $essay_id );
		Log::info( 'Auto grading finished.' );
		update_post_meta( $essay_id, '_aigraid_tmp_score', [
			'passed'         => $passed,
			'points_awarded' => $points,
			'status'         => 'graded',
		] );

		return $result;
	}


	/**
	 *
	 * @param $quizdata
	 * @param $user
	 *
	 * @return void
	 */
	public function quiz_completed( $quizdata, $user ) {

		static $prevent_recursion = false;
		if ( $prevent_recursion ) {
			return;
		}
		$prevent_recursion = true;

		if ( ! empty( $quizdata['graded'] ) ) {
			$system = new System();
			foreach ( $quizdata['graded'] as $index => $grading ) {
				$essay_id = isset( $grading['post_id'] ) ? $grading['post_id'] : '';
				$essay    = get_post( $essay_id );
				if ( empty( $essay ) ) {
					continue;
				}
				$outcome = get_post_meta( $essay_id, '_aigraid_tmp_score', true );
				if ( ! empty( $outcome ) ) {
					Log::info( '- Storing the auto-graded outcome' );
					$quiz_id     = get_post_meta( $essay_id, 'quiz_pro_id', true );
					$question_id = get_post_meta( $essay_id, 'question_id', true );
					$system->mark_as_graded( $quiz_id, $question_id, $essay, $outcome['points_awarded'] );
					delete_post_meta( $essay_id, '_aigraid_tmp_score' );
				}
			}
		}
	}

	/**
	 * Enqueue the required scripts
	 * @return void
	 */
	public function admin_enqueue_scripts() {

		$eval_url              = add_query_arg( [ 'action' => self::AJAX_EVAL_ACTION, '_wpnonce' => wp_create_nonce( 'aiga' ) ], admin_url( 'admin-ajax.php' ) );
		$mark_as_solved_url    = add_query_arg( [ 'action' => self::AJAX_SOLVE_ACTION, '_wpnonce' => wp_create_nonce( 'aiga' ) ], admin_url( 'admin-ajax.php' ) );
		$attention_dismiss_url = add_query_arg( [ 'action' => self::AJAX_ATTENTION_DISMISS, '_wpnonce' => wp_create_nonce( 'aiga' ) ], admin_url( 'admin-ajax.php' ) );

		wp_register_script( 'aiga-main', AIGA_URL . 'static/main.js', [ 'jquery' ], AIGA_VERSION, true );
		wp_enqueue_script( 'aiga-main' );
		wp_localize_script( 'aiga-main', 'AIGA_Main', [
			'eval_url'              => $eval_url,
			'mark_as_solved_url'    => $mark_as_solved_url,
			'attention_dismiss_url' => $attention_dismiss_url,
		] );

		wp_register_style( 'aiga-main', AIGA_URL . 'static/main.css', '', AIGA_VERSION, 'all' );
		wp_enqueue_style( 'aiga-main' );
	}

	/**
	 * Handle manual gradin g
	 * @return void
	 */
	public function handle_manual_grade() {

		Log::info( 'Manual grading started.' );

		if ( ! check_ajax_referer( 'aiga', '_wpnonce', false ) ) {
			wp_send_json_error( [
				'message' => __( 'Permission denied.', 'ai-graid' ),
			] );
		}

		$essay_id = isset( $_POST['essay_id'] ) ? (int) $_POST['essay_id'] : 0;

		if ( ! $essay_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid essay id.', 'ai-graid' ),
			] );
		}

		Log::info( '- Processing item: ' . $essay_id );

		$system = new System();
		$result = $system->evaluate( $essay_id, true );

		Log::info( '- Processed item:  ' . $essay_id );
		Log::info( '- Result: ' . wp_json_encode( $result ) );
		Log::info( 'Manual grading finished.' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [
				'message' => $result->get_error_message(),
			] );
		} else {

			$question_id = get_post_meta( $essay_id, 'question_post_id', true );

			wp_send_json_success( [
				'message'    => esc_html__( 'The grading process finished.', 'ai-graid' ),
				'resultHtml' =>  wp_kses( Utils::get_view( 'grading-outcome', [
					'essay_id'    => $essay_id,
					'question_id' => $question_id,
					'enabled'     => true,
				] ), Utils::kses_allowed_html() ),
				'points'     => isset( $result['points_obtained'] ) ? $result['points_obtained'] : null,
			] );
		}

		exit;
	}

	/**
	 * Handle manual solve
	 * @return void
	 */
	public function handle_manual_solve() {
		if ( ! check_ajax_referer( 'aiga', '_wpnonce', false ) ) {
			wp_send_json_error( [
				'message' => __( 'Permission denied.', 'ai-graid' ),
			] );
		}

		$essay_id = isset( $_POST['essay_id'] ) ? (int) $_POST['essay_id'] : 0;

		if ( ! $essay_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid essay id.', 'ai-graid' ),
			] );
		}

		$system = new System();
		$system->set_attention_needed( $essay_id, 0 );

		$question_id = get_post_meta( $essay_id, 'question_post_id', true );

		wp_send_json_success( [
			'message'    => esc_html__( 'The essay was un-flagged successfully.', 'ai-graid' ),
			'resultHtml' => wp_kses( Utils::get_view( 'grading-outcome', [
				'essay_id'    => $essay_id,
				'question_id' => $question_id,
				'enabled'     => true,
			] ), Utils::kses_allowed_html() ),
		] );
	}

	/**
	 * Attention dismiss
	 * @return void
	 */
	public function handle_attention_dismiss() {
		if ( ! check_ajax_referer( 'aiga', '_wpnonce', false ) ) {
			wp_send_json_error( [
				'message' => __( 'Permission denied.', 'ai-graid' ),
			] );
		}

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : '';

		if ( empty( $id ) ) {
			wp_send_json_error( [
				'message' => __( 'Dismiss failed.', 'ai-graid' ),
			] );
		} else {
			delete_post_meta( $id, 'aiga_attention_needed' );
			wp_send_json_success( [
				'message'    => __( 'Dismiss success', 'ai-graid' ),
				'resultHtml' => wp_kses( Utils::get_view( 'glance' ), Utils::kses_allowed_html() ),
			] );

		}

	}

	/**
	 * Show results on the frontend
	 * @return string
	 */
	public function show_results( $content ) {

		global $post;
		if ( empty( $post ) || $post->post_type != 'sfwd-essays' ) {
			return $content;
		}


		$results = apply_filters( 'aiga_essay_results_content', null, $post->ID );
		if ( ! empty( $results ) ) {
			$content .= $results;
		} else {
			$content .= wp_kses( Utils::get_view( 'essay-results', [ 'essay_id' => $post->ID ] ), Utils::kses_allowed_html() );
		}

		return $content;
	}

	/**
	 * Enqueue public scripts
	 * @return void
	 */
	public function public_enqueue_scripts() {
		wp_register_style( 'aiga-public', AIGA_URL . 'static/public.css', '', AIGA_VERSION, 'all' );
		if ( is_singular( 'sfwd-essays' ) ) {
			wp_enqueue_style( 'aiga-public' );
		}
	}

	/**
	 * Register dashboard widget
	 * @return void
	 */
	public function register_dashboard_widget() {
		wp_add_dashboard_widget(
			'aigraid-overview',
			esc_html__( 'AI GrAid Overview', 'ai-graid' ),
			[ $this, 'show_dashboard_widget' ]
		);
	}

	/**
	 * Show dashboard widget
	 * @return void
	 */
	public function show_dashboard_widget() {

		echo wp_kses(Utils::get_view( 'glance' ), Utils::kses_allowed_html());
	}

}