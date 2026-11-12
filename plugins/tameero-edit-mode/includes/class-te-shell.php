<?php
/**
 * TE_Shell — admin bar toggle, assets, aur Edit Mode ka shell markup.
 *
 * Zero-footprint usool: assets aur markup SIRF tab aate hain jab
 *  - user logged-in admin (edit_theme_options) ho,
 *  - theme ke pas edit config ho,
 *  - aur page front page ho (v1 mein Edit Mode sirf homepage par hai).
 * Visitor ko na script, na style, na markup — kuch nahi milta.
 *
 * @package TameeroEditMode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TE_Shell {

	/** @var bool Is request mein shell active hai ya nahi. */
	private static $active = false;

	/**
	 * Admin bar mein Edit Mode ka toggle node.
	 *
	 * @param WP_Admin_Bar $bar Admin bar.
	 */
	public static function admin_bar_node( $bar ) {
		if ( ! tameero_edit_available() || ! is_front_page() ) {
			return;
		}
		$bar->add_node(
			[
				'id'    => 'tameero-edit-mode',
				'title' => '<span class="te-ab-dot"></span>' . esc_html__( 'ایڈٹ موڈ:', 'tameero-edit-mode' ) . ' <span class="te-ab-state">' . esc_html__( 'بند', 'tameero-edit-mode' ) . '</span>',
				'href'  => '#',
				'meta'  => [ 'class' => 'te-ab-toggle' ],
			]
		);
	}

	/**
	 * Front page + admin + config ho to hi assets enqueue karo.
	 */
	public static function enqueue() {
		if ( ! tameero_edit_available() || ! is_front_page() ) {
			return;
		}
		self::$active = true;

		wp_enqueue_style( 'tameero-edit-mode', TAMEERO_EDIT_URL . 'assets/css/edit-mode.css', [], TAMEERO_EDIT_VERSION );
		wp_enqueue_script( 'tameero-edit-mode', TAMEERO_EDIT_URL . 'assets/js/edit-mode.js', [], TAMEERO_EDIT_VERSION, true );

		$tax        = TE_Config::categories_taxonomy();
		$categories = [];
		$terms      = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => false ] );
		if ( is_wp_error( $terms ) ) {
			$terms = [];
		}
		foreach ( $terms as $cat ) {
			$categories[] = [
				'id'    => (int) $cat->term_id,
				'name'  => $cat->name,
				'slug'  => $cat->slug,
				'count' => (int) $cat->count,
			];
		}

		wp_localize_script(
			'tameero-edit-mode',
			'TameeroEdit',
			[
				'restUrl'    => esc_url_raw( rest_url( 'tameero/v1/' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'config'     => [
					'theme'    => get_stylesheet(),
					'sections' => TE_Config::sections(),
					'global'   => TE_Config::global_block(),
					'categoriesTitle' => TE_Config::load()['categories_title'] ?? null,
					'sectionsNote'    => TE_Config::load()['sections_note'] ?? null,
				],
				'values'     => TE_Config::current_values(),
				'categories' => $categories,
				'autoOn'     => isset( $_GET['tameero_edit'] ) && '1' === sanitize_key( wp_unslash( $_GET['tameero_edit'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sirf UI auto-on flag hai, koi action nahi hota
				'autoSection' => isset( $_GET['tameero_section'] ) ? sanitize_key( wp_unslash( $_GET['tameero_section'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sirf panel ka ibtedai section chunav hai
				'i18n'       => [
					'on'           => __( 'آن', 'tameero-edit-mode' ),
					'off'          => __( 'بند', 'tameero-edit-mode' ),
					'globalTitle'  => __( 'عمومی ترتیبات', 'tameero-edit-mode' ),
					'save'         => __( 'محفوظ کریں', 'tameero-edit-mode' ),
					'saved'        => __( 'محفوظ ہو گیا', 'tameero-edit-mode' ),
					'retry'        => __( 'دوبارہ کوشش کریں', 'tameero-edit-mode' ),
					'reset'        => __( 'تبدیلیاں واپس لیں', 'tameero-edit-mode' ),
					'unsaved'      => __( 'غیر محفوظ تبدیلیاں', 'tameero-edit-mode' ),
					'unsavedTitle' => __( 'تبدیلیاں محفوظ نہیں ہوئیں!', 'tameero-edit-mode' ),
					'unsavedText'  => __( 'کیا آپ محفوظ کیے بغیر باہر نکلنا چاہتے ہیں؟', 'tameero-edit-mode' ),
					'saveAndExit'  => __( 'محفوظ کریں اور نکلیں', 'tameero-edit-mode' ),
					'leaveNoSave'  => __( 'محفوظ کیے بغیر نکلیں', 'tameero-edit-mode' ),
					'cancel'       => __( 'منسوخ کریں', 'tameero-edit-mode' ),
					'saveError'    => __( 'انٹرنیٹ یا سرور کا مسئلہ ہے — ترتیبات محفوظ نہیں ہو سکیں۔', 'tameero-edit-mode' ),
					'appliesOnSave' => __( 'محفوظ کرنے پر تبدیل ہوگی', 'tameero-edit-mode' ),
					'editSection'  => __( 'ترمیم کریں', 'tameero-edit-mode' ),
				],
			]
		);
	}

	/**
	 * Shell ka markup: drawer (desktop par right dock, mobile par
	 * bottom sheet), exit-confirm modal, toast. Sirf active request par.
	 */
	public static function shell_markup() {
		if ( ! self::$active ) {
			return;
		}
		?>
		<div id="te-root" hidden>
			<aside id="te-drawer" aria-label="<?php esc_attr_e( 'ایڈٹ موڈ پینل', 'tameero-edit-mode' ); ?>">
				<div id="te-sheet-handle" aria-hidden="true"></div>
				<header id="te-panel-head">
					<button type="button" id="te-panel-back" hidden>&larr;</button>
					<h2 id="te-panel-title"></h2>
					<button type="button" id="te-panel-close" aria-label="<?php esc_attr_e( 'بند کریں', 'tameero-edit-mode' ); ?>">&times;</button>
				</header>
				<div id="te-lockbar" hidden></div>
				<div id="te-draftbar" hidden></div>
				<div id="te-panel-body"></div>
				<footer id="te-panel-foot">
					<p id="te-panel-note" hidden></p>
					<div class="te-foot-row">
						<button type="button" id="te-reset" class="te-btn te-btn-quiet"><?php esc_html_e( 'تبدیلیاں واپس لیں', 'tameero-edit-mode' ); ?></button>
						<button type="button" id="te-save" class="te-btn te-btn-primary" disabled><?php esc_html_e( 'محفوظ کریں', 'tameero-edit-mode' ); ?></button>
					</div>
				</footer>
			</aside>

			<div id="te-modal" hidden>
				<div class="te-modal-card" role="dialog" aria-modal="true">
					<div class="te-modal-icon" aria-hidden="true">&#9888;</div>
					<h3><?php esc_html_e( 'تبدیلیاں محفوظ نہیں ہوئیں!', 'tameero-edit-mode' ); ?></h3>
					<p><?php esc_html_e( 'کیا آپ محفوظ کیے بغیر باہر نکلنا چاہتے ہیں؟', 'tameero-edit-mode' ); ?></p>
					<button type="button" id="te-modal-save" class="te-btn te-btn-primary"><?php esc_html_e( 'محفوظ کریں اور نکلیں', 'tameero-edit-mode' ); ?></button>
					<button type="button" id="te-modal-leave" class="te-btn te-btn-quiet"><?php esc_html_e( 'محفوظ کیے بغیر نکلیں', 'tameero-edit-mode' ); ?></button>
					<button type="button" id="te-modal-cancel" class="te-btn te-btn-link"><?php esc_html_e( 'منسوخ کریں', 'tameero-edit-mode' ); ?></button>
				</div>
			</div>

			<div id="te-toast" role="status" hidden></div>
		</div>
		<?php
	}
}
