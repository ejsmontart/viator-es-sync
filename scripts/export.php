<?php

/**
 *  WARNING proof of concept messy play with exporting content to other sources
 * 
 *  idea is to collect products which have availability merge their referenced objects and spit out one big json per product containing everything you may need.
 */
ini_set('memory_limit', '2000M');
require __DIR__ . "/../vendor/autoload.php";

use Elasticsearch\ClientBuilder;
use Analog\Logger;

// ===========================================================================
// Load configuration from ENV variables. We don't touch env() past this point
// ===========================================================================
$config = new Viator\Client\Configuration();
$config->setDebug(true)
        ->setDebugFile('./debug.log')
        ->setApiKey((string) getenv('VIATOR_API_KEY'))
        ->setHostPrefix((string) getenv('VIATOR_API_HOST_PREFIX'))
        ->setElasticSearchUrl((string) getenv('VIATOR_ES_URL'))
        ->setlocales(explode(',', (string) getenv('VIATOR_LOCALES')));

$locale = 'en';

// ===========================================================================
// Assemble all the key objects
// ===========================================================================
$esClient = ClientBuilder::create()
        ->setHosts([$config->getElasticSearchUrl()])
        ->build();
$esStorage = new Viator\Client\Storage\SimpleElasticSearchStorage($esClient);

Analog::handler(Analog\Handler\Stderr::init());
$logger = new Logger();

$productAttractionExtractor = new \Viator\Client\Helpers\ProductAttractionExtractor();
$productLocationExtractor = new \Viator\Client\Helpers\ProductLocationExtractor();

// ===========================================================================
// Fetch reference objects
// ===========================================================================
// dictionaries - load all into memory for quick lookups later on
$tags = $esStorage->getAllAtOnce('viator_raw_tags');
$attractions = $esStorage->getAllAtOnce('viator_raw_attractions_' . $locale);
$bookingQuestions = $esStorage->getAllAtOnce('viator_raw_booking_questions_' . $locale);
$destinations = $esStorage->getAllAtOnce('viator_raw_destinations_' . $locale);
$exchangeRates = $esStorage->getAllAtOnce('viator_raw_exchange_rates');

// ===========================================================================
// iterates over all active prodcuts (in pages of 500 procuts at a time)
// ===========================================================================
$iterator = $esStorage->getActiveProductIterator($locale, 50);
foreach ($iterator as $productsPage) {
    $productCodes = array();
    foreach ($productsPage['hits']['hits'] as $hit) {
        $productCodes [$hit['_id']] = $hit['_id'];
    }

    // fetch product and schedules for the current page of product codes
    $products = $esStorage->bulkGet('viator_raw_products_' . $locale, $productCodes);
    $schedules = $esStorage->bulkGet('viator_raw_schedules', $productCodes);

    // build a combined view of each product (include all data into one object)
    $combinedData = array();
    foreach ($products as $productCode => $product) {
        $combinedData[$productCode] = array(
            'product' => $product,
            'schedules' => array(),
            'tags' => array(),
            'bookingQuestions' => array(),
            'attractions' => array(),
            'destinations' => array(),
            'locations' => array(),
        );

        // get the schedule
        if (!empty($schedules[$productCode]['bookableItems'])) {
            $combinedData[$productCode]['schedules'] = $schedules[$productCode];
        } else {
            $logger->warning("Schedule not found for $productCode, excluding the product!");
            unset($combinedData[$productCode]);
            continue;
        }

        // get booking questions
        if (!empty($product['bookingQuestions'])) {
            foreach ($product['bookingQuestions'] as $question) {
                if (!empty($bookingQuestions[$question])) {
                    $combinedData[$productCode]['bookingQuestions'][$question] = $bookingQuestions[$question];
                } else {
                    $logger->debug("Unknown booking question $question in $productCode, ignoring it");
                }
            }
        }

        // get booking questions
        if (!empty($product['tags'])) {
            foreach ($product['tags'] as $tagId) {
                if (!empty($tags[$tagId])) {
                    $combinedData[$productCode]['tags'][$tagId] = $tags[$tagId];
                } else {
                    $logger->debug("Unknown tag $tagId in $productCode, ignoring it");
                }
            }
        }

        // get attraction details
        $attractionIds = $productAttractionExtractor->extractAttractionIds($product);
        foreach ($attractionIds as $attractionId) {
            if (!empty($attractions[$attractionId])) {
                $combinedData[$productCode]['attractions'][$attractionId] = $attractions[$attractionId];
            } else {
                $logger->debug("Unknown attraction $attractionId in $productCode, ignoring it");
            }
        }

        // collect destination ids from attractions and product
        $destinationIds = array();
        foreach ($combinedData[$productCode]['attractions'] as $attraction) {
            if (!empty($attraction['destinationId'])) {
                $destinationIds[$attraction['destinationId']] = $attraction['destinationId'];
            }
            if (!empty($attraction['primaryDestinationId'])) {
                $destinationIds[$attraction['primaryDestinationId']] = $attraction['primaryDestinationId'];
            }
        }
        if (!empty($product['destinations'])) {
            foreach ($product['destinations'] as $dest) {
                if (!empty($dest['ref'])) {
                    $destinationIds[$dest['ref']] = $dest['ref'];
                }
            }
        }
        foreach ($destinationIds as $destinationId) {
            if (!empty($destinations[$destinationId])) {
                $combinedData[$productCode]['destinations'][$destinationId] = $destinations[$destinationId];
            } else {
                $logger->debug("Unknown destination $destinationId in $productCode, ignoring it");
            }
        }

        // get location details
        $locationRefs = $productLocationExtractor->extractLocationRefs($product);

        // fetch locations from DB
        $locationData = $esStorage->bulkGet('viator_raw_locations_' . $locale, $locationRefs);
        foreach ($locationRefs as $locationRef) {
            if (!empty($locationData[$locationRef])) {
                $combinedData[$productCode]['locations'][$locationRef] = $locationData[$locationRef];
            } else {
                //$logger->debug("Unknown location $locationRef in $productCode, ignoring it");
            }
        }
    }


    // TODO
    $writer = new Viator\Exporters\Legacy\Deals\ProductWriter($exchangeRates, $destinations, 'en', 'CAD');
    $writer->writeProduct(array_pop($combinedData));
}

