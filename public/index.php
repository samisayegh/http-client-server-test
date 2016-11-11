<?php
require __DIR__ . '/../vendor/autoload.php';

use \pillr\library\http\Response as HttpResponse;

# TIP: Use the $_SERVER Sugerglobal to get all the data your need from the Client's HTTP Request.

# TIP: HTTP headers are printed natively in PHP by invoking header().
#      Ex. header('Content-Type', 'text/html');

// DEFINITIONS
// save current UTC date
$now = date('D, d M Y H:i:s T');

// define body
$body =
"{
	'@id': '{$_SERVER['REQUEST_URI']}',
	'to': 'Pillr',
	'subject': 'Hello Pillr',
	'message': 'Here is my submission.',
	'from': 'Sami Sayegh',
	'timeSent': '$now'
}";

// define headers
$headers = array(
	'Date' => $now,
	'Server' => $_SERVER['SERVER_SOFTWARE'],
	'Last-Modified' => $now,
	'Content-Length' => strlen($body),
	'Content-Type' => 'application/json'
	);

// INIT RESPONSE OBJ & SET HEADERS
// create response object
$res = new HttpResponse('1.1','200','OK', $headers, $body);

// set headers except Content-Length since printing headers takes from limit
foreach ($res->getHeaders() as $key => $value) {
	if($key != 'Content-Length'){
		header("{$key}: {$value}");
	};
};

// PRINT TO CLIENT
// print status line
echo "{$_SERVER['SERVER_PROTOCOL']} {$res->getStatusCode()} {$res->getReasonPhrase()}\n";
// print headers
foreach ($res->getHeaders() as $key => $value) {
	echo "{$key}: {$value}\n";
};
// print body
echo $res->getBody();