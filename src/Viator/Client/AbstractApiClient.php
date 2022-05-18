<?php

namespace Viator\Client;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Viator\Client\ApiException;
use Viator\Client\Configuration;

/**
 * Hides some complexity of sending Guzzle request
 * Exposes simple GET / POST operations specific for Viator API needs.
 * 
 */
abstract class AbstractApiClient {

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var Configuration
     */
    protected $config;

    /**
     * @param ClientInterface $client
     * @param Configuration   $config
     */
    public function __construct(
            ClientInterface $client = null,
            Configuration $config = null
    ) {
        $this->client = $client ?: new Client();
        $this->config = $config ?: new Configuration();
    }

    /**
     * Sends a GET request 
     *
     * @param string $uri
     * @param string $locale when null then Accept-Language request header is not set
     * @throws \InvalidArgumentException
     * @return array decoded response from Viator service
     */

    /**
     * 
     * @return type
     */
    public function sendGetRequest($uri, $locale = null) {
        $headers = $this->getHeaders($locale);

        $request = new Request(
                'GET',
                $this->config->getHostPrefix() . $uri,
                $headers,
                ''
        );
        $response = $this->sendRequest($request);

        return json_decode($response, true);
    }

    /**
     * Sends a POST request
     *
     * @param string $uri
     * @param mixed $data payload which will be json encoded
     * @param string $locale when null then Accept-Language request header is not set
     * @throws \InvalidArgumentException
     * @return array decoded response from Viator service
     */
    public function sendPostRequest($uri, $data, $locale = null) {
        $headers = $this->getHeaders($locale);

        $httpBody = \GuzzleHttp\json_encode($data);

        $request = new Request(
                'POST',
                $this->config->getHostPrefix() . $uri,
                $headers,
                $httpBody
        );

        $response = $this->sendRequest($request);

        return json_decode($response, true);
    }

    /**
     * Sets all headers
     * 
     * @param string $locale when set Accept-Language will be added
     * @return array of headers
     */
    protected function getHeaders($locale = null) {
        $headers = array(
            'exp-api-key' => $this->config->getApiKey(),
            'Accept' => 'application/json;version=2.0',
            'Content-Type' => 'application/json',
            'User-Agent' => 'PAPI Open Client/PHP',
        );

        if (!empty($locale)) {
            $headers['Accept-Language'] = $locale;
        }

        return $headers;
    }

    /**
     * Generic HTTP request handling
     *
     *
     * @throws ApiException on non-2xx response or connection error
     * @return string response body
     */
    protected function sendRequest($request) {
        $options = $this->getRequestOptions();
        try {
            $response = $this->client->send($request, $options);
        } catch (RequestException $e) {
            throw new ApiException(
                            "[{$e->getCode()}] {$e->getMessage()}",
                            (int) $e->getCode(),
                            $e->getResponse() ? $e->getResponse()->getHeaders() : null,
                            $e->getResponse() ? (string) $e->getResponse()->getBody() : null
            );
        } catch (ConnectException $e) {
            throw new ApiException(
                            "[{$e->getCode()}] {$e->getMessage()}",
                            (int) $e->getCode(),
                            null,
                            null
            );
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 400) {
            throw new ApiException(
                            sprintf(
                                    '[%d] Error connecting to the API (%s)',
                                    $statusCode,
                                    (string) $request->getUri()
                            ),
                            $statusCode,
                            $response->getHeaders(),
                            (string) $response->getBody()
            );
        } else {
            return (string) $response->getBody();
        }
    }

    /**
     * Sets guzzle request options
     *      Logs full HTTP traffic into a file or std out if debug is ON
     *      Sets timeouts
     *
     * @throws \RuntimeException on file opening failure
     * @return array of HTTP client options
     */
    protected function getRequestOptions() {
        $options = [];
        if ($this->config->getDebug()) {
            $options[RequestOptions::DEBUG] = fopen($this->config->getDebugFile(), 'a');
            if (!$options[RequestOptions::DEBUG]) {
                throw new \RuntimeException('Failed to open the debug file: ' . $this->config->getDebugFile());
            }
        }

        $options[RequestOptions::ALLOW_REDIRECTS] = array('max' => 5);
        $options[RequestOptions::TIMEOUT] = 90;
        $options[RequestOptions::READ_TIMEOUT] = 90;
        $options[RequestOptions::CONNECT_TIMEOUT] = 90;

        return $options;
    }

}
