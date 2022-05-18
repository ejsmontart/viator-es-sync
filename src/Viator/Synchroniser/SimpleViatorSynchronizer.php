<?php

namespace Viator\Synchroniser;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Viator\Client\Helpers\ProductLocationExtractor;
use Viator\Client\Helpers\ProductFilteringAnnotator;
use Viator\Client\ViatorApiClient;
use Viator\Client\Storage\SimpleElasticSearchStorage;

/**
 * Class drives synchronizing product catalogue from Viator API into a data store.
 */
class SimpleViatorSynchronizer {

    /**
     * We do not want to synchronize some objects like tags all the time as they change rarely.
     * We skip these updates unless they are older than this setting.
     * 
     * @var int max age in seconds
     */
    private $updateTaxonomyAfterSeconds = 12 * 60 * 60;

    /**
     * how often do we want to pull exchange rates
     * 
     * @var int max age in seconds
     */
    private $updateCurrenciesAfterSeconds = 4 * 60 * 60;

    /**
     * Very basic interface for storing documents
     * 
     * @var \Viator\Client\Storage\SimpleElasticSearchStorage
     */
    private $storage;

    /**
     * @var int timestamp of when we have initiated sync run
     */
    private $startTimestamp;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var \Viator\Client\Storage\ViatorApiClient
     */
    private $client;

    /**
     * 
     * @var \Viator\Client\Helpers\ProductLocationExtractor
     */
    private $locationRefExtractor;

    /**
     * 
     * @var \Viator\Client\Helpers\ProductFilteringAnnotator
     */
    private $productFilteringAnnotator;

    /**
     * 
     * @param LoggerInterface $logger
     * @param ViatorApiClient $client
     * @param StorageInterface $storage
     */
    public function __construct(LoggerInterface $logger, ViatorApiClient $client, SimpleElasticSearchStorage $storage) {
        $this->startTimestamp = time();
        $this->logger = $logger;
        $this->client = $client;
        $this->storage = $storage;
        $this->locationRefExtractor = new ProductLocationExtractor();
        $this->productFilteringAnnotator = new ProductFilteringAnnotator();
    }

    public function synchroniseTags() {
        // fetch last sync time for this object type
        $LastSyncTime = (int) $this->storage->getByReference('viator_sync_metadata', 'last_update_time_tags')['value'];
        // skip sync if we have done it recently
        if ($LastSyncTime + $this->updateTaxonomyAfterSeconds < $this->startTimestamp) {
            // get data and upsert it into document store
            $this->logger->log(LogLevel::DEBUG, "Synchronizing tags ... ");
            $objects = $this->client->getTags();
            $this->storage->bulkUpsert('viator_raw_tags', $objects);
            // update time of most recent sync
            $this->storage->setByReference('viator_sync_metadata', 'last_update_time_tags', array('value' => $this->startTimestamp));
        } else {
            $this->logger->log(LogLevel::DEBUG, "Skipping tags.");
        }
    }

    public function synchroniseExchangeRates() {
        // fetch last sync time for this object type
        $LastSyncTime = (int) $this->storage->getByReference('viator_sync_metadata', 'last_update_time_exchange_rates')['value'];
        // skip sync if we have done it recently
        if ($LastSyncTime + $this->updateCurrenciesAfterSeconds < $this->startTimestamp) {
            // get data and upsert it into document store
            $this->logger->log(LogLevel::DEBUG, "Synchronizing exchange rates ... ");
            $objects = $this->client->getAllExchangeRates();
            $this->storage->bulkUpsert('viator_raw_exchange_rates', $objects);
            // update time of most recent sync
            $this->storage->setByReference('viator_sync_metadata', 'last_update_time_exchange_rates', array('value' => $this->startTimestamp));
        } else {
            $this->logger->log(LogLevel::DEBUG, "Skipping exchange rates.");
        }
    }

    public function synchroniseAvailabilitySchedules() {
        // schedules modified-since does not require locales either
        $totalSchedulesFetched = 0;
        $nextCursor = $this->storage->getByReference('viator_sync_metadata', 'next_cursor_schedules')['cursor'];
        $this->logger->log(LogLevel::DEBUG, "Synchronizing schedules ... ");
        do {
            if (empty($nextCursor)) {
                $result = $this->client->getAvailabilityModifiedSinceTime(10, '2020-01-01T00:00:01.737043Z');
            } else {
                $result = $this->client->getAvailabilityModifiedSinceCursor(500, $nextCursor);
            }
            $nextCursor = $result->getNextCursor();

            if (!empty($nextCursor)) {
                // nextCursor is empty which means we have ran out of products to synchronise, we will come back later with same cursor
                $this->storage->bulkUpsert('viator_raw_schedules', $result->getData());
                $this->storage->setByReference('viator_sync_metadata', 'next_cursor_schedules', array('cursor' => $nextCursor));
            }

            $schedulesFetched = count($result->getData());
            $totalSchedulesFetched += $schedulesFetched;
        } while ($schedulesFetched > 0 && !empty($nextCursor));
        $this->logger->log(LogLevel::DEBUG, "Synchronized $totalSchedulesFetched schedules.");
    }

