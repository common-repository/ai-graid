<?php
/* @var int $essay_id */
/* @var \AIGrAid\Plugin\System $system */
/* @var $inline_more */

if ( ! defined( 'ABSPATH' ) ) exit; // Exif if accessed directly.

?>

<?php if(isset($enabled) && !$enabled): ?>
    <span style="color: black;"><?php esc_html_e( 'Not enabled', 'ai-graid' ); ?></span>
<?php else: ?>

	<?php if ( $system->is_error( $essay_id ) ): ?>
        <span style="color: red;"><?php esc_html_e( 'Failed', 'ai-graid' ); ?></span>
	<?php elseif ( $system->is_complete( $essay_id ) ): ?>
        <span style="color: green;"><?php esc_html_e( 'Finished', 'ai-graid' ); ?></span>
		<?php if ( isset( $inline_more ) && $inline_more ): ?>
            <span>|</span>
            <?php if($system->is_passed( $essay_id )): ?>
                <span style="color: green;"><?php esc_html_e( 'Pass', 'ai-graid' ); ?></span>
            <?php else: ?>
                <span style="color: red;"><?php esc_html_e( 'No pass', 'ai-graid' ); ?></span>
			<?php endif; ?>
        <?php endif; ?>
    <?php elseif ( $system->is_locked( $essay_id ) ): ?>
        <span style="color: blue;"><?php esc_html_e( 'Evaluating', 'ai-graid' ); ?></span>
	<?php elseif ( $system->is_queued( $essay_id ) ): ?>
        <span style="color: grey;"><?php esc_html_e( 'Queued', 'ai-graid' ); ?></span>
	<?php else: ?>
        <span style="color: black;"><?php esc_html_e( 'Not started yet', 'ai-graid' ); ?></span>
	<?php endif; ?>

<?php endif; ?>
