<?php
/**
 * TE_AI — Edit Mode ka AI rasta (v2.20, plugin 1.2.0).
 *
 * AI orchestration TAMEERO panel mein rehti hai; ye class sirf
 * teen cheezein deti hai, tamam maujooda allowlist/validation par:
 *  1. ai-preview — tajweez shuda values ko BINA save kiye asal
 *     homepage render karke dikhana (theme_mod/option filters se;
 *     request khatam = asar khatam, koi likhai nahi hoti).
 *  2. ai-apply — validated values ko maujooda atomic rasta
 *     (snapshot + TE_REST::apply_values) se lagana AUR history
 *     mein transaction record karna (before/after ke sath).
 *  3. ai-history / ai-undo — "AI ne kya kiya" ki list aur
 *     transaction-aware undo (sirf usi transaction ki values
 *     wapas; agar kisi value ko baad mein koi aur badal chuka
 *     ho to undo ruk jata hai, zabardasti nahi hoti).
 *
 * Hadd: aik transaction mein zyada se zyada 8 changes.
 * Global Styles (global_style storage) wale controls preview
 * mein skip hote hain (un ka render is raste se nahi hota);
 * apply un par bhi maujooda apply_values se hi hota hai.
 *
 * @package TameeroEditMode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TE_AI {

	const OPTION_HISTORY = 'tameero_ai_history';
	const MAX_CHANGES    = 8;
	const MAX_HISTORY    = 50;

	public static function register_routes() {
		register_rest_route(
			'tameero/v1',
			'/ai-preview',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'preview' ],
				'permission_callback' => 'tameero_edit_available',
			]
		);
		register_rest_route(
			'tameero/v1',
			'/ai-apply',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'apply' ],
				'permission_callback' => 'tameero_edit_available',
			]
		);
		register_rest_route(
			'tameero/v1',
			'/ai-history',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'history' ],
				'permission_callback' => 'tameero_edit_available',
			]
		);
		register_rest_route(
			'tameero/v1',
			'/ai-undo',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'undo' ],
				'permission_callback' => 'tameero_edit_available',
			]
		);
	}

	/**
	 * Bheji gayi values ko maujooda validation se saaf karo.
	 * save_settings wala hi usool: declared nahi = reject,
	 * aik bhi ghalat = sab reject.
	 *
	 * @param mixed $values Raw values (key => raw).
	 * @return array|WP_Error [clean, map] ya error.
	 */
	private static function validate_all( $values ) {
		if ( ! is_array( $values ) || empty( $values ) ) {
			return new WP_Error( 'te_empty', 'Koi tabdeeli nahi bheji gayi.', [ 'status' => 400 ] );
		}
		if ( count( $values ) > self::MAX_CHANGES ) {
			return new WP_Error( 'te_too_many', 'Aik dafa mein zyada se zyada ' . self::MAX_CHANGES . ' tabdeeliyan ho sakti hain.', [ 'status' => 400 ] );
		}
		$map    = TE_Config::controls_map();
		$clean  = [];
		$errors = [];
		foreach ( $values as $key => $raw ) {
			if ( ! isset( $map[ $key ] ) ) {
				$errors[ $key ] = 'Ye setting Edit Mode mein declared nahi hai.';
				continue;
			}
			$result = TE_REST::validate_value( $map[ $key ], $raw );
			if ( is_wp_error( $result ) ) {
				$errors[ $key ] = $result->get_error_message();
			} else {
				$clean[ $key ] = $result;
			}
		}
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'te_validation', 'Kuch settings durust nahi hain — kuch bhi lagu nahi hua.', [ 'status' => 400, 'fields' => $errors ] );
		}
		return [ $clean, $map ];
	}

	/**
	 * Preview: values ko filters se aarzi tor par laga kar asal
	 * homepage render karo. Koi save NAHI hota.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function preview( $request ) {
		$checked = self::validate_all( $request->get_param( 'values' ) );
		if ( is_wp_error( $checked ) ) {
			return $checked;
		}
		list( $clean, $map ) = $checked;

		$filters = [];
		$skipped = [];
		foreach ( $clean as $key => $value ) {
			$control = $map[ $key ];
			$storage = $control['storage'] ?? 'theme_mod';
			if ( 'theme_mod' === $storage ) {
				$cb = static function () use ( $value ) {
					return $value;
				};
				add_filter( 'theme_mod_' . $control['theme_mod'], $cb );
				$filters[] = [ 'theme_mod_' . $control['theme_mod'], $cb ];
			} elseif ( 'option' === $storage ) {
				$cb = static function () use ( $value ) {
					return $value;
				};
				add_filter( 'pre_option_' . $control['option'], $cb );
				$filters[] = [ 'pre_option_' . $control['option'], $cb ];
			} else {
				$skipped[] = $key; // global_style: is raste se preview nahi hota.
			}
		}

		$html = TE_REST::render_homepage_html();

		foreach ( $filters as $f ) {
			remove_filter( $f[0], $f[1] );
		}
		if ( is_wp_error( $html ) ) {
			return $html;
		}

		// Assets (CSS/JS/images) site ke asal URL se load hon —
		// panel is HTML ko iframe mein dikhata hai.
		$base = '<base href="' . esc_url( home_url( '/' ) ) . '">';
		if ( false !== strpos( $html, '<head>' ) ) {
			$html = preg_replace( '/<head>/', '<head>' . $base, $html, 1 );
		}

		return rest_ensure_response(
			[
				'ok'      => true,
				'html'    => $html,
				'skipped' => $skipped,
			]
		);
	}

	/**
	 * Apply: snapshot + atomic apply + history transaction.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function apply( $request ) {
		$checked = self::validate_all( $request->get_param( 'values' ) );
		if ( is_wp_error( $checked ) ) {
			return $checked;
		}
		list( $clean, $map ) = $checked;

		$prompt = (string) $request->get_param( 'prompt' );
		$prompt = mb_substr( trim( $prompt ), 0, 500 );

		$current = TE_Config::current_values();
		$changes = [];
		foreach ( $clean as $key => $value ) {
			$changes[] = [
				'key'    => $key,
				'label'  => (string) ( $map[ $key ]['label'] ?? $key ),
				'before' => $current[ $key ] ?? null,
				'after'  => $value,
			];
		}

		TE_Safety::snapshot_before_save();
		$result = TE_REST::apply_values( $clean, $map );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$tx = 'tx_' . gmdate( 'YmdHis' ) . '_' . wp_generate_password( 6, false );
		self::history_push(
			[
				'id'      => $tx,
				'at'      => gmdate( 'c' ),
				'prompt'  => $prompt,
				'changes' => $changes,
				'status'  => 'applied',
			]
		);

		return rest_ensure_response( [ 'ok' => true, 'tx' => $tx, 'saved' => TE_Config::current_values() ] );
	}

	/**
	 * History ki aakhri entries (nayi pehle).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function history( $request ) {
		$items = array_slice( self::history_all(), 0, 20 );
		return rest_ensure_response( [ 'ok' => true, 'items' => $items ] );
	}

	/**
	 * Transaction-aware undo: sirf is transaction ki values
	 * wapas karo, aur sirf tab jab unhein baad mein kisi aur
	 * ne NA badla ho. Masla ho to kuch nahi badalta.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function undo( $request ) {
		$tx_id = (string) $request->get_param( 'tx' );
		$items = self::history_all();
		$found = null;
		foreach ( $items as $i => $entry ) {
			if ( ( $entry['id'] ?? '' ) === $tx_id ) {
				$found = $i;
				break;
			}
		}
		if ( null === $found ) {
			return new WP_Error( 'te_tx', 'Ye transaction history mein nahi mili.', [ 'status' => 404 ] );
		}
		$entry = $items[ $found ];
		if ( ( $entry['status'] ?? '' ) !== 'applied' ) {
			return new WP_Error( 'te_tx_state', 'Ye tabdeeli pehle hi wapas ho chuki hai.', [ 'status' => 400 ] );
		}

		$map     = TE_Config::controls_map();
		$current = TE_Config::current_values();
		$back    = [];
		$blocked = [];
		foreach ( (array) $entry['changes'] as $change ) {
			$key = $change['key'];
			if ( ! isset( $map[ $key ] ) ) {
				$blocked[] = $key;
				continue;
			}
			$now = $current[ $key ] ?? null;
			if ( $now !== $change['after'] && (string) $now !== (string) $change['after'] ) {
				$blocked[] = $key; // Baad mein koi aur tabdeeli a chuki hai.
				continue;
			}
			$back[ $key ] = $change['before'];
		}
		if ( ! empty( $blocked ) ) {
			return new WP_Error(
				'te_undo_blocked',
				'Kuch values is tabdeeli ke baad badal chuki hain — undo ruk gaya, kuch wapas nahi hua.',
				[ 'status' => 409, 'fields' => $blocked ]
			);
		}

		TE_Safety::snapshot_before_save();
		$result = TE_REST::apply_values( $back, $map );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$items[ $found ]['status']   = 'undone';
		$items[ $found ]['undone_at'] = gmdate( 'c' );
		update_option( self::OPTION_HISTORY, $items, false );

		return rest_ensure_response( [ 'ok' => true, 'tx' => $tx_id, 'saved' => TE_Config::current_values() ] );
	}

	/** @return array History ki tamam entries (nayi pehle). */
	private static function history_all() {
		$items = get_option( self::OPTION_HISTORY, [] );
		return is_array( $items ) ? $items : [];
	}

	/** @param array $entry Nayi entry sab se upar jama karo. */
	private static function history_push( $entry ) {
		$items   = self::history_all();
		array_unshift( $items, $entry );
		$items = array_slice( $items, 0, self::MAX_HISTORY );
		update_option( self::OPTION_HISTORY, $items, false );
	}
}
