<?php

namespace Viator\Client\Helpers;

/**
 * Overrides product status to INACTIVE based on your business criteria.
 * Intention is to reduce the amount of data you need to refresh and reduce size of your database.
 * 
 *      Adds ['_sync_annotations'] node to the product data with additional information
 */
class ProductFilteringAnnotator {

    /**
     * Accepts Viator product details array, adds "_sync_annotations" node and overrides status if needed.
     * 
     * @param type $productData
     * @return array Updated product data array
     */
    public function processProduct($productData, $scheduleData) {
        $this->addAnnotation($productData, 'ProductFilteringAnnotator.lastUpdate', time());

        // mark inactive products as rejected
        if (empty($productData['status']) || $productData['status'] == 'INACTIVE') {
            //$productData['status'] = 'INACTIVE'; this would be redundant
            $this->addAnnotation($productData, 'ProductFilteringAnnotator.status', 'Rejected');
            $this->addAnnotation($productData, 'ProductFilteringAnnotator.reason', 'inactive');
            return $productData;
        }

        $skipItineraryTypes = array(
            'HOP_ON_HOP_OFF' // fairly rare product type with complex schema
        );
        if (empty($productData['itinerary']['itineraryType']) || in_array($productData['itinerary']['itineraryType'], $skipItineraryTypes)) {
            $productData['status'] = 'INACTIVE';
            $this->addAnnotation($productData, 'ProductFilteringAnnotator.status', 'Rejected');
            $this->addAnnotation($productData, 'ProductFilteringAnnotator.reason', 'itineraryType');
            return $productData;
        }

        // new or unpopular products which have no reviews
        if (empty($productData['reviews']['totalReviews']) || $productData['reviews']['totalReviews'] < 2) {
            $productData['status'] = 'INACTIVE';
            $this->addAnnotation($productData, 'ProductFilteringAnnotator.status', 'Rejected');
            $this->addAnnotation($productData, 'ProductFilteringAnnotator.reason', 'reviewCount');
            return $productData;
        }

        // products with bad avg review
        if (empty($productData['reviews']['combinedAverageRating']) || $productData['reviews']['combinedAverageRating'] <2.5) {
            $productData['status'] = 'INACTIVE';
            $this->addAnnotation($productData, 'ProductFilteringAnnotator.status', 'Rejected');
            $this->addAnnotation($productData, 'ProductFilteringAnnotator.reason', 'poorRating');
            return $productData;
        }

        // some types of products may be less desirable
        $skipTags = array(
            12044 => true, // Airport & Hotel Transfers
            20238 => true, // Private Drivers 
        );
        if (!empty($productData['tags'])) {
            foreach ($productData['tags'] as $tag) {
                if (isset($skipTags[$tag])) {
                    $productData['status'] = 'INACTIVE';
                    $this->addAnnotation($productData, 'ProductFilteringAnnotator.status', 'Rejected');
                    $this->addAnnotation($productData, 'ProductFilteringAnnotator.reason', 'tags');
                    $this->addAnnotation($productData, 'ProductFilteringAnnotator.tag', $tag);
                    return $productData;
                }
            }
        }

        // schedule missing or incomplete
        if (empty($scheduleData['currency']) || empty($scheduleData['summary']['fromPrice']) || empty($scheduleData['bookableItems'])) {
            $productData['status'] = 'INACTIVE';
            $this->addAnnotation($productData, 'ProductFilteringAnnotator.status', 'Rejected');
            $this->addAnnotation($productData, 'ProductFilteringAnnotator.reason', 'schedule');
            return $productData;
        }

        // otherwise take it
        $this->addAnnotation($productData, 'ProductFilteringAnnotator.status', 'accepted');

        return $productData;
    }

    /**
     * Adds property to the _sync_annotations element, adds _sync_annotations first if missing
     * 
     * @param array $productData passed by reference (update in place)
     * @param string $key key to be set
     * @param string $value value to be ser
     */
    private function addAnnotation(&$productData, $key, $value) {
        if (empty($productData['_sync_annotations'])) {
            $productData['_sync_annotations'] = array();
        }
        $productData['_sync_annotations'][$key] = $value;
    }

}
