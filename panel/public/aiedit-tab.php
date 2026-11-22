<?php
/**
 * AI Edit tab (v2.20) — Project Details ka naya tab.
 * Frozen UI (editmode-audit/FROZEN.md) ke mutabiq marahil:
 *   start (numbered sections + reference tasveer + hidayat)
 *   -> tajweez list (checkboxes + gap lines)
 *   -> Preview (Pehle/Baad, lazmi)
 *   -> Apply -> History + Undo.
 * Tamam asal kaam lib/aiedit.php + plugin (TE_AI) ke zariye
 * hota hai; ye file sirf dikhati hai.
 */

function wdh_aiedit_dir() {
	$d = hub_repo() . '/panel/.ai-edit';
	if ( ! is_dir( $d ) ) { mkdir( $d, 0775, true ); }
	return $d;
}
function wdh_aiedit_prop_file( $slug ) { return wdh_aiedit_dir() . '/' . $slug . '-proposal.json'; }
function wdh_aiedit_preview_file( $slug ) { return wdh_aiedit_dir() . '/' . $slug . '-preview.html'; }
function wdh_aiedit_load_prop( $slug ) {
	$f = wdh_aiedit_prop_file( $slug );
	if ( ! file_exists( $f ) ) { return null; }
	$d = json_decode( (string) file_get_contents( $f ), true );
	return is_array( $d ) ? $d : null;
}
function wdh_aiedit_save_prop( $slug, $prop ) {
	file_put_contents( wdh_aiedit_prop_file( $slug ), json_encode( $prop, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), LOCK_EX );
}
function wdh_aiedit_clear_prop( $slug ) {
	@unlink( wdh_aiedit_prop_file( $slug ) );
	@unlink( wdh_aiedit_preview_file( $slug ) );
}

// v2.21 M5: AI Edit tab hat gaya — sirf file helpers bachte hain
// (proposal/preview file paths + clear), chat flow (aiedit_chat) inhe istemal karta hai.
