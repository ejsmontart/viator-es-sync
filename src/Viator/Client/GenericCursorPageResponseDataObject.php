<?php

namespace Viator\Client;

/**
 * Object wraps modified-since response so that we have cursor and data accessible independently
 */
class GenericCursorPageResponseDataObject {

    public $data = [];
    public $nextCursor = null;

    public function __construct($data, $nextCursor) {
        $this->data = $data;
        $this->nextCursor = $nextCursor;
    }

    /**
     * @return array data 
     */
    public function getData() {
        return $this->data;
    }

    /**
     * @return string next cursor to be used with modified-since
     */
    public function getNextCursor() {
        return $this->nextCursor;
    }

}
