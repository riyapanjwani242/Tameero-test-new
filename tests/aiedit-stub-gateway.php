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
