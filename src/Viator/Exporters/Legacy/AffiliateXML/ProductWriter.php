<?php

namespace Viator\Exporters\Legacy\AffiliateXML;

class ProductWriter {

    private $rank = 0;
    private $exchangeRates;

    public function __construct($exchangeRates) {
        $this->exchangeRates = $exchangeRates;
    }

    public function writeProduct($combinedProduct) {
        $this->rank++;

        $res = array();
        $res['Product'] = array(
            'Rank' => $this->rank,
            'ProductType' => 'SITours_NEW',
            'ProductCode' => $combinedProduct['product']['productCode'],
            'ProductName' => $combinedProduct['product']['title'],
            'Introduction' => '???',
            'ProductText' => $combinedProduct['product']['description'],
            'SpecialDescription' => null,
            'Special' => 0, // ?
            'Duration' => ' to TEXT ',
            'Commences' => 'start point city',
            'ProductImage' => array(
                'ThumbnailURL' => small,
                'ImageURL' => larger,
            ),
            'Destination' => array(
                'ID' => 2,
                'Continent' => 2,
                'Country' => 2,
                'Region' => 2,
                'City' => 2,
                'IATACode' => 2,
            ),
            'ProductCategories' => array(
                //???
                'ProductCategory' => array(
                    'Group' => '',
                    'Category' => '',
                    'Subcategory' => '',
                )
            ),
            'ProductURLs' => array(
                'ProductURL' => '',
            ),
            'Pricing' => array(
                $this->getPricesArray($combinedProduct['schedules']['summary']['fromPrice'], $combinedProduct['schedules']['currency'])
            ),
            'BookingType' => $combinedProduct['product']['bookingConfirmationSettings']['confirmationType'], // map to legacy strings
            'VoucherOption' => '',
        );

        print_r($res);
        die();
    }

    private function getPricesArray($price, $currencyFrom) {
        $currencies = array(
            'AUD',
            'NZD',
            'EUR',
            'GBP',
            'USD',
            'CAD',
            'CHF',
            'NOK',
            'JPY',
            'SEK',
            'HKD',
            'SGD',
            'ZAR',
            'INR',
            'TWD',
        );

        $result = array();
        foreach ($currencies as $code) {
            $exchangeRate = $this->exchangeRates[$currencyFrom . '-' . $code]['rate'];
            $result['Price' . $code] = $price * $exchangeRate;
        }
        return $result;
    }

}
