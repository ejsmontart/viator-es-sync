<?php

namespace Viator\Client\Helpers;

/**
 * Extracts attraction ids references from product data
 * 
 */
class ProductAttractionExtractor {

    /**
     * Inspects different parts of the product details and collects all attraction ids
     * 
     * @param array $productData full product details 
     * @return array array of attraction ids found in the product 
     */
    public function extractAttractionIds($productData) {
        $allLocations = array();

        // standard itinerary
        // -> itinerary.itineraryItems[].pointOfInterestLocation.attractionId
        if (!empty($productData ['itinerary']['itineraryItems'])) {
            foreach ($productData ['itinerary']['itineraryItems'] as $item) {
                if (!empty($item['pointOfInterestLocation']['attractionId'])) {
                    $allLocations[$item['pointOfInterestLocation']['attractionId']] = $item['pointOfInterestLocation']['attractionId'];
                }
            }
        }


        // Multi day tours
        // -> itinerary.days[].items[].pointOfInterestLocation.attractionId
        if (!empty($productData ['itinerary']['days'])) {
            foreach ($productData ['itinerary']['days'] as $day) {
                if (!empty($day['items'])) {
                    foreach ($day['items'] as $item) {
                        if (!empty($item['pointOfInterestLocation']['attractionId'])) {
                            $allLocations[$item['pointOfInterestLocation']['attractionId']] = $item['pointOfInterestLocation']['attractionId'];
                        }
                    }
                }
            }
        }

        // Hop on Hop off
        // -> itinerary.routes[].pointsOfInterest[].attractionId
        if (!empty($productData ['itinerary']['routes'])) {
            foreach ($productData ['itinerary']['routes'] as $route) {
                if (!empty($route['pointsOfInterest'])) {
                    foreach ($route['pointsOfInterest'] as $poi) {
                        if (!empty($poi['attractionId'])) {
                            $allLocations[$poi['attractionId']] = $poi['attractionId'];
                        }
                    }
                }
            }
        }


        // Unstructured itinerary
        // -> itinerary.pointOfInterestLocations[].attractionId
        if (!empty($productData ['itinerary']['pointOfInterestLocations'])) {
            foreach ($productData ['itinerary']['pointOfInterestLocations'] as $poi) {
                if (!empty($poi['attractionId'])) {
                    $allLocations[$poi['attractionId']] = $poi['attractionId'];
                }
            }
        }

        return $allLocations;
    }

}
