<?php
// Test stub: AI Edit proposal gateway (sirf tests ke liye).
// POST /v1/chat/completions par hamesha tay-shuda proposal JSON
// wapas karta hai — aik durust change, aik ghalat key (panel ko
// gap mein daalna chahiye), aik ghalat select value (chorni
// chahiye), aur aik gap note.
$uri = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
header( 'Content-Type: application/json' );
if ( $uri === '/v1/models' ) {
	echo json_encode( [ 'data' => [ [ 'id' => 'stub-vision-model' ] ] ] );
	return;
}
if ( $uri === '/v1/chat/completions' ) {
	// SAKHTI (v2.20.1): asal gateway ki tarah body validate karo — body
	// JSON object na ho (masalan double-encoded string) to 400. Aik dafa
	// naram stub ki wajah se double-encoding wala bug tests se nikal gaya
	// tha (field mein HTTP 400 aya). Body capture bhi rakho taake test
	// asal bheji gayi body ki jaanch kar sake.
	$rawBody = (string) file_get_contents( 'php://input' );
	$capture = getenv( 'WDH_STUB_CAPTURE' );
	if ( is_string( $capture ) && '' !== $capture ) { file_put_contents( $capture, $rawBody ); }
	$bd = json_decode( $rawBody, true );
	if ( ! is_array( $bd ) || ! isset( $bd['model'] ) || ! isset( $bd['messages'] ) || ! is_array( $bd['messages'] ) ) {
		http_response_code( 400 );
		echo json_encode( [ 'error' => [ 'message' => 'stub: body JSON object (model+messages) nahi hai', 'type' => 'invalid_request_error' ] ] );
		return;
	}
	$proposal = [
		'changes' => [
			[ 'key' => 'urdu_news_sec_video_heading', 'value' => 'bohat-bara', 'reason' => 'Heading bohat bari kar di' ],
			[ 'key' => 'urdu_news_sec_video_bg', 'value' => 'neela', 'reason' => 'Background halka neela' ],
			[ 'key' => 'urdu_news_sec_video_bg_ghalat', 'value' => 'neela', 'reason' => 'invented key' ],
			[ 'key' => 'urdu_news_sec_hero_heading', 'value' => 'chhota', 'reason' => 'allowed nahi hai' ],
			[ 'key' => 'urdu_news_primary_color', 'value' => '#006341', 'reason' => 'Bunyadi rang sabz' ],
		],
		'gaps'    => [ 'Hero ki tasveer badalna mumkin nahi — is ka control maujood nahi.' ],
		'summary' => 'Video section ki heading bohat bari, background neela, bunyadi rang sabz.',
	];
	echo json_encode(
		[ 'choices' => [ [ 'message' => [ 'role' => 'assistant', 'content' => json_encode( $proposal, JSON_UNESCAPED_UNICODE ) ] ] ] ]
	);
	return;
}
http_response_code( 404 );
echo json_encode( [ 'error' => 'stub: path nahi mila' ] );
