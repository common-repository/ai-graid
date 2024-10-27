<?php
/*  @var bool $enabled */
/* @var int $essay_id */
/* @var int $question_id */

use AIGrAid\Plugin\Utils;

if ( ! defined( 'ABSPATH' ) ) exit; // Exif if accessed directly.

$system = new \AIGrAid\Plugin\System();
$system->maybe_clear_queue_flag( $essay_id );

$has_result = $system->has_result( $essay_id );
$is_error   = $system->is_error( $essay_id );
$is_queued  = $system->is_queued( $essay_id );

$quiz_id = get_post_meta($essay_id, 'quiz_post_id', true);

?>

<?php if ( $enabled ): ?>

	<?php
	$result      = $system->get_result( $essay_id );
	$can_restart = $is_error || ( isset( $result['passed'] ) && ! $result['passed'] );
	?>

    <p class="aiga-status">
        <strong><?php esc_html_e( 'Status', 'ai-graid' ); ?></strong>:
		<?php
		    echo wp_kses( Utils::get_view( 'grading-status', [ 'system' => $system, 'essay_id' => $essay_id, 'inline_more' => false, 'enabled' => $enabled ] ), Utils::kses_allowed_html());
        ?>
    </p>

	<?php if ( isset( $result['score'] ) ): ?>
        <p class="aiga-status">
            <strong><?php esc_html_e( 'Passed', 'ai-graid' ); ?></strong>:
            <?php if( isset( $result['passed'] ) ): ?>
                <?php if( (int) $result['passed'] === 1 ): ?>
                    <span style="color:green;"><?php esc_html_e( 'Yes', 'ai-graid' ); ?></span>
	            <?php else: ?>
                    <span style="color:red;"><?php esc_html_e( 'No', 'ai-graid' ); ?></span>
	            <?php endif; ?>
            <?php else: ?>
                <span><?php esc_html_e( 'Could not be determined', 'ai-graid' ); ?></span>
            <?php endif; ?>
        </p>

        <p class="aiga-status"><strong><?php esc_html_e( 'Points', 'ai-graid' ); ?></strong>: <span><?php echo (double) $result['score'] . '%'; ?></span></p>
	<?php endif; ?>

	<?php if ( isset( $result['explanation'] ) ): ?>
        <hr/><p class="aiga-status"><span><?php echo esc_html( $result['explanation'] ); ?></span></p>
	<?php endif; ?>

	<?php if ( isset( $result['detail'] ) ): ?>
        <hr/><p class="aiga-status"><span><?php echo esc_html( $result['detail'] ); ?></span></p>
	<?php endif; ?>

	<?php if ( isset( $result['error'] ) ): ?>
        <hr/><p class="aiga-status"><span><?php echo esc_html( $result['error'] ); ?></span></p>
	<?php endif; ?>

	<?php if ( !$is_queued && ( ! $has_result || $can_restart) ): ?>

		<?php
		$btn_text = $can_restart ? esc_html__( 'Restart', 'ai-graid' ) : esc_html__( 'Start', 'ai-graid' );
		?>
        <button id="aiga-evaluate" data-id="<?php echo (int) $essay_id; ?>" data-ltext="<?php esc_html_e( 'Grading...', 'ai-graid' ); ?>" class="button button-primary" type="submit"><?php echo esc_html( $btn_text ); ?></button>

	<?php endif; ?>

	<?php if ( $system->is_attention_needed( $essay_id ) ): ?>
        <hr/>
        <div class="aiga-attention">
            <p><strong><?php esc_html_e( 'Attention Needed', 'ai-graid' ); ?></strong></p>
            <p>
                <?php echo wp_kses(sprintf(__( 'Looks like this essay was automatically graded by %s but the result was not satisfiable therefore the essay was flagged and needs attention by a human. If you already reviewed this, please mark it as resolved to unflag it.', 'ai-graid' ), '<strong>' . esc_html__( 'Ai GrAID', 'ai-graid' ) . '</strong>'), Utils::kses_allowed_html()); ?>
            </p>
            <button id="aiga-mark-as-solved" data-id="<?php echo (int) $essay_id; ?>" class="button button-secondary" type="button"><?php esc_html_e( 'Mark as solved', 'ai-graid' ); ?></button>
        </div>
	<?php endif; ?>

<?php else: ?>

    <p><?php esc_html_e( 'Grading is not enabled.', 'ai-graid' ); ?></p>
    <p>
        <a href="<?php echo esc_url(admin_url('post.php?post='.$question_id.'&action=edit&quiz_id='.$quiz_id)); ?>"><?php esc_html_e('View Question', 'ai-graid'); ?></a>
    </p>

<?php endif; ?>
