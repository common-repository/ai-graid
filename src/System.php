<?php

namespace AIGrAid\Plugin;

class System {

	const LOCK_TIME = 10 * MINUTE_IN_SECONDS;

	private $http;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->http = new Http();
	}


	/**
	 * Is complete?
	 *
	 * @param $essay_id
	 *
	 * @return false|mixed
	 */
	public function is_complete( $essay_id ) {

		$result = $this->get_result( $essay_id );

		return isset( $result['score'] );
	}


	/**
	 * Is complete?
	 *
	 * @param $essay_id
	 *
	 * @return false|mixed
	 */
	public function is_error( $essay_id ) {
		$result = $this->get_result( $essay_id );

		return isset( $result['detail'] ) || isset( $result['error'] );
	}

	/**
	 * Has result?
	 *
	 * @param $essay_id
	 *
	 * @return bool|false
	 */
	public function has_result( $essay_id ) {
		$result = $this->get_result( $essay_id );

		return (bool) $result;
	}

	/**
	 * Return's the result
	 *
	 * @param $essay_id
	 *
	 * @return mixed
	 */
	public function get_result( $essay_id ) {
		$result = get_post_meta( $essay_id, 'aiga_result', true );

		return $result;
	}

	/**
	 * Saves the result
	 *
	 * @param $essay_id
	 * @param $data
	 * @param  bool  $auto_grade
	 *
	 * @return void
	 */
	public function save_result( $essay_id, $data, $auto_grade = false ) {

		if ( $auto_grade ) {
			$this->set_auto_graded( $essay_id, 1 );
		}

		if ( isset( $data['passed'] ) && (bool) $data['passed'] ) {
			$this->set_passed( $essay_id, 1 );
		} else {
			$this->set_passed( $essay_id, 0 );

			// In case of user error
			if ( $auto_grade ) {
				$this->set_attention_needed( $essay_id, 1 );
				Log::info( '- Attention needed SET' );
			}
		}

		// In case of API connection error.
		if ( $auto_grade && isset( $data['error'] ) ) {
			$this->set_attention_needed( $essay_id, 1 );
		}

		update_post_meta( $essay_id, 'aiga_result', $data );

		if ( apply_filters( 'aiga_create_comment_with_explanation', true, $data ) ) {
			$comment_content = '';
			if ( ! empty( $data['explanation'] ) ) {
				$comment_content .= $data['explanation'];
			}
			if ( ! empty( $comment_content ) ) {
				$comment_data = [
					'comment_post_ID'      => $essay_id,
					'comment_author'       => apply_filters( 'aiga_essay_comment_author', 'AI GrAid' ),
					'comment_author_email' => apply_filters( 'aiga_essay_comment_email', 'info@aigraid.com' ),
					'comment_author_url'   => apply_filters( 'aiga_essay_comment_url', 'https://aigraid.com' ),
					'comment_date'         => wp_date( 'Y-m-d H:i:s' ),
					'comment_date_gmt'     => date( 'Y-m-d H:i:s' ),
					'comment_content'      => $comment_content,
					'comment_approved'     => 1,
					'comment_agent'        => 'Ai GrAid',
					'comment_type'         => 'comment',
				];
				$id           = wp_insert_comment( $comment_data );

				Log::info( 'Comment:' );
				Log::info( $comment_data );
				Log::info( 'Outcome: ' . ( $id ? $id : '0' ) );
			} else {
				Log::info('Comment not posted because the explanation was empty.');
				Log::info($data);
			}
		}
	}

	/**
	 * Clears the result
	 *
	 * @param $essay_id
	 *
	 * @return void
	 */
	public function clear( $essay_id ) {
		delete_post_meta( $essay_id, 'aiga_result' );
		delete_post_meta( $essay_id, 'aiga_auto_graded' );
		delete_post_meta( $essay_id, 'aiga_attention_needed' );
	}

	/**
	 * Evaluates a post
	 *
	 * Returns array with the result or false if already locked for processing or null if results are not able to be determined.
	 *
	 * @param $essay_id
	 * @param  bool  $force
	 * @param  bool  $auto_grade
	 *
	 * @return bool|array|null
	 */
	public function evaluate( $essay_id, $force = false, $auto_grade = false ) {

		if ( $force ) {
			$this->unlock( $essay_id );
			$this->clear( $essay_id );
		}

		if ( $this->has_result( $essay_id ) ) {
			$this->unlock( $essay_id );

			return $this->get_result( $essay_id );
		}

		if ( $this->is_locked( $essay_id ) ) {
			return false;
		}

		$this->lock( $essay_id );

		$question_post_id = (int) get_post_meta( $essay_id, 'question_post_id', true );
		$quiz_id          = (int) get_post_meta( $essay_id, 'quiz_pro_id', true );
		$question_id      = (int) get_post_meta( $essay_id, 'question_id', true );
		$api_auth         = get_option( 'aiga_auth' );

		$req_data = [
			'user_id'            => isset( $api_auth['user_id'] ) ? $api_auth['user_id'] : '',
			'question'           => get_post_field( 'post_title', $essay_id ),
			'actual_answer'      => get_post_field( 'post_content', $essay_id ),
			'correct_answer'     => carbon_get_post_meta( $question_post_id, 'aiga_expected_answer' ),
			'passing_percentage' => (double) carbon_get_post_meta( $question_post_id, 'aiga_passing_percentage' ),
			'points'             => (int) get_post_meta( $question_post_id, 'question_points', true ),
		];

		$result = $this->dispatch( $req_data );

		Log::info( $result );

		if ( is_wp_error( $result ) ) {
			$this->save_result( $essay_id, [ 'error' => $result->get_error_message() ], $auto_grade );
		} else {
			$this->save_result( $essay_id, $result, $auto_grade );
			if ( ! $auto_grade ) {
				$essay  = get_post( $essay_id );
				$points = isset( $result['points_obtained'] ) ? round( (float) $result['points_obtained'] ) : 0;
				$this->mark_as_graded( $quiz_id, $question_id, $essay, $points );

				learndash_update_quiz_data( $quiz_id, $question_id, [
					'updated_question_score'    => $points,
					'points_awarded_difference' => $points,
					'score_difference'          => 1,
				], $essay );
			}
		}

		$this->unlock( $essay_id );

		return $result;

	}

	/**
	 * Update the essay data
	 *
	 * @param $quiz_id
	 * @param $question_id
	 * @param $essay
	 * @param $points
	 *
	 * @return void
	 */
	public function mark_as_graded( $quiz_id, $question_id, $essay, $points ) {
		learndash_update_submitted_essay_data( $quiz_id, $question_id, $essay, [
			'post_id'        => $essay->ID,
			'points_awarded' => $points,
			'status'         => 'graded',
		] );
		wp_update_post( [
			'ID'          => $essay->ID,
			'post_status' => 'graded',
		] );
	}

	/**
	 * Lock
	 *
	 * @param $essay_id
	 *
	 * @return void
	 */
	public function lock( $essay_id ) {
		update_post_meta( $essay_id, 'aiga_lock', 1 );
	}

	/**
	 * Unlock
	 *
	 * @param $essay_id
	 *
	 * @return void
	 */
	public function unlock( $essay_id ) {
		delete_post_meta( $essay_id, 'aiga_lock' );
		update_postmeta_cache( [ $essay_id ] );
	}

	/**
	 * Check if locked
	 *
	 * @param $essay_id
	 *
	 * @return bool
	 */
	public function is_locked( $essay_id ) {
		$time = (int) get_post_meta( $essay_id, 'aiga_lock', true );

		return $this->is_lock_valid( $time );
	}

	/**
	 * Check if lock is expired
	 *
	 * @param $time
	 *
	 * @return bool
	 */
	public function is_lock_valid( $time ) {
		if ( $time <= 0 ) {
			return false;
		}

		if ( ( time() - $time ) >= self::LOCK_TIME ) {
			return false;
		}

		return true;

	}

	/**
	 * Mark the object as queued
	 *
	 * @param $essay_id
	 * @param $flag
	 *
	 * @return void
	 */
	public function set_queued( $essay_id, $flag ) {
		if ( $flag ) {
			update_post_meta( $essay_id, '_aiga_queued', time() );
		} else {
			delete_post_meta( $essay_id, '_aiga_queued' );
		}
	}


	/**
	 * Clear the queued status
	 *
	 * @param $essay_id
	 *
	 * @return void
	 */
	public function maybe_clear_queue_flag( $essay_id ) {
		$queued_at = $this->get_queued_at( $essay_id );

		if ( $queued_at > 1 && ( ( time() - $queued_at ) >= ( 10 * MINUTE_IN_SECONDS ) ) ) {
			$this->set_queued( $essay_id, false );
		}
	}

	/**
	 * Is queued?
	 *
	 * @param $essay_id
	 *
	 * @return bool
	 */
	public function is_queued( $essay_id ) {
		return $this->get_queued_at( $essay_id ) > 1;
	}

	/**
	 * Returns the queue timestamp in UTC
	 *
	 * @param $essay_id
	 *
	 * @return int
	 */
	public function get_queued_at( $essay_id ) {
		return (int) get_post_meta( $essay_id, '_aiga_queued', true );
	}

	/**
	 * Is attention needed?
	 *
	 * @param $essay_id
	 *
	 * @return bool
	 */
	public function is_attention_needed( $essay_id ) {
		return 1 === (int) get_post_meta( $essay_id, 'aiga_attention_needed', true );
	}

	/**
	 * Set attention needed
	 *
	 * @param $essay_id
	 * @param $flag
	 *
	 * @return void
	 */
	public function set_attention_needed( $essay_id, $flag ) {
		if ( $flag ) {
			update_post_meta( $essay_id, 'aiga_attention_needed', 1 );
		} else {
			update_post_meta( $essay_id, 'aiga_attention_needed', 0 );
		}

	}

	/**
	 * Is passed?
	 *
	 * @param $essay_id
	 *
	 * @return bool
	 */
	public function is_passed( $essay_id ) {
		return 1 === (int) get_post_meta( $essay_id, 'aiga_passed', true );
	}

	/**
	 * Set passed
	 *
	 * @param $essay_id
	 * @param $flag
	 *
	 * @return void
	 */
	public function set_passed( $essay_id, $flag ) {
		if ( $flag ) {
			update_post_meta( $essay_id, 'aiga_passed', 1 );
		} else {
			update_post_meta( $essay_id, 'aiga_passed', 0 );
		}
	}

	/**
	 * Is auto gaaded @param $essay_id
	 *
	 * @return bool
	 */
	public function is_auto_graded( $essay_id ) {
		return 1 === (int) get_post_meta( $essay_id, 'aiga_auto_graded', true );
	}

	/**
	 * Set auto graded
	 *
	 * @param $essay_id
	 * @param $flag
	 *
	 * @return void
	 */
	public function set_auto_graded( $essay_id, $flag ) {
		if ( $flag ) {
			update_post_meta( $essay_id, 'aiga_auto_graded', 1 );
		} else {
			update_post_meta( $essay_id, 'aiga_auto_graded', 0 );
		}
	}


	/**
	 * Return the flagged essays that need human attention
	 * @return array
	 */
	public function get_flagged_essays( $params = [] ) {

		global $wpdb;

		$params = wp_parse_args( $params, [
			'page'     => 1,
			'per_page' => 50,
		] );

		$per_page = (int) $params['per_page'];
		$page     = (int) $params['page'];
		$offset   = ( $page - 1 ) * $per_page;
		$results  = $wpdb->get_col( $wpdb->prepare( "SELECT post_id from {$wpdb->postmeta} PM WHERE PM.meta_key='aiga_attention_needed' AND PM.meta_value='1' LIMIT %d OFFSET %d", $per_page, $offset ) );

		if ( ! empty( $results ) ) {
			$results = array_map( 'intval', $results );
		}

		return $results;
	}

	/**
	 * Return the flagged essays that need human attention
	 * @return int
	 */
	public function count_flagged_essays() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(post_id) from {$wpdb->postmeta} PM WHERE PM.meta_key='aiga_attention_needed' AND PM.meta_value='1'" );
	}

	/**
	 * Dispatch API request
	 *
	 * @param $data
	 *
	 * @return array|mixed|\WP_Error
	 */
	public function dispatch( $data ) {

		$response = $this->http->post( 'evaluate', $data );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = wp_remote_retrieve_body( $response );


		Log::info( 'Request:' );
		Log::info( $data );
		Log::info( $body );

		$decoded = json_decode( $body, true );

		return json_last_error() === JSON_ERROR_NONE ? $decoded : new \WP_Error( 'invalid_response', __( 'Invalid response received from the API. Please contact support!', 'ai-graid' ) );
	}

	/**
	 * Check the api connection
	 * @return array
	 * @throws \Exception
	 */
	public function check_api() {

		$api_key  = $this->http->get_api_key();
		$response = $this->http->get( 'api_keys/status', [] );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'API Error: Unable to reach API.' );
		}
		$body = wp_remote_retrieve_body( $response );
		$body = json_decode( $body, true );

		if ( ! empty( $body['detail'] ) ) {
			delete_option( 'aiga_auth' );
			throw new \Exception( 'API Error: ' . $body['detail'] );
		} else {
			update_option( 'aiga_auth', $body );
			if ( isset( $body['revoked'] ) && false === $body['revoked'] ) {
				return [ 'expires_at' => $body['expires_at'] ];
			} elseif ( isset( $body['revoked'] ) && true === $body['revoked'] ) {
				throw new \Exception( 'API Key Revoked.' );
			} else {
				throw new \Exception( 'API Error: Unable to read response.' );
			}
		}
	}

}