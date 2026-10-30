<?php
/**
 * Dukaan — comments template.
 *
 * @package Dukaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( post_password_required() ) {
	return;
}
?>
<div id="comments" class="dukaan-comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="dukaan-comments-title">
			<?php
			$dukaan_count = get_comments_number();
			printf(
				/* translators: %s: comment count */
				esc_html( _n( '%s comment', '%s comments', $dukaan_count, 'dukaan' ) ),
				esc_html( number_format_i18n( $dukaan_count ) )
			);
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments( array(
				'style'       => 'ol',
				'short_ping'  => true,
				'avatar_size' => 48,
			) );
			?>
		</ol>

		<?php
		the_comments_pagination( array(
			'prev_text' => __( '&larr; Pichhla', 'dukaan' ),
			'next_text' => __( 'Agla &rarr;', 'dukaan' ),
		) );
		?>
	<?php endif; ?>

	<?php
	if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) :
		?>
		<p class="dukaan-no-comments"><?php esc_html_e( 'Comments band hain.', 'dukaan' ); ?></p>
		<?php
	endif;

	comment_form();
	?>
</div>
