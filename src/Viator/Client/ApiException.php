<?php

namespace Viator\Client;

use \Exception;

class ApiException extends Exception {

    /**
     * The HTTP body of the server response either as Json or string.
     *
     * @var string|null
     */
    protected $responseBody;

    /**
     * The HTTP header of the server response.
     *
     * @var string[]|null
     */
    protected $responseHeaders;

    /**
     * Constructor
     *
     * @param string                $message         Error message
     * @param int                   $code            HTTP status code
     * @param string[]|null         $responseHeaders HTTP response header
     * @param array|string|null     $responseBody    HTTP decoded body of the server response either as \stdClass or string
     */
    public function __construct($message = "", $code = 0, $responseHeaders = [], $responseBody = null) {
        parent::__construct($message, $code);
        $this->responseHeaders = $responseHeaders;
        $this->responseBody = $responseBody;
    }

    /**
     * Gets the HTTP response header
     *
     * @return string[]|null HTTP response header
     */
    public function getResponseHeaders() {
        return $this->responseHeaders;
    }

    /**
     * Gets the HTTP body of the server response 
     *
     * @return array|string|null HTTP body of the server response either as array or string
     */
    public function getResponseBody() {
        return $this->responseBody;
    }

}
