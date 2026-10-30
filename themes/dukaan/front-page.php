<?php
/**
 * Dukaan — home page: hero, featured categories, latest products,
 * sale section (sirf jab sale products hon), trust strip.
 *
 * @package Dukaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$dukaan_has_woo    = class_exists( 'WooCommerce' );
$dukaan_hero_head  = trim( (string) get_theme_mod( 'dukaan_hero_heading', '' ) );
$dukaan_hero_text  = trim( (string) get_theme_mod( 'dukaan_hero_text', '' ) );
$dukaan_shop_url   = $dukaan_has_woo && function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'shop' ) > 0
	? get_permalink( wc_get_page_id( 'shop' ) )
	: home_url( '/' );
?>

<main id="primary" class="dukaan-main">

	<section class="dukaan-hero">
		<div class="dukaan-container">
			<h1 class="dukaan-hero-heading"><?php echo esc_html( '' !== $dukaan_hero_head ? $dukaan_hero_head : get_bloginfo( 'name' ) ); ?></h1>
			<?php if ( '' !== $dukaan_hero_text ) : ?>
				<p class="dukaan-hero-text"><?php echo esc_html( $dukaan_hero_text ); ?></p>
			<?php endif; ?>
			<?php if ( $dukaan_has_woo ) : ?>
				<a class="dukaan-btn dukaan-btn-gold" href="<?php echo esc_url( $dukaan_shop_url ); ?>"><?php esc_html_e( 'Abhi Shopping Karein', 'dukaan' ); ?></a>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( $dukaan_has_woo ) : ?>

		<?php
		$dukaan_cats = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'number'     => 4,
			'parent'     => 0,
			'exclude'    => array( (int) get_option( 'default_product_cat', 0 ) ),
		) );
		if ( $dukaan_cats && ! is_wp_error( $dukaan_cats ) ) :
			?>
			<section class="dukaan-section">
				<div class="dukaan-container">
					<h2 class="dukaan-section-title"><?php esc_html_e( 'Categories', 'dukaan' ); ?></h2>
					<div class="dukaan-cat-grid">
						<?php foreach ( $dukaan_cats as $dukaan_cat ) : ?>
							<?php
							$dukaan_thumb_id = (int) get_term_meta( $dukaan_cat->term_id, 'thumbnail_id', true );
							$dukaan_img      = $dukaan_thumb_id ? wp_get_attachment_image_url( $dukaan_thumb_id, 'medium' ) : '';
							$dukaan_link     = get_term_link( $dukaan_cat );
							if ( is_wp_error( $dukaan_link ) ) {
								continue;
							}
							?>
							<a class="dukaan-cat-card" href="<?php echo esc_url( $dukaan_link ); ?>">
								<?php if ( $dukaan_img ) : ?>
									<img src="<?php echo esc_url( $dukaan_img ); ?>" alt="<?php echo esc_attr( $dukaan_cat->name ); ?>" loading="lazy">
								<?php endif; ?>
								<span class="dukaan-cat-name"><?php echo esc_html( $dukaan_cat->name ); ?></span>
								<span class="dukaan-cat-count">
									<?php
									printf(
										/* translators: %d: product count */
										esc_html( _n( '%d product', '%d products', $dukaan_cat->count, 'dukaan' ) ),
										(int) $dukaan_cat->count
									);
									?>
								</span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<section class="dukaan-section">
			<div class="dukaan-container">
				<h2 class="dukaan-section-title"><?php esc_html_e( 'Naye Products', 'dukaan' ); ?></h2>
				<?php echo do_shortcode( '[products limit="8" columns="4" orderby="date" order="DESC"]' ); ?>
			</div>
		</section>

		<?php
		$dukaan_on_sale = function_exists( 'wc_get_product_ids_on_sale' ) ? wc_get_product_ids_on_sale() : array();
		if ( $dukaan_on_sale ) :
			?>
			<section class="dukaan-section dukaan-sale-section">
				<div class="dukaan-container">
					<h2 class="dukaan-section-title"><?php esc_html_e( 'Sale — Khaas Riayat', 'dukaan' ); ?></h2>
					<?php echo do_shortcode( '[products limit="4" columns="4" on_sale="true"]' ); ?>
				</div>
			</section>
		<?php endif; ?>

	<?php else : ?>

		<section class="dukaan-section">
			<div class="dukaan-container">
				<?php
				// Baghair WooCommerce ke: taza posts dikhao taake home khaali na lage.
				$dukaan_posts = new WP_Query( array(
					'post_type'           => 'post',
					'posts_per_page'      => 6,
					'ignore_sticky_posts' => true,
				) );
				if ( $dukaan_posts->have_posts() ) :
					?>
					<h2 class="dukaan-section-title"><?php esc_html_e( 'Taza Posts', 'dukaan' ); ?></h2>
					<div class="dukaan-post-grid">
						<?php
						while ( $dukaan_posts->have_posts() ) :
							$dukaan_posts->the_post();
							get_template_part( 'template-parts/content', 'card' );
						endwhile;
						?>
					</div>
					<?php
					wp_reset_postdata();
				endif;
				?>
			</div>
		</section>

	<?php endif; ?>

	<section class="dukaan-trust">
		<div class="dukaan-container dukaan-trust-grid">
			<div class="dukaan-trust-item">
				<span class="dukaan-trust-icon" aria-hidden="true">&#128666;</span>
				<h3><?php esc_html_e( 'Pure Pakistan Delivery', 'dukaan' ); ?></h3>
				<p><?php esc_html_e( 'Har sheher mein delivery — charges order par wazeh bta diye jate hain.', 'dukaan' ); ?></p>
			</div>
			<div class="dukaan-trust-item">
				<span class="dukaan-trust-icon" aria-hidden="true">&#128176;</span>
				<h3><?php esc_html_e( 'Advance Payment', 'dukaan' ); ?></h3>
				<p><?php esc_html_e( 'Advance payment par order confirm hota hai — JazzCash / EasyPaisa / bank.', 'dukaan' ); ?></p>
			</div>
			<div class="dukaan-trust-item">
				<span class="dukaan-trust-icon" aria-hidden="true">&#128172;</span>
				<h3><?php esc_html_e( 'WhatsApp Order', 'dukaan' ); ?></h3>
				<p><?php esc_html_e( 'Product page se seedha WhatsApp par order — koi lambi checkout nahi.', 'dukaan' ); ?></p>
			</div>
		</div>
	</section>

</main>

<?php
get_footer();