    public function synchroniseLocalisedReferenceObjects($locale) {
        // some of the objects change rarely so we can synchronise them once or twice a day
        $LastSyncTime = (int) $this->storage->getByReference('viator_sync_metadata', 'last_update_time_referenced_objects_' . $locale)['value'];
        if ($LastSyncTime + $this->updateTaxonomyAfterSeconds < $this->startTimestamp) {

            $this->logger->log(LogLevel::DEBUG, "Synchronizing destinations ($locale) ... ");
            $objects = $this->client->getDestinations($locale);
            $this->storage->bulkUpsert('viator_raw_destinations_' . $locale, $objects);

            $this->logger->log(LogLevel::DEBUG, "Synchronizing booking questions ($locale) ... ");
            $objects = $this->client->getBookingQuestions($locale);
            $this->storage->bulkUpsert('viator_raw_booking_questions_' . $locale, $objects);

            $this->logger->log(LogLevel::DEBUG, "Synchronizing cancel reasons ($locale) ... ");
            $objects = $this->client->getCancelReasons($locale);
            $this->storage->bulkUpsert('viator_raw_cancel_reasons_' . $locale, $objects);

            $this->logger->log(LogLevel::DEBUG, "Synchronizing attractions ($locale) ... ");
            $objects = $this->client->getAllAttractions($locale);
            $this->storage->bulkUpsert('viator_raw_attractions_' . $locale, $objects);

            // update time of most recent sync
            $this->storage->setByReference('viator_sync_metadata', 'last_update_time_referenced_objects_' . $locale, array('value' => $this->startTimestamp));
        } else {
            $this->logger->log(LogLevel::DEBUG, "Skipping destinations, booking questions, ancel reasons, attractions ($locale)");
        }
    }

    public function synchroniseProducts($locale) {
        // products are synchronised using modified-since endpoint
        // when we make the very first call we use datetime, after that we only use cursor.
        $this->logger->log(LogLevel::DEBUG, "Synchronizing products ($locale) ... ");
        $totalProductsFetched = 0;
        $nextCursor = $this->storage->getByReference('viator_sync_metadata', 'next_cursor_products_' . $locale)['cursor'];
        do {
            if (empty($nextCursor)) {
                $result = $this->client->getProductsModifiedSinceTime($locale, 10, '2020-01-01T00:00:01.737043Z');
            } else {
                $result = $this->client->getProductsModifiedSinceCursor($locale, 200, $nextCursor);
            }
            $nextCursor = $result->getNextCursor();
            $products = $result->getData();

            $schedules = array();
            if (!empty($products)) {
                $schedules = $this->storage->bulkGet('viator_raw_schedules', array_keys($products));
            }

            // Reject products we do not want to sell by overriding their status to INACTIVE
            foreach ($products as $index => $product) {
                $products[$index] = $this->productFilteringAnnotator->processProduct($product, $schedules[$index]);
            }

            // Find all location references and add them to the list of discovered location references for sync later on
            $allLocations = array();
            foreach ($products as $index => $product) {
                if ($product['status'] == 'ACTIVE') {
                    $locations = $this->locationRefExtractor->extractLocationRefs($product);
                    $allLocations = array_merge($allLocations, $locations);
                }
            }

            if (!empty($allLocations)) {
                foreach ($allLocations as $index => $ignore) {
                    $allLocations[$index] = array(
                        'ref' => $index,
                        'creationTimestamp' => time(),
                    );
                }
                $this->storage->bulkCreate('viator_sync_location_refs', $allLocations);
                $this->logger->log(LogLevel::DEBUG, "... found " . count($allLocations) . " locations");
            }

            if (!empty($nextCursor)) {
                // nextCursor is empty when we run out of products to synchronise, we keep old cursor and come back later
                $this->storage->bulkUpsert('viator_raw_products_' . $locale, $products);
                $this->storage->setByReference('viator_sync_metadata', 'next_cursor_products_' . $locale, array('cursor' => $nextCursor));
            }

            $productsFetched = count($products);
            $totalProductsFetched += $productsFetched;
            $this->logger->log(LogLevel::DEBUG, "... found $productsFetched products");
        } while ($productsFetched > 0);
        $this->logger->log(LogLevel::DEBUG, "Synchronized $totalProductsFetched products ($locale).");
    }

    //
    public function synchronizeLocations($locale) {
        $this->logger->log(LogLevel::DEBUG, "Synchronizing locations ($locale) ... ");

        do {
            // find references to synchronise in this locale
            $references = $this->storage->findUnsyncedLocations($locale, 200);

            if (!empty($references)) {
                // fetch them and save the data
                $start = microtime(true);
                $objects = $this->client->getLocationsBulk($locale, array_keys($references));
                $this->logger->log(LogLevel::DEBUG, "Synchronizing locations ($locale) got " . count($objects) . " locations in " . ( microtime(true) - $start)) . " sec";
                $this->storage->bulkUpsert('viator_raw_locations_' . $locale, $objects);

                // update viator_sync_location_refs to mark which refs were fetched for this locale
                $updateTime = time();
                foreach ($references as $ref => $data) {
                    $references[$ref]['updated_' . $locale] = $updateTime;
                }

                $this->storage->bulkUpsert('viator_sync_location_refs', $references);
            }
        } while (!empty($references));
    }

}
