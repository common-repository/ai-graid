<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exif if accessed directly.
?>

<div class="aiga-attention-needed-wrap">
<?php

use AIGrAid\Plugin\System;

$system                 = new System();
$attention_needed_count = $system->count_flagged_essays();
?>

<?php if ( $attention_needed_count > 0 ): ?>

	<?php
	$per_page                 = apply_filters( 'aiga_attention_needed_per_page', 15 );
	$page                     = isset( $_GET['an_page'] ) ? (int) $_GET['an_page'] : 1;
	$total_pages              = $attention_needed_count >= $per_page ? $attention_needed_count / $per_page : 1;
	$attention_needed_records = $system->get_flagged_essays( [ 'per_page' => $per_page, 'page' => $page ] )
	?>

    <div class="aiga-attention-needed aiga-attention-needed--nok">

        <div class="aiga-attention-needed--status">
            <p class="aiga-attention-needed--icon"><span class="dashicons dashicons-warning"></span></p>
            <h2 class="aiga-attention-needed--title">
		        <?php echo esc_html( sprintf( __( '%d %s need attention', 'ai-graid' ), $attention_needed_count, $attention_needed_count == 1 ? esc_attr__( 'essay', 'ai-graid' ) : esc_attr__( 'essays', 'ai-graid' ) ) ); ?>
            </h2>
        </div>
        <table class="aiga-attention-needed--table">
            <thead>
            <tr>
                <th><?php esc_html_e( 'Title', 'ai-graid' ); ?></th>
                <th style="width: 130px; text-align:center;"></th>
            </tr>
            </thead>
            <tbody>
			<?php foreach ( $attention_needed_records as $attention_needed_record ): ?>
                <tr>
                    <td>
						<?php echo esc_html( get_the_title( $attention_needed_record ) ); ?> (<strong>#<?php echo esc_html( $attention_needed_record ); ?></strong>)
                    </td>
                    <td style="text-align:center;">
                        <a href="<?php echo esc_url( admin_url( sprintf( 'post.php?post=%s&action=edit', $attention_needed_record ) ) ); ?>" target="_blank" class="button button-primary button-small"><?php esc_html_e( 'Review', 'ai-graid' ); ?></a>
                        <a target="_blank" class="button button-secondary button-small aiga-dismiss-attention-needed" data-id="<?php echo (int) $attention_needed_record; ?>"><?php esc_html_e( 'Dismiss', 'ai-graid' ); ?></a>
                    </td>
                </tr>
			<?php endforeach; ?>
            </tbody>
        </table>

		<?php if ( $attention_needed_count > $per_page && $page < $total_pages ): ?>
            <p class="aiga-attention-needed--pagination">
                <a class="button button-primary" href="<?php echo esc_url( add_query_arg( [ 'an_page' => $page + 1 ], admin_url( '/index.php' ) ) ); ?>"><?php esc_html_e( 'Load more', 'ai-graid' ); ?></a>
            </p>
		<?php endif; ?>
    </div>
<?php else: ?>
    <div class="aiga-attention-needed aiga-attention-needed--ok">
        <div class="aiga-attention-needed--status">
            <p class="aiga-attention-needed--icon"><span class="dashicons dashicons-yes"></span></p>
            <h2 class="aiga-attention-needed--title"><?php esc_html_e( 'All caught up', 'ai-graid' ); ?></h2>
            <p><?php esc_html_e('Looks like you are doing great. Keep it up!', 'ai-graid'); ?></p>
        </div>
    </div>
<?php endif; ?>

</div>
