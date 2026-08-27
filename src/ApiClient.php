<?php

namespace CatLab\Accounts\Client;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;

/**
 * Class ApiClient
 * @package CatLab\Accounts\Client
 */
class ApiClient
{
    /**
     * @var User
     */
    private $user;

    /** Seconds to establish the connection. */
    const CONNECT_TIMEOUT = 5;

    /** Seconds for the whole request; mail delivery through accounts can take a few. */
    const REQUEST_TIMEOUT = 30;

    /**
     * @var ClientInterface|null
     */
    private $httpClient;

    /**
     * @param null $user
     */
    public function __construct($user = null)
    {
        $this->user = $user;
    }

    /**
     * Override the Guzzle client (tests, custom middleware).
     * @param ClientInterface $httpClient
     * @return $this
     */
    public function setHttpClient(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * @return ClientInterface
     */
    protected function getHttpClient()
    {
        if (!$this->httpClient) {
            // Never wait forever on accounts: a hung request would otherwise
            // block the caller until PHP's max_execution_time.
            $this->httpClient = new \GuzzleHttp\Client([
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'timeout' => self::REQUEST_TIMEOUT
            ]);
        }
        return $this->httpClient;
    }

    /**
     * Create an order for the user.
     *
     * Authenticates as the product (client credentials, see
     * getProductAuthorizationHeaders()); the user is identified by the
     * numeric accounts id in the url. Requires a user with a catlab_id.
     *
     * @param array $data items, callback, partner, maxTransactionFee
     * @return mixed
     * @throws \LogicException when no user (or no catlab_id) is set
     */
    public function createOrder($data)
    {
        $client = $this->getHttpClient();

        $url = $this->getUrl('users/' . $this->requireUserId() . '/orders');

        $headers = $this->getProductAuthorizationHeaders();

        $res = $client->post(
            $url,
            [
                'headers' => $headers,
                'json' =>$data
            ]
        );

        $data = json_decode($res->getBody(), true);
        if (!$data) {
            throw new \LogicException("Could not decode create order json api request: " . $res->getBody());
        }

        return $data;
    }

    /**
     * @param $id
     * @param bool $expanded
     * @return mixed
     */
    public function getOrder($id, $expanded = false)
    {
        $client = $this->getHttpClient();

        $url = $this->getUrl('orders/' . $id);

        if ($expanded) {
            $url .= '?expanded=1';
        }

        // GET orders/{id} is readable by the product that created the order
        // (client credentials). The product always owns its orders and its
        // credentials never expire, so the order sync keeps working after
        // the user's access token has expired.
        $headers = $this->getProductAuthorizationHeaders();

        $res = $client->get(
            $url,
            [
                'headers' => $headers
            ]
        );

        $data = json_decode($res->getBody(), true);
        if (!$data) {
            throw new \LogicException("Could not decode create order json api request: " . $res->getBody());
        }

        return $data;
    }

    /**
     * Send an email to the user
     * (or to a target on behalf of the user; reply-to is the user's address)
     *
     * Authenticates as the product (client credentials, see
     * getProductAuthorizationHeaders()); the user is identified by the
     * numeric accounts id in the url. Requires a user with a catlab_id.
     *
     * @param $subject
     * @param $body
     * @param null $target
     * @return mixed
     * @throws \LogicException when no user (or no catlab_id) is set
     */
    public function sendEmail($subject, $body, $target = null)
    {
        $client = $this->getHttpClient();

        $url = $this->getUrl('users/' . $this->requireUserId() . '/mail');

        $headers = $this->getProductAuthorizationHeaders();

        $res = $client->post(
            $url,
            [
                'headers' => $headers,
                'form_params' => [
                    'subject' => $subject,
                    'body' => $body,
                    'target' => $target
                ]
            ]
        );

        $data = json_decode($res->getBody(), true);
        if (!$data) {
            throw new \LogicException("Could not decode create order json api request: " . $res->getBody());
        }

        return $data;
    }

    /**
     * @return string
     */
    public function getJsConnectToken()
    {
        $client = $this->getHttpClient();

        $url = $this->getUrl('users/me/jsconnect');

        $headers = $this->getUserAuthorizationHeaders();

        $res = $client->get(
            $url,
            [
                'headers' => $headers
            ]
        );

        $data = json_decode($res->getBody(), true);
        if (!$data) {
            throw new \LogicException("Could not decode create order json api request: " . $res->getBody());
        }

        return $data['jsConnect'];
    }

    /**
     * Generate an url to a page on the catlab accounts portal.
     * @param $path
     * @param array $parameters
     * @return string
     */
    public function getAccountLink($path, $parameters = [])
    {
        $url = \Config::get('services.catlab.url') . $path;

        $parameters['authcode'] = $this->user->catlab_access_token;

        return $url .= '?' . http_build_query($parameters);
    }

    /**
     * Authorization header for calls made on behalf of the user (bearer token).
     * @return string[]
     */
    protected function getUserAuthorizationHeaders()
    {
        $headers = [];
        if ($this->user) {
            $headers['Authorization'] = 'Bearer ' . $this->user->catlab_access_token;
        }
        return $headers;
    }

    /**
     * Authorization header for calls made by the product itself: HTTP Basic
     * with services.catlab.client_id / client_secret (RFC 6749 §2.3.1).
     *
     * CATLAB_CLIENT_ID and CATLAB_CLIENT_SECRET must be set (see
     * config/services.php): accounts rejects a user token on the product
     * routes (createOrder, sendEmail, getOrder), so there is no bearer
     * fallback and a missing configuration is a deployment error.
     *
     * @return string[]
     * @throws \LogicException when the client credentials are not configured
     */
    protected function getProductAuthorizationHeaders()
    {
        $clientId = \Config::get('services.catlab.client_id');
        $clientSecret = \Config::get('services.catlab.client_secret');

        if (!$clientId || !$clientSecret) {
            throw new \LogicException(
                'services.catlab.client_id / client_secret are not configured; ' .
                'set CATLAB_CLIENT_ID and CATLAB_CLIENT_SECRET.'
            );
        }

        return [
            'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret)
        ];
    }

    /**
     * Numeric accounts id of the current user, for routes that take the user
     * id in the url. Product-authenticated routes do not accept "me", so a
     * missing user is a programming error rather than something to send along.
     * @return int
     * @throws \LogicException
     */
    protected function requireUserId()
    {
        if (!$this->user || !$this->user->catlab_id) {
            throw new \LogicException(
                'ApiClient needs a user with a catlab_id for this call; ' .
                'construct it with the user (new ApiClient($user)).'
            );
        }
        return $this->user->catlab_id;
    }

    protected function getUrl($path)
    {
        return \Config::get('services.catlab.url') . '/api/1.0/' . $path;
    }
}
