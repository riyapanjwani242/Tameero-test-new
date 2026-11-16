<?php
// Mock 9Router gateway — img tests ke liye (tests/img-gen-check.php).
// Bartao model naam se: quota* -> 429, auth* -> 401, badmagic* -> 200 text,
// safety* -> 400 safety wording, warna asal JPEG (binary ya b64_json).
$uri = $_SERVER['REQUEST_URI'] ?? '';
$JPEG_B64 = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAUDBAQEAwUEBAQFBQUGBwwIBwcHBw8LCwkMEQ8SEhEPERETFhwXExQaFRERGCEYGh0dHx8fExciJCIeJBweHx7/2wBDAQUFBQcGBw4ICA4eFBEUHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh7/wAARCABaAHgDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDzSiiivkT+iAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKAP//Z';
if ( strpos( $uri, '/models/image' ) !== false ) {
	header( 'Content-Type: application/json' );
	echo json_encode( [ 'data' => [ [ 'id' => 'mock/flash-image' ], [ 'id' => 'mock/pro-image' ] ] ] );
	return true;
}
if ( strpos( $uri, '/images/generations' ) !== false ) {
	$body = json_decode( (string) file_get_contents( 'php://input' ), true );
	$model = (string) ( $body['model'] ?? '' );
	if ( strpos( $model, 'quota' ) !== false ) { http_response_code( 429 ); echo '{"error":"rate limit"}'; return true; }
	if ( strpos( $model, 'auth' ) !== false ) { http_response_code( 401 ); echo '{"error":"invalid key"}'; return true; }
	if ( strpos( $model, 'safety' ) !== false ) { http_response_code( 400 ); echo '{"error":"safety policy blocked this prompt"}'; return true; }
	if ( strpos( $model, 'badmagic' ) !== false ) { http_response_code( 200 ); echo 'ye image nahi hai, saada text hai'; return true; }
	$jpeg = base64_decode( $JPEG_B64 );
	if ( strpos( $uri, 'response_format=binary' ) !== false ) {
		header( 'Content-Type: image/jpeg' );
		echo $jpeg;
	} else {
		header( 'Content-Type: application/json' );
		echo json_encode( [ 'created' => time(), 'data' => [ [ 'b64_json' => base64_encode( $jpeg ) ] ] ] );
	}
	return true;
}
http_response_code( 404 );
echo '{"error":"unknown path"}';
return true;
