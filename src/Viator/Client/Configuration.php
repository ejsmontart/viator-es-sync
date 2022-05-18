<?php

namespace Viator\Client;

class Configuration {

    /**
     * Associate array to store API key(s)
     *
     * @var string
     */
    protected $apiKey = '';

    /**
     * The host prefix
     * Production prefix may look like this: https://api.viator.com/partner
     *
     * @var string
     */
    protected $hostPrefix = '';

    /**
     * location of elastic search instance to use
     *
     * @var string
     */
    protected $elasticSearchUrl = '';

    /**
     * Array of locales to be used for data imports
     *
     * @var array example value: array('en','fr')
     */
    protected $locales = '';

    /**
     * Debug switch (default set to false)
     *
     * @var bool
     */
    protected $debug = false;

    /**
     * Debug file location (log to STDOUT by default)
     *
     * @var string
     */
    protected $debugFile = 'php://output';

    /**
     * Constructor is private as its a singleton
     */
    public function __construct() {
        
    }

    /**
     * Sets API key
     *
     * @param string $key
     *
     * @return $this
     */
    public function setApiKey($key) {
        if (empty($key)) {
            throw new \InvalidArgumentException('empty api key');
        }
        $this->apiKey = $key;
        return $this;
    }

    /**
     * Gets API key
     *
     * @param string $apiKeyIdentifier API key identifier (authentication scheme)
     *
     * @return null|string API key or token
     */
    public function getApiKey() {
        return $this->apiKey;
    }

    /**
     * Sets the host prefix
     *
     * @param string $hostPrefix Production prefix may look like this: https://api.viator.com/partner
     *
     * @return $this
     */
    public function setHostPrefix($hostPrefix) {
        if (empty($hostPrefix)) {
            throw new \InvalidArgumentException('empty host prefix');
        }

        $this->hostPrefix = $hostPrefix;
        return $this;
    }

    /**
     * Gets the host
     *
     * @return string Host
     */
    public function getHostPrefix() {
        return $this->hostPrefix;
    }

    /**
     * Sets debug flag
     *
     * @param bool $debug Debug flag
     *
     * @return $this
     */
    public function setDebug($debug) {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Gets the debug flag
     *
     * @return bool
     */
    public function getDebug() {
        return $this->debug;
    }

    /**
     * Sets the debug file
     *
     * @param string $debugFile Debug file
     *
     * @return $this
     */
    public function setDebugFile($debugFile) {
        $this->debugFile = $debugFile;
        return $this;
    }

    /**
     * Gets the debug file
     *
     * @return string
     */
    public function getDebugFile() {
        return $this->debugFile;
    }

    /**
     * Sets elastic search url to be used
     *
     * @param string $url url of ES instance
     * @return $this
     */
    public function setElasticSearchUrl($url) {
        if (empty($url)) {
            throw new \InvalidArgumentException('empty elastic search url');
        }

        $this->elasticSearchUrl = $url;
        return $this;
    }

    /**
     * Gets elastic search url
     *
     * @return string
     */
    public function getElasticSearchUrl() {
        return $this->elasticSearchUrl;
    }

    /**
     * @param array $locales array of locales to import
     * @return $this
     */
    public function setLocales($locales) {
        if (empty($locales)) {
            throw new \InvalidArgumentException('empty locales list');
        }

        $this->locales = $locales;
        return $this;
    }

    /**
     * Gets array of locales to import
     *
     * @return string
     */
    public function getLocales() {
        return $this->locales;
    }

}
