<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exif if accessed directly.

/* @var int $essay_id */

?>

<?php if ( current_user_can('administrator') ): ?>

	<?php
	$question_post_id = (int) get_post_meta( $essay_id, 'question_post_id', true );
	$correct_answer   = carbon_get_post_meta( $question_post_id, 'aiga_expected_answer' );
	?>
    <div class="aiga-wrap">
        <h4><?php esc_html_e('Correct answer', 'ai-graid'); ?></h4>
        <div class="aiga-expected-answer">
			<?php echo esc_html( $correct_answer ); ?>
        </div>
		<?php do_action( 'aiga_essay_result', $essay_id ); ?>
    </div>

<?php endif; ?>
