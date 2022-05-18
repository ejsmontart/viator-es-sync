<?php

namespace Viator\Client;

use Viator\Client\GenericCursorPageResponseDataObject;

/**
 * Simple API client to demonstrating access to some of Viator endpoints
 * 
 * Responses are json_decode as arrays and restructured as maps indexed by object id
 * 
 * @link https://docs.viator.com/partner-api/technical/
 */
class ViatorApiClient extends AbstractApiClient {

    public function getTags() {
        $response = $this->sendGetRequest('/products/tags'); // no locale is needed

        $result = array();
        if (!empty($response['tags'])) {
            foreach ($response['tags'] as $entry) {
                $result[$entry['tagId']] = $entry;
            }
        }
        return $result;
    }

    /**
     * 
     * @return array of exchange rate objects indexed by source-target currency code
     */
    public function getAllExchangeRates() {
        $data = array(
            // currencies supported by product suppliers
            "sourceCurrencies" => [
                "AED", "AUD", "BRL", "CAD", "CHF", "CNY", "DKK", "EUR", "FJD", "GBP", "HHL",
                "HKD", "IDR", "INR", "ISK", "JPY", "KRW", "MXN", "MYR", "NOK", "NZD", "PLN",
                "RUB", "SEK", "SGD", "THB", "TRY", "TWD", "USD", "VND", "ZAR"
            ],
            // currencies you can use to pay Viator for products your customers order (you can yse other currencies on your side)
            "targetCurrencies" => [
                "AUD",
                "EUR",
                "USD",
                "GBP"
            ]
        );
        $response = $this->sendPostRequest('/exchange-rates', $data, $locale);

        $result = array();
        if (!empty($response['rates'])) {
            foreach ($response['rates'] as $entry) {
                $result[$entry['sourceCurrency'] . '-' . $entry['targetCurrency']] = $entry;
            }
        }
        return $result;
    }

    public function getDestinations($locale = 'en') {
        $response = $this->sendGetRequest('/v1/taxonomy/destinations', $locale);

        $result = array();
        if (!empty($response['data'])) {
            foreach ($response['data'] as $entry) {
                $result[$entry['destinationId']] = $entry;
            }
        }
        return $result;
    }

    public function getBookingQuestions($locale = 'en') {
        $response = $this->sendGetRequest('/products/booking-questions', $locale);

        $result = array();
        if (!empty($response['bookingQuestions'])) {
            foreach ($response['bookingQuestions'] as $entry) {
                $result[$entry['id']] = $entry;
            }
        }
        return $result;
    }

    public function getCancelReasons($locale = 'en') {
        $response = $this->sendGetRequest('/bookings/cancel-reasons', $locale);

        $result = array();
        if (!empty($response['reasons'])) {
            foreach ($response['reasons'] as $entry) {
                $result[$entry['cancellationReasonCode']] = $entry;
            }
        }
        return $result;
    }

    /**
     * 
     * @param type $locale
     * @param type $destionationId
     * @param type $limit
     * @param type $nonZeroOffset               offset starting from 1 
     * @param type $sortOrder
     * @return type
     */
    public function getAttractions($locale = 'en', $destionationId = 1, $limit = 100, $nonZeroOffset = 1, $sortOrder = 'SEO_PUBLISHED_DATE_A') {
        $offset = (int) $nonZeroOffset >= 1 ? (int) $nonZeroOffset : 1;
        $limit = ($limit >= 1 || $limit <= 500) ? (int) $limit : 100;

        $data = array(
            "destId" => $destionationId,
            "topX" => $offset . "-" . ($offset + $limit),
            "sortOrder" => $sortOrder,
        );
        $response = $this->sendPostRequest('/v1/taxonomy/attractions', $data, $locale);

        $result = array();
        if (!empty($response['data'])) {
            foreach ($response['data'] as $entry) {
                $result[$entry['seoId']] = $entry;
            }
        }

        return $result;
    }

    /**
     * Returns all attractions across all destinations in a single large map
     * 
     * This method sends dozens of requests one after another and collects all attractions so it can take a long time.
     * As of Jan 2022 less than 6k attractions.
     * 
     * @param type $locale
     * @return type
     */
    public function getAllAttractions($locale = 'en') {
        // get all destinaitons
        $destinations = $this->getDestinations();

        // find out top level destination ids
        $rootLevelDestinationIds = array();
        foreach ($destinations as $destination) {
            $path = $destination['lookupId'];
            $parts = explode('.', $path);
            $root = array_shift($parts);
            $rootLevelDestinationIds[$root] = $root;
        }

        $result = array();
        // loop over all top level destinations to fetch all attactions
        foreach ($rootLevelDestinationIds as $destinationId) {
            // offset in this legacy endpoint starts from 1
            $offset = 1;
            do {
                $resultPage = $this->getAttractions($locale, $destinationId, 100, $offset);
                // merge results into combined array replacing duplicates
                $result = array_replace($result, $resultPage);
                $offset = $offset + 100;
            } while (count($resultPage) > 0);
        }

        return $result;
    }

