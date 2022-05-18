<?php

namespace Viator\Client\Storage;

use Elasticsearch\Helper\Iterators\SearchHitIterator;
use Elasticsearch\Helper\Iterators\SearchResponseIterator;

class SimpleElasticSearchStorage {

    /**
     * 
     * @var Elasticsearch\Client
     */
    private $client;

    public function __construct(\Elasticsearch\Client $client) {
        $this->client = $client;
    }

    /**
     * Get one object by reference/id
     * 
     * @param string $referenceType name of the table/collection/file containing objects
     * @param string $reference reference/id of the object to fetch
     * @return array
     */
    public function getByReference($referenceType, $reference) {

        $params = [
            'index' => $referenceType,
            'id' => $reference
        ];

        try {
            $response = $this->client->get($params);
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            return array();
        }

        return $response['_source'];
    }

    /**
     * insert/update one object by reference/id
     * 
     * @param string $referenceType name of the table/collection/file containing objects
     * @param string $reference reference/id of the object to set
     * @param array $data data to store 
     * @return array
     */
    public function setByReference($referenceType, $reference, $data) {
        $response = $this->bulkUpsert($referenceType, [$reference => $data]);
    }

    /**
     * insert/update a set of objects. Map passed in has to be indexed by their references/ids
     * Existing objects are overwritten.
     * 
     * @param string $referenceType name of the table/collection/file containing objects
     * @param array $data array containing objects. Id of the object is array index, value is the object
     * @return array
     */
    public function bulkUpsert($referenceType, $data) {
        $params = ['refresh' => true];
        foreach ($data as $ref => $value) {
            $params['body'][] = [
                'index' => [
                    '_index' => $referenceType,
                    '_id' => $ref
                ]
            ];

            $params['body'][] = $value;
        }

        $response = $this->client->bulk($params);

        $this->logStats($referenceType, 'bulkUpsert', count($data));
    }

    /**
     * Insert a set of objects. Map passed in has to be indexed by their references/ids
     * For each object: If object with same reference already exists it is ignored.
     * 
     * @param string $referenceType name of the table/collection/file containing objects
     * @param array $data array containing objects. Id of the object is array index, value is the object
     * @return array
     */
    public function bulkCreate($referenceType, $data) {
        $params = ['refresh' => true];
        foreach ($data as $ref => $value) {
            $params['body'][] = [
                'create' => [
                    '_index' => $referenceType,
                    '_id' => $ref
                ]
            ];

            $params['body'][] = $value;
        }

        $response = $this->client->bulk($params);

        $this->logStats($referenceType, 'bulkCreate', count($data));
    }

    /**
     * Delete a set of objects. 
     * For each object: If object does not exist, id is ignored.
     * 
     * @param string $referenceType name of the table/collection/file containing objects
     * @param array $data array containing object ids as strings.
     * @return array
     */
    public function bulkDelete($referenceType, $data) {
        $params = ['refresh' => true];
        foreach ($data as $ref) {
            $params['body'][] = [
                'delete' => [
                    '_index' => $referenceType,
                    '_id' => $ref
                ]
            ];
        }

        $response = $this->client->bulk($params);

        $this->logStats($referenceType, 'bulkDelete', count($data));
    }

    /**
     * Get a set of objects. 
     * For each object: If object does not exist, dont return it.
     * Entire result may be empty array if none is found.
     * 
     * @param string $referenceType name of the table/collection/file containing objects
     * @param array $data array containing object ids as strings.
     * @return array
     */
    public function bulkGet($referenceType, $data) {
        $params = array();
        $params['index'] = $referenceType;
        $params['_source'] = true;
        foreach ($data as $ref) {
            $params['body']['docs'][] = array('_id' => $ref);
        }

        $response = $this->client->mget($params);
        $result = array();
        if (!empty($response['docs'])) {
            foreach ($response['docs'] as $hit) {
                $result[$hit['_id']] = $hit['_source'];
            }
        }
        return $result;
    }

    /**
     * Specialized helper query allowing us to efficiently find locations which need to be fetched for specific locale
     * 
     * @param string $locale
     * @param int $limit
     * @return array locations that need fetching for the specific language
     */
    public function findUnsyncedLocations($locale, $limit) {
        $params = [
            'index' => 'viator_sync_location_refs',
            'size' => $limit,
            'body' => [
                'query' => [
                    'bool' => [
                        'must_not' => [
                            'bool' => [
                                'should' => [
                                    'exists' => ["field" => "updated_" . $locale]
                                ],
                                "minimum_should_match" => 1,
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $response = $this->client->search($params);
        $result = array();
        if (!empty($response['hits']['hits'])) {
            foreach ($response['hits']['hits'] as $hit) {
                $result[$hit['_id']] = $hit['_source'];
            }
        }
        return $result;
    }

    /**
     * Allows you to load all objects at once so you can use them as a lookup map
     * 
     * WARNING - don't use this method for large data sets as it will exhaust all memory
     * 
     * @param string $referenceType name of the table/collection/file containing objects
     * @return array map of all object loaded in one go, object id is the key and value is the data
     */
    public function getAllAtOnce($referenceType) {
        $params = [
            'scroll' => '10m', // period to retain the search context
            'index' => $referenceType, // here the index name
            'size' => 200, // 100 results per page
            'body' => [
                'query' => [
                    'match_all' => new \StdClass // {} in JSON
                ]
            ]
        ];
        $pages = new SearchResponseIterator($this->client, $params);
        $hits = new SearchHitIterator($pages);

        $result = array();
        foreach ($hits as $hit) {
            $result[$hit['_id']] = $hit['_source'];
        }

        return $result;
    }

    /**
     * Specialized helper query allowing us to efficiently find locations which need to be fetched for specific locale
     * 
     * @param string $locale
     * @param int $limit
     * @return array locations that need fetching for the specific language
     */
    public function getActiveProductIterator($locale, $limit) {
        $params = [
            'index' => 'viator_raw_products_' . $locale,
            'scroll' => '30m', // period to retain the search context
            'size' => $limit,
            '_source' => false,
            'body' => [
                "query" => [
                    "bool" => [
                        "must" => [],
                        "filter" => [
                            [
                                "match_phrase" => [
                                    "status" => "ACTIVE"
                                ]
                            ]
                        ],
                        "should" => [],
                        "must_not" => []
                    ]
                ]
            ]
        ];

        return new SearchResponseIterator($this->client, $params);
    }

    protected function logStats($referenceType, $operation, $objectCount) {
        $params = [];
        $params['body'][] = [
            'index' => [
                '_index' => 'viator_sync_log',
                '_id' => microtime(true) . uniqid(),
            ]
        ];
        $params['body'][] = array(
            'timestamp' => time(),
            'datetime' => date(DATE_ATOM, time()),
            'collection' => $referenceType,
            'objectCount' => $objectCount,
            'operation' => $operation
        );
        $response = $this->client->bulk($params);
    }

}
