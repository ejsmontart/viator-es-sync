<?php

namespace Viator\Client\Helpers;

/**
 * Extracts location references from product data
 * Locations appear in different places based on itinerary and product configuration
 */
class ProductLocationExtractor {

    /**
     * Inspects different parts of the product details and collects all location references found
     * 
     * @param array $productData full product details 
     * @return array array of location references found in the product 
     */
    public function extractLocationRefs($productData) {
        $allLocations = array();

        // -> logistics.redemption.locations[].ref
        if (!empty($productData ['logistics']['redemption']['locations'])) {
            foreach ($productData ['logistics']['redemption']['locations'] as $location) {
                if (!empty($location['ref'])) {
                    $allLocations[$location['ref']] = $location['ref'];
                }
            }
        }

        // -> logistics.start[].location.ref
        if (!empty($productData ['logistics']['start'])) {
            foreach ($productData ['logistics']['start'] as $location) {
                if (!empty($location['location']['ref'])) {
                    $allLocations[$location['location']['ref']] = $location['location']['ref'];
                }
            }
        }

        // -> logistics.end[].location.ref
        if (!empty($productData ['logistics']['end'])) {
            foreach ($productData ['logistics']['end'] as $location) {
                if (!empty($location['location']['ref'])) {
                    $allLocations[$location['location']['ref']] = $location['location']['ref'];
                }
            }
        }


        // Pickup locations
        // -> logistics.travelerPickup.locations[].location.ref
        if (!empty($productData ['logistics']['travelerPickup']['locations'])) {
            foreach ($productData ['logistics']['travelerPickup']['locations'] as $location) {
                if (in_array($location['pickupType'], array('HOTEL', 'AIRPORT', 'PORT'))) {
                    if (!empty($location['location']['ref'])) {
                        $allLocations[$location['location']['ref']] = $location['location']['ref'];
                    }
                }
            }
        }

        // standard itinerary
        // -> itinerary.itineraryItems[].pointOfInterestLocation.location.ref
        if (!empty($productData ['itinerary']['itineraryItems'])) {
            foreach ($productData ['itinerary']['itineraryItems'] as $item) {
                if (!empty($item['pointOfInterestLocation']['location']['ref'])) {
                    $allLocations[$item['pointOfInterestLocation']['location']['ref']] = $item['pointOfInterestLocation']['location']['ref'];
                }
            }
        }


        // Multi day tours
        // -> itinerary.days[].items[].pointOfInterestLocation.location.ref
        // -> itinerary.days[].accommodations[].location.ref
        if (!empty($productData ['itinerary']['days'])) {
            foreach ($productData ['itinerary']['days'] as $day) {
                if (!empty($day['items'])) {
                    foreach ($day['items'] as $item) {
                        if (!empty($item['pointOfInterestLocation']['location']['ref'])) {
                            $allLocations[$item['pointOfInterestLocation']['location']['ref']] = $item['pointOfInterestLocation']['location']['ref'];
                        }
                    }
                }
                if (!empty($day['accommodations'])) {
                    foreach ($day['accommodations'] as $item) {
                        if (!empty($item['location']['ref'])) {
                            $allLocations[$item['location']['ref']] = $item['location']['ref'];
                        }
                    }
                }
            }
        }

        // Hop on Hop off
        // -> itinerary.routes[].pointsOfInterest[].location.ref
        // -> itinerary.routes[].stops[].stopLocation.ref
        if (!empty($productData ['itinerary']['routes'])) {
            foreach ($productData ['itinerary']['routes'] as $route) {
                if (!empty($route['pointsOfInterest'])) {
                    foreach ($route['pointsOfInterest'] as $poi) {
                        if (!empty($poi['location']['ref'])) {
                            $allLocations[$poi['location']['ref']] = $poi['location']['ref'];
                        }
                    }
                }
                if (!empty($route['stops'])) {
                    foreach ($route['stops'] as $stop) {
                        if (!empty($stop['stopLocation']['ref'])) {
                            $allLocations[$stop['stopLocation']['ref']] = $stop['stopLocation']['ref'];
                        }
                    }
                }
            }
        }

        // -> itinerary.pointsOfInterest.ref
        if (!empty($productData ['itinerary']['pointsOfInterest'])) {
            foreach ($productData ['itinerary']['pointsOfInterest'] as $location) {
                if (!empty($location['ref'])) {
                    $allLocations[$location['ref']] = $location['ref'];
                }
            }
        }

        // -> itinerary.activityInfo.location.ref
        if (!empty($productData ['itinerary']['activityInfo']['location']['ref'])) {
            $ref = $productData ['itinerary']['activityInfo']['location']['ref'];
            $allLocations[$ref] = $ref;
        }

        // Unstructured itinerary
        // -> itinerary.pointOfInterestLocations[].location.ref
        if (!empty($productData ['itinerary']['pointOfInterestLocations'])) {
            foreach ($productData ['itinerary']['pointOfInterestLocations'] as $poi) {
                if (!empty($poi['location']['ref'])) {
                    $allLocations[$poi['location']['ref']] = $poi['location']['ref'];
                }
            }
        }

        return $allLocations;
    }

}