    /**
     * 
     * @param type $locale
     * @param type $limit
     * @param type $startTime
     * @return Viator\Client\GenericCursorPageResponseDataObject results in a map with nextCursor
     */
    public function getProductsModifiedSinceTime($locale = 'en', $limit = 100, $startTime = null) {
        $limit = ($limit >= 1 || $limit <= 500) ? (int) $limit : 100;

        $uri = '/products/modified-since?count=' . $limit . '&modified-since=' . urlencode($startTime);
        $cursorPageResponse = $this->getCursorBasedPage($uri, $locale, 'products', 'productCode');

        return $cursorPageResponse;
    }

    /**
     * 
     * @param type $locale
     * @param type $limit
     * @param type $nextCursor
     * @return Viator\Client\GenericCursorPageResponseDataObject results in a map with nextCursor
     */
    public function getProductsModifiedSinceCursor($locale = 'en', $limit = 100, $nextCursor = null) {
        $limit = ($limit >= 1 || $limit <= 500) ? (int) $limit : 100;

        $uri = '/products/modified-since?count=' . $limit . '&cursor=' . urlencode($nextCursor);
        $cursorPageResponse = $this->getCursorBasedPage($uri, $locale, 'products', 'productCode');

        return $cursorPageResponse;
    }

    /**
     * 
     * @param type $locale
     * @param type $limit
     * @param type $startTime
     * @return Viator\Client\GenericCursorPageResponseDataObject results in a map with nextCursor
     */
    public function getAvailabilityModifiedSinceTime($limit = 100, $startTime = null) {
        $limit = ($limit >= 1 || $limit <= 500) ? (int) $limit : 100;

        $uri = '/availability/schedules/modified-since?count=' . $limit . '&modified-since=' . urlencode($startTime);
        $cursorPageResponse = $this->getCursorBasedPage($uri, $locale, 'availabilitySchedules', 'productCode');

        return $cursorPageResponse;
    }

    /**
     * 
     * @param type $locale
     * @param type $limit
     * @param type $nextCursor
     * @return Viator\Client\GenericCursorPageResponseDataObject results in a map with nextCursor
     */
    public function getAvailabilityModifiedSinceCursor($limit = 100, $nextCursor = null) {
        $limit = ($limit >= 1 || $limit <= 500) ? (int) $limit : 100;

        $uri = '/availability/schedules/modified-since?count=' . $limit . '&cursor=' . urlencode($nextCursor);
        $cursorPageResponse = $this->getCursorBasedPage($uri, $locale, 'availabilitySchedules', 'productCode');

        return $cursorPageResponse;
    }

    /**
     * 
     * @param type $uri
     * @param type $locale
     * @param type $dataElementName
     * @param type $idElementName
     * @return GenericCursorPageResponseDataObject
     */
    protected function getCursorBasedPage($uri, $locale, $dataElementName, $idElementName) {
        $response = $this->sendGetRequest($uri, $locale);

        $data = array();
        if (!empty($response[$dataElementName])) {
            foreach ($response[$dataElementName] as $entry) {
                $data[$entry[$idElementName]] = $entry;
            }
        }

        $nextCursor = (string) $response['nextCursor'];

        return new GenericCursorPageResponseDataObject($data, $nextCursor);
    }

    public function getLocationsBulk($locale = 'en', $references = array()) {

        $data = array(
            "locations" => array_values($references)
        );
        $response = $this->sendPostRequest('/locations/bulk', $data, $locale);

        $result = array();
        if (!empty($response['locations'])) {
            foreach ($response['locations'] as $entry) {
                $result[$entry['reference']] = $entry;
            }
        }

        return $result;
    }

    public function getProductsBulk($locale = 'en', $references = array()) {
        $data = array(
            "productCodes" => array_values($references)
        );
        $response = $this->sendPostRequest('/products/bulk', $data, $locale);

        $result = array();
        if (!empty($response)) {
            foreach ($response as $entry) {
                $result[$entry['productCode']] = $entry;
            }
        }

        return $result;
    }

    public function getAvailabilityBulk($locale = 'en', $references = array()) {
        $data = array(
            "productCodes" => array_values($references)
        );
        $response = $this->sendPostRequest('/availability/schedules/bulk', $data, $locale);

        $result = array();
        if (!empty($response['availabilitySchedules'])) {
            foreach ($response['availabilitySchedules'] as $entry) {
                $result[$entry['productCode']] = $entry;
            }
        }

        return $result;
    }

}
