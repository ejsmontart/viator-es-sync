<?php

namespace Viator\Exporters\Legacy\Deals;

class ProductWriter {

    private $rank = 0;
    private $exchangeRates = array();
    private $allDestinations = array();
    private $lang = null;
    private $country = null;

    public function __construct($exchangeRates, $allDestinations, $lang, $country) {
        $this->exchangeRates = $exchangeRates;
        $this->allDestinations = $allDestinations;
        $this->lang = $lang;
        $this->country = $country;
    }

    public function writeProduct($combinedProduct) {
        $res = array();

        $country = $this->getDestinationOrParentByType($combinedProduct, 'COUNTRY');
        $city = $this->getDestinationOrParentByType($combinedProduct, 'CITY');

        $res['Product'] = array(
            'product_id' => $combinedProduct['product']['productCode'],
            'product_name' => $combinedProduct['product']['title'],
            'deep_link' => '#### URL with awin param   ?aid='. urlencode($this->getLocalisedAid()),
            'description' => $combinedProduct['product']['description'],
            'Specification' => '#### category name?',
            'IMAGE_URL' => $this->findImage($combinedProduct, 360),
            'THUMB_URL' => $this->findImage($combinedProduct, 75),
            'STAR_RATING' => $this->getAverageRating($combinedProduct),
            'Custom_1' => isset($country['destinationName']) ? $country['destinationName'] : '',
            'Location' => isset($city['destinationName']) ? $city['destinationName'] : '',
            'PRICE' => $combinedProduct['schedules']['summary']['fromPrice'],
            'CURRENCY' => $combinedProduct['schedules']['currency'],
            'merchant_category' => $this->getTranslatedCategorey(),
        );

        print_r();
        print_r($this->getDestinationOrParentByType($combinedProduct, 'CITY'));

        print_r($res);
        echo "x\n";
        print_r($combinedProduct);
        echo "x\n";

        die();
    }

    /**
     * Returns url of first image that is closest width to requested size
     * 
     * @param type $combinedProduct
     * @param type $width
     */
    private function findImage(&$combinedProduct, $width) {
        $closestW = 1000000;
        $closestUrl = null;

        foreach ($combinedProduct['product']['images'] as $image) {
            foreach ($image['variants'] as $variant) {
                if (abs($width - $variant['width']) < $closestW) {
                    $closestW = abs($width - $variant['width']);
                    $closestUrl = $variant['url'];
                }
            }
        }

        return $closestUrl;
    }

    private function getDestinationOrParentByType(&$combinedProduct, $type) {
        foreach ($combinedProduct['destinations'] as $dest) {
            $lookupParts = explode(".", $dest['lookupId']);
            foreach ($lookupParts as $id) {

                echo $id . " ";

                if (!empty($this->allDestinations[$id]) && $this->allDestinations[$id]['destinationType'] == $type) {
                    return $this->allDestinations[$id];
                }
            }
        }


        return null;
    }

    private function getAverageRating(&$combinedProduct) {

        if (!empty($combinedProduct['product']['reviews']['combinedAverageRating'])) {
            return $combinedProduct['product']['reviews']['combinedAverageRating'];
        } else {
            return null;
        }
    }

    // we had it hardcoded with only 2 languages
    private function getTranslatedCategorey() {
        if ($this->lang == 'fr') {
            return "Attractions Touristiques";
        } else {
            return "Tourist Attractions";
        }
    }

    private function getLocalisedAid() {
        $prefix = 'awin';
        $suffix = 'LIVEPRODFEED_!!!id!!!';

        if ($this->country == 'AUD') {
            return $prefix . "aus" . $suffix;
        } else if ($this->country == 'CAD') {
            return $prefix . "cad" . $suffix;
        } else if ($this->country == 'USD') {
            return $prefix . "us" . $suffix;
        } else {
            return $prefix . $suffix;
        }
    }

}
