<?php
/**
 * TE_Safety — soft lock + rolling backup + settings export/import.
 *
 * Soft lock (brief: do admin aik sath hon to doosre ko wazeh paigham):
 *  - Transient `tameero_edit_lock`: { user_id, name, since }, 90 second expiry.
 *  - Mode ON honay par lock lene ki koshish; heartbeat (30s) taaza rakhta hai.
 *  - Lock kisi aur ke pas ho to doosra admin sirf DEKH sakta hai (save band)
 *    aur "کنٹرول حاصل کریں" se takeover kar sakta hai.
 *
 * Rolling backup:
 *  - Har save se PEHLE theme_mods ka snapshot option `tameero_edit_backups`
 *    mein jata hai — aakhri 3 mehfooz. Restore one-click.
 *
 * Export/Import:
 *  - GET  /settings-export → sirf declared mods ka JSON.
 *  - POST /settings-import { mods } → save-settings jaisi hi validation —
 *    atomic, ghalti par kuch nahi badalta.
 *
 * @package TameeroEditMode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TE_Safety {

	const LOCK_KEY    = 'tameero_edit_lock';
	const BACKUPS_KEY = 'tameero_edit_backups';

	public static function register_routes() {
		register_rest_route(
			'tameero/v1',
			'/lock',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ __CLASS__, 'lock_status' ],
					'permission_callback' => 'tameero_edit_available',
				],
				[
					'methods'             => 'POST',
					'callback'            => [ __CLASS__, 'lock_action' ],
					'permission_callback' => 'tameero_edit_available',
				],
			]
		);
		register_rest_route(
			'tameero/v1',
			'/backups',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'backups_list' ],
				'permission_callback' => 'tameero_edit_available',
			]
		);
		register_rest_route(
			'tameero/v1',
			'/backups/restore',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'backups_restore' ],
				'permission_callback' => 'tameero_edit_available',
			]
		);
		register_rest_route(
			'tameero/v1',
			'/settings-export',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'settings_export' ],
				'permission_callback' => 'tameero_edit_available',
			]
		);
		register_rest_route(
			'tameero/v1',
			'/settings-import',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'settings_import' ],
				'permission_callback' => 'tameero_edit_available',
			]
		);
	}

	/* ---------------- soft lock ---------------- */

	/**
	 * @return array|null Mojooda lock ya null.
	 */
	private static function current_lock() {
		$lock = get_transient( self::LOCK_KEY );
		return ( is_array( $lock ) && isset( $lock['user_id'] ) ) ? $lock : null;
	}

	/**
	 * @param array|null $lock Lock.
	 * @return array REST payload.
	 */
	private static function lock_payload( $lock ) {
		$me = get_current_user_id();
		if ( ! $lock ) {
			return [ 'ok' => true, 'state' => 'free', 'mine' => false, 'holder' => null ];
		}
		$mine = ( (int) $lock['user_id'] === (int) $me );
		return [
			'ok'     => true,
			'state'  => $mine ? 'mine' : 'held',
			'mine'   => $mine,
			'holder' => [
				'name'  => $lock['name'] ?? '',
				'since' => $lock['since'] ?? 0,
			],
		];
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function lock_status() {
		return rest_ensure_response( self::lock_payload( self::current_lock() ) );
	}

	/**
	 * op: acquire | heartbeat | release | takeover
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function lock_action( $request ) {
		$op   = (string) $request->get_param( 'op' );
		$me   = wp_get_current_user();
		$mine = [
			'user_id' => $me->ID,
			'name'    => $me->display_name,
			'since'   => time(),
		];
		$lock = self::current_lock();

		switch ( $op ) {
			case 'acquire':
				if ( $lock && (int) $lock['user_id'] !== (int) $me->ID ) {
					return rest_ensure_response( self::lock_payload( $lock ) );
				}
				$mine['since'] = $lock['since'] ?? time();
				set_transient( self::LOCK_KEY, $mine, 90 );
				return rest_ensure_response( self::lock_payload( $mine ) );

			case 'heartbeat':
				if ( $lock && (int) $lock['user_id'] === (int) $me->ID ) {
					set_transient( self::LOCK_KEY, $lock, 90 );
					return rest_ensure_response( self::lock_payload( $lock ) );
				}
				return rest_ensure_response( self::lock_payload( $lock ) );

			case 'takeover':
				set_transient( self::LOCK_KEY, $mine, 90 );
				return rest_ensure_response( self::lock_payload( $mine ) );

			case 'release':
				if ( $lock && (int) $lock['user_id'] === (int) $me->ID ) {
					delete_transient( self::LOCK_KEY );
				}
				return rest_ensure_response( self::lock_payload( null ) );

			default:
				return new WP_Error( 'te_lock_op', 'Lock operation ghalat hai.', [ 'status' => 400 ] );
		}
	}

	/* ---------------- rolling backup ---------------- */

	/**
	 * Save se pehle bulaya jata hai (TE_REST): theme_mods ka snapshot,
	 * aakhri 3 mehfooz rehte hain.
	 */
	public static function snapshot_before_save() {
		$mods = get_theme_mods();
		if ( ! is_array( $mods ) ) {
			$mods = [];
		}
		$backups   = get_option( self::BACKUPS_KEY, [] );
		if ( ! is_array( $backups ) ) {
			$backups = [];
		}
		$backups[] = [
			'time' => time(),
			'user' => get_current_user_id(),
			'mods' => $mods,
		];
		$backups = array_slice( $backups, -3 );
		update_option( self::BACKUPS_KEY, $backups, false );
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function backups_list() {
		$backups = get_option( self::BACKUPS_KEY, [] );
		$out     = [];
		if ( is_array( $backups ) ) {
			foreach ( $backups as $i => $b ) {
				$out[] = [
					'index' => $i,
					'time'  => $b['time'] ?? 0,
					'count' => isset( $b['mods'] ) && is_array( $b['mods'] ) ? count( $b['mods'] ) : 0,
				];
			}
		}
		return rest_ensure_response( [ 'ok' => true, 'backups' => array_reverse( $out ) ] );
	}

	/**
	 * One-click revert: chuna hua snapshot dobara lagu karo. Declared
	 * (Edit Mode) mods hi restore hote hain — baaki mods (masalan WP ke
	 * apne) ko haath nahi lagta.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function backups_restore( $request ) {
		$index   = (int) $request->get_param( 'index' );
		$backups = get_option( self::BACKUPS_KEY, [] );
		if ( ! is_array( $backups ) || ! isset( $backups[ $index ]['mods'] ) || ! is_array( $backups[ $index ]['mods'] ) ) {
			return new WP_Error( 'te_backup', 'Ye backup mojood nahi hai.', [ 'status' => 404 ] );
		}
		$snapshot = $backups[ $index ]['mods'];
		$map      = TE_Config::controls_map();
		if ( empty( $map ) ) {
			return new WP_Error( 'te_config', 'Edit config nahi mili.', [ 'status' => 500 ] );
		}
		foreach ( array_keys( $map ) as $mod ) {
			if ( array_key_exists( $mod, $snapshot ) ) {
				set_theme_mod( $mod, $snapshot[ $mod ] );
			} else {
				remove_theme_mod( $mod );
			}
		}
		return rest_ensure_response( [ 'ok' => true ] );
	}

	/* ---------------- export / import ---------------- */

	/**
	 * @return WP_REST_Response
	 */
	public static function settings_export() {
		$map = TE_Config::controls_map();
		if ( empty( $map ) ) {
			return new WP_Error( 'te_config', 'Edit config nahi mili.', [ 'status' => 500 ] );
		}
		$mods = [];
		foreach ( array_keys( $map ) as $mod ) {
			$value = get_theme_mod( $mod, null );
			if ( null !== $value ) {
				$mods[ $mod ] = $value;
			}
		}
		return rest_ensure_response(
			[
				'ok'    => true,
				'data'  => [
					'format' => 'tameero-edit-settings',
					'v'      => 1,
					'theme'  => get_stylesheet(),
					'time'   => time(),
					'mods'   => $mods,
				],
			]
		);
	}

	/**
	 * Import — validation save-settings jaisi hi (TE_REST::apply_values).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function settings_import( $request ) {
		$mods = $request->get_param( 'mods' );
		if ( ! is_array( $mods ) || empty( $mods ) ) {
			return new WP_Error( 'te_import', 'File mein settings nahi mili.', [ 'status' => 400 ] );
		}
		$map = TE_Config::controls_map();
		if ( empty( $map ) ) {
			return new WP_Error( 'te_config', 'Edit config nahi mili.', [ 'status' => 500 ] );
		}
		// Sirf declared mods, validation ke sath — unknown keys ignore.
		$clean = [];
		foreach ( $mods as $mod => $value ) {
			if ( ! isset( $map[ $mod ] ) ) {
				continue;
			}
			$valid = TE_REST::validate_value( $map[ $mod ], $value );
			if ( is_wp_error( $valid ) ) {
				return new WP_Error( 'te_import_validation', $valid->get_error_message(), [ 'status' => 400 ] );
			}
			$clean[ $mod ] = $valid;
		}
		if ( empty( $clean ) ) {
			return new WP_Error( 'te_import', 'File ki koi setting is theme par lagu nahi hoti.', [ 'status' => 400 ] );
		}
		self::snapshot_before_save();
		foreach ( $clean as $mod => $value ) {
			set_theme_mod( $mod, $value );
		}
		return rest_ensure_response( [ 'ok' => true, 'applied' => count( $clean ) ] );
	}
}
