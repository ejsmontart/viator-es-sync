<?php

/**
 * Entry point for synchronising Viator product catalogue into an Elastic Search instance.
 * 
 * Script can be executed by a cron or any other means. You can safely run it every 5 minutes.
 * 
 * API documentation
 * https://docs.viator.com/partner-api/technical/
 */

ini_set('memory_limit', '2000M');
require __DIR__."/../vendor/autoload.php";

use Elasticsearch\ClientBuilder;
use Analog\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleRetry\GuzzleRetryMiddleware;

// ===========================================================================
// Load configuration from ENV variables. We don't touch env() past this point
// ===========================================================================
$config = new Viator\Client\Configuration();
$config->setDebug(true)
        ->setDebugFile('./debug.log')
        ->setApiKey((string)getenv('VIATOR_API_KEY'))
        ->setHostPrefix((string)getenv('VIATOR_API_HOST_PREFIX'))
        ->setElasticSearchUrl((string)getenv('VIATOR_ES_URL'))
        ->setlocales(explode(',', (string)getenv('VIATOR_LOCALES')));

// ===========================================================================
// Assemble all the key objects
// ===========================================================================
$esClient = ClientBuilder::create()
        ->setHosts([$config->getElasticSearchUrl()])
        ->build();
$esStorage = new Viator\Client\Storage\SimpleElasticSearchStorage($esClient);

Analog::handler(Analog\Handler\Stderr::init());
$logger = new Logger();

// hardocding these as probably not worth externalizing them
$stack = HandlerStack::create();
$stack->push(GuzzleRetryMiddleware::factory());
$httpClient = new Client([
    'handler' => $stack,
    'max_retry_attempts' => 4,
    'retry_on_timeout' => true,
    'retry_enabled' => true,
    'retry_on_status' => [429, 500, 503]]);

$client = new Viator\Client\ViatorApiClient($httpClient, $config);
$synchronizer = new \Viator\Synchroniser\SimpleViatorSynchronizer($logger, $client, $esStorage);

// ===========================================================================
// Execute the synchroniser
// ===========================================================================

// synchronise locale-agnostic data
$synchronizer->synchroniseExchangeRates();
$synchronizer->synchroniseTags();
$synchronizer->synchroniseAvailabilitySchedules();

// synchronise locale specific data
foreach ($config->getLocales() as $locale) {
    $synchronizer->synchroniseLocalisedReferenceObjects($locale);
    $synchronizer->synchroniseProducts($locale);
    $synchronizer->synchronizeLocations($locale);
}   

$logger->debug('Synchroniser run completed.');