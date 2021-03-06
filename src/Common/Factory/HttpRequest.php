<?php

/**
 * Simple HTTP requests
 *
 * @package     Nails
 * @subpackage  common
 * @category    Factory
 * @author      Nails Dev Team
 */

namespace Nails\Common\Factory;

use GuzzleHttp\Client;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\NailsException;
use Nails\Common\Helper\ArrayHelper;
use Nails\Config;
use Nails\Environment;
use Nails\Factory;
use Nails\Testing;

/**
 * Class HttpRequest
 *
 * @package Nails\Common\Factory
 */
abstract class HttpRequest
{
    /**
     * The HTTP Method for the request
     *
     * @var string
     */
    const HTTP_METHOD = '';

    // --------------------------------------------------------------------------

    /**
     * The request headers
     *
     * @var array
     */
    protected $aHeaders = [];

    /**
     * The Base URI for the request
     *
     * @var string
     */
    protected $sBaseUri = '';

    /**
     * The path for the request
     *
     * @var string
     */
    protected $sPath = '';

    /**
     * The UserAgent to use for the request
     *
     * @var string
     */
    protected $sUserAgent = 'Nails';

    /**
     * Basic auth username
     *
     * @var string
     */
    protected $sAuthUsername = '';

    /**
     * Basic auth password
     *
     * @var string
     */
    protected $sAuthPassword = '';

    // --------------------------------------------------------------------------

    /**
     * HttpRequest constructor.
     *
     * @param string $sBaseUri The Base URI for the request
     * @param string $sPath    The path for the request
     * @param array  $aHeaders An array of headers to set
     */
    public function __construct(string $sBaseUri = '', string $sPath = '', array $aHeaders = [])
    {
        if (Environment::is(Environment::ENV_TEST)
            && !$sBaseUri
            && !array_key_exists(Testing::TEST_HEADER_NAME, $aHeaders)
        ) {

            $aHeaders[Testing::TEST_HEADER_NAME] = Testing::TEST_HEADER_VALUE;
        }

        $this->baseUri($sBaseUri);
        $this->path($sPath);
        $this->userAgent($this->sUserAgent);

        foreach ($aHeaders as $sHeader => $mValue) {
            $this->setHeader($sHeader, $mValue);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Sets a header
     *
     * @param $sHeader
     * @param $mValue
     *
     * @return $this
     */
    public function setHeader($sHeader, $mValue): self
    {
        if (empty($this->aHeaders)) {
            $this->aHeaders = [];
        }

        $this->aHeaders[$sHeader] = $mValue;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the request headers
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return isset($this->aHeaders) ? $this->aHeaders : [];
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a single header
     *
     * @param string $sHeader The header to return
     *
     * @return mixed|null
     */
    public function getHeader($sHeader)
    {
        return isset($this->aHeaders[$sHeader]) ? $this->aHeaders[$sHeader] : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the required headers for imitating a user
     *
     * @param object|int $oUser The user to imitate
     *
     * @return $this
     */
    public function asUser($oUser): self
    {
        if ($oUser === null) {
            return $this;

        } elseif (is_int($oUser)) {
            $oUser = (object) ['id' => $oUser];

        } elseif (!is_object($oUser) || !property_exists($oUser, 'id')) {
            throw new \InvalidArgumentException(
                sprintf('Passed user must be an object with an `id` property or an integer')
            );
        }

        return $this
            ->setHeader(Testing::TEST_HEADER_NAME, Testing::TEST_HEADER_VALUE)
            ->setHeader(Testing::TEST_HEADER_USER_NAME, $oUser->id);
    }

    // --------------------------------------------------------------------------

    /**
     * Populates the baseUri property of the request
     *
     * @param string $sBaseUri The base for the request
     *
     * @return $this
     */
    public function baseUri(string $sBaseUri): self
    {
        $this->sBaseUri = $sBaseUri ?: Config::get('BASE_URL');
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the base URI
     *
     * @return string
     */
    public function getBaseUri(): string
    {
        return $this->sBaseUri;
    }

    // --------------------------------------------------------------------------

    /**
     * Populates the path property of the request
     *
     * @param string $sPath The path for the request
     *
     * @return $this
     */
    public function path(string $sPath): self
    {
        $this->sPath = $sPath;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->sPath;
    }

    // --------------------------------------------------------------------------

    /**
     * Populates the UserAgent property of the request
     *
     * @param string $sUserAgent The UserAgentfor the request
     *
     * @return $this
     */
    public function userAgent($sUserAgent): self
    {
        return $this->setHeader('User-Agent', $sUserAgent);
    }

    // --------------------------------------------------------------------------

    /**
     * Sets basic auth credentials
     *
     * @param string $sUsername The basic auth username
     * @param string $sPassword The basic auth password
     *
     * @return $this
     */
    public function auth(string $sUsername, string $sPassword): self
    {
        $this->sAuthUsername = $sUsername;
        $this->sAuthPassword = $sPassword;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Configures and executes the HTTP request
     *
     * @return HttpResponse
     * @throws FactoryException
     */
    public function execute(): HttpResponse
    {
        $aClientConfig   = [
            'base_uri'        => $this->sBaseUri,
            'verify'          => !(Environment::is(Environment::ENV_DEV) || Environment::is(Environment::ENV_TEST)),
            'allow_redirects' => Environment::not(Environment::ENV_TEST),
            'http_errors'     => false,
        ];
        $aRequestOptions = [
            'headers' => $this->aHeaders,
        ];

        if (!empty($this->sAuthUsername)) {
            $aRequestOptions['auth'] = [
                $this->sAuthUsername,
                $this->sAuthPassword,
            ];
        }

        $this->compile($aClientConfig, $aRequestOptions);

        $oClient   = Factory::factory('HttpClient', '', $aClientConfig);
        $oResponse = $oClient->request(static::HTTP_METHOD, $this->sPath, $aRequestOptions);

        return Factory::factory('HttpResponse', '', $oResponse, $this);
    }

    // --------------------------------------------------------------------------

    /**
     * Compile the request
     *
     * @param array $aClientConfig   The config array for the HTTP Client
     * @param array $aRequestOptions The options for the request
     */
    abstract protected function compile(array &$aClientConfig, array &$aRequestOptions): void;
}
