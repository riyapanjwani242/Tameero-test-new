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

/** AI Edit tab ka poora markup. */
function wdh_aiedit_tab( $dslug, $csrf, $S, $site ) {
	$h = static function ( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); };
	$tabUrl = '/?view=site&site=' . urlencode( $dslug ) . '&tab=aiedit';
	$state = hub_aiedit_call( $dslug, 'state' );
	if ( ! empty( $state['error'] ) || empty( $state['blocks'] ) ) {
		echo '<div class="card"><p>' . $h( $S['aiedit_unavailable'] ) . '</p>';
		if ( ! empty( $state['error'] ) ) { echo '<p class="mut">' . $h( $state['error'] ) . '</p>'; }
		echo '</div>';
		return;
	}
	$prop = wdh_aiedit_load_prop( $dslug );
	$showPreview = isset( $_GET['aipreview'] ) && file_exists( wdh_aiedit_preview_file( $dslug ) ) && $prop && ! empty( $prop['selected'] );

	/* ---------- Proposal / Preview / Apply ---------- */
	if ( $prop && ! empty( $prop['changes'] ) ) {
		echo '<div class="card"><h2>' . $h( $S['aiedit_proposal'] ) . '</h2>';
		echo '<p class="mut">' . $h( $S['aiedit_you_said'] ) . '</p><p>"' . $h( $prop['instruction'] ?? '' ) . '"</p>';
		foreach ( (array) ( $prop['gaps'] ?? [] ) as $gap ) {
			echo '<div class="aigap">⚠ ' . $h( $gap ) . '</div>';
		}
		if ( $showPreview ) {
			$siteUrl = (string) ( $site['url'] ?? '' );
			echo '<div class="aiba">';
			echo '<div class="aiba-col"><div class="aiba-lbl">' . $h( $S['aiedit_before'] ) . '</div>';
			if ( $siteUrl !== '' && ! empty( $site['running'] ) ) {
				echo '<iframe class="aiframe" src="' . $h( $siteUrl ) . '" loading="lazy"></iframe>';
			} else {
				echo '<div class="aiframe ainote">Site band hai — Pehle wala preview site chalne par dikhe ga. Site start karke page refresh karein.</div>';
			}
			echo '</div>';
			echo '<div class="aiba-col"><div class="aiba-lbl">' . $h( $S['aiedit_after'] ) . ' <span class="pill">' . $h( $S['aiedit_not_saved'] ) . '</span></div>';
			echo '<iframe class="aiframe" src="/?aiedit_ph=' . urlencode( $dslug ) . '" loading="lazy"></iframe>';
			echo '</div></div>';
			echo '<form method="post" class="airow">';
			echo '<input type="hidden" name="csrf" value="' . $h( $csrf ) . '"><input type="hidden" name="action" value="aiedit_apply"><input type="hidden" name="site" value="' . $h( $dslug ) . '">';
			echo '<button class="btn primary" type="submit">' . $h( $S['aiedit_apply'] ) . '</button>';
			echo '<a class="btn" href="' . $h( $tabUrl ) . '">' . $h( $S['aiedit_back'] ) . '</a>';
			echo '</form><p class="mut">' . $h( $S['aiedit_backup_note'] ) . '</p>';
		} else {
			echo '<p style="margin-top:10px"><b>' . $h( $S['aiedit_changes'] ) . '</b></p>';
			echo '<form method="post">';
			echo '<input type="hidden" name="csrf" value="' . $h( $csrf ) . '"><input type="hidden" name="site" value="' . $h( $dslug ) . '">';
			foreach ( $prop['changes'] as $i => $c ) {
				$sw = '';
				if ( strpos( (string) $c['new_label'], '#' ) === 0 ) {
					$sw = ' <span class="aiswatch" style="background:' . $h( $c['new_label'] ) . '"></span>';
				}
				echo '<label class="aichg"><input type="checkbox" name="sel[]" value="' . (int) $i . '" checked> <span><b>' . $h( $c['section'] ) . '</b> — ' . $h( $c['label'] ) . ': ' . $h( $c['old_label'] ) . ' → <b>' . $h( $c['new_label'] ) . '</b>' . $sw;
				if ( ! empty( $c['reason'] ) ) { echo '<br><span class="mut">' . $h( $c['reason'] ) . '</span>'; }
				echo '</span></label>';
			}
			echo '<div class="airow">';
			echo '<button class="btn primary" type="submit" name="action" value="aiedit_preview">' . $h( $S['aiedit_preview'] ) . '</button>';
			echo '<button class="btn" type="submit" name="action" value="aiedit_discard" formnovalidate>' . $h( $S['aiedit_redo'] ) . '</button>';
			echo '<button class="btn danger" type="submit" name="action" value="aiedit_discard" formnovalidate>' . $h( $S['aiedit_cancel'] ) . '</button>';
			echo '</div><p class="mut">' . $h( $S['aiedit_preview_note'] ) . '</p>';
			echo '</form>';
		}
		echo '</div>';
	}

	/* ---------- Start form ---------- */
	if ( ! $prop ) {
		echo '<div class="card"><h2>' . $h( $S['aiedit_sections'] ) . '</h2><div class="aisecs">';
		$n = 0;
		foreach ( (array) $state['blocks'] as $block ) {
			$n++;
			echo '<div class="aisec"><span class="aibadge">' . $n . '</span> <b>' . $h( $block['title'] ?? '' ) . '</b> <span class="mut">(' . count( (array) ( $block['controls'] ?? [] ) ) . ' controls)</span></div>';
		}
		echo '</div></div>';
		echo '<form method="post" enctype="multipart/form-data">';
		echo '<input type="hidden" name="csrf" value="' . $h( $csrf ) . '"><input type="hidden" name="action" value="aiedit_propose"><input type="hidden" name="site" value="' . $h( $dslug ) . '">';
		echo '<div class="card"><h2>' . $h( $S['aiedit_ref'] ) . '</h2>';
		echo '<input type="file" name="refimg" accept="image/*">';
		echo '<p class="mut">' . $h( $S['aiedit_ref_note'] ) . '</p></div>';
		echo '<div class="card"><h2>' . $h( $S['aiedit_what'] ) . '</h2>';
		echo '<textarea name="instruction" required rows="3" style="width:100%" placeholder="' . $h( $S['aiedit_ph'] ) . '"></textarea>';
		echo '<div class="airow"><button class="btn primary" type="submit">' . $h( $S['aiedit_propose'] ) . '</button>';
		echo '<span class="mut">' . $h( $S['aiedit_note'] ) . '</span></div></div>';
		echo '</form>';
	}

	/* ---------- History + Undo ---------- */
	$hist = hub_aiedit_call( $dslug, 'history' );
	echo '<div class="card"><h2>' . $h( $S['aiedit_history'] ) . '</h2>';
	if ( ! empty( $hist['error'] ) ) {
		echo '<p class="mut">' . $h( $hist['error'] ) . '</p>';
	} elseif ( empty( $hist['items'] ) ) {
		echo '<p class="mut">' . $h( $S['aiedit_none'] ) . '</p>';
	} else {
		foreach ( (array) $hist['items'] as $it ) {
			$undone = ( ( $it['status'] ?? '' ) === 'undone' );
			echo '<div class="aihist"><div class="aihist-top"><span class="mut">' . $h( $it['at'] ?? '' ) . '</span>';
			echo $undone ? '<span class="tag">' . $h( $S['aiedit_undone'] ) . '</span>' : '<span class="tag tagok">AI</span>';
			echo '</div>';
			if ( ! empty( $it['prompt'] ) ) { echo '<p><b>' . $h( $S['aiedit_you_said'] ) . '</b> "' . $h( $it['prompt'] ) . '"</p>'; }
			$parts = [];
			foreach ( (array) ( $it['changes'] ?? [] ) as $c ) {
				$parts[] = $h( $c['label'] ?? $c['key'] ) . ': ' . $h( is_bool( $c['before'] ?? null ) ? ( $c['before'] ? 'On' : 'Off' ) : (string) ( $c['before'] ?? '' ) ) . ' → ' . $h( is_bool( $c['after'] ?? null ) ? ( $c['after'] ? 'On' : 'Off' ) : (string) ( $c['after'] ?? '' ) );
			}
			echo '<p class="mut">' . implode( ' · ', $parts ) . '</p>';
			if ( ! $undone ) {
				echo '<form method="post"><input type="hidden" name="csrf" value="' . $h( $csrf ) . '"><input type="hidden" name="action" value="aiedit_undo"><input type="hidden" name="site" value="' . $h( $dslug ) . '"><input type="hidden" name="tx" value="' . $h( $it['id'] ?? '' ) . '">';
				echo '<button class="btn danger" type="submit">' . $h( $S['aiedit_undo'] ) . '</button></form>';
			}
			echo '</div>';
		}
	}
	echo '</div>';
}
