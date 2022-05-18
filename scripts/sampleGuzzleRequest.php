<?php

/*
 * This script shows how you can call Viator API using raw HTTP client like Guzzle
 * This is just to demonstrate where key goes, how request is structured etc
 * This is in no way production-ready code.
 */

ini_set('memory_limit', '2000M');
require __DIR__ . "/../vendor/autoload.php";

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;

if (empty((string) getenv('VIATOR_API_KEY'))) {
    echo "you need to set VIATOR_API_KEY env variable";
    exit(1);
}

// get instance of http client
$stack = HandlerStack::create();
$httpClient = new Client([
    'handler' => $stack,
    'max_retry_attempts' => 4,
    'retry_on_timeout' => true,
    'retry_enabled' => true,
    'retry_on_status' => [500, 503]]);

// prepare headers
$headers = array(
    'exp-api-key' => (string) getenv('VIATOR_API_KEY'),
    'Accept' => 'application/json;version=2.0',
    'Content-Type' => 'application/json',
    'User-Agent' => 'PAPI Open Client/PHP',
    'Accept-Language' => 'en',
);

// define request specific client options
$options = [];
$options[RequestOptions::ALLOW_REDIRECTS] = array('max' => 5);
$options[RequestOptions::TIMEOUT] = 90;
$options[RequestOptions::READ_TIMEOUT] = 90;
$options[RequestOptions::CONNECT_TIMEOUT] = 90;
$options[RequestOptions::DEBUG] = \fopen('./debug.sampleGuzzle.log', 'a');

// Prepare body of the POST request 
// https://docs.viator.com/partner-api/technical/#operation/locationsBulk
$data = array(
    "locations" => array(
        'LOC-6eKJ+or5y8o99Qw0C8xWyAcu/7vFeEq6qgrQqW+llLE=',
        'LOC-6eKJ+or5y8o99Qw0C8xWyIi8k2ebOcTMSWaA+jC2+d4=',
    )
);
$httpBody = \GuzzleHttp\json_encode($data);

// prepare the request
$request = new Request(
        'POST',
        'https://api.sandbox.viator.com/partner/locations/bulk',
        $headers,
        $httpBody
);
// Send request - WARNING - you should have exception handling code here, check status etc
$response = $httpClient->send($request, $options);
$responseBody = (string) $response->getBody();

print_r(\json_decode((string) $response->getBody(), true));
