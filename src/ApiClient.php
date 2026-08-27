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
     * @param $data
     * @return mixed
     */
    public function createOrder($data)
    {
        $client = $this->getHttpClient();

        $url = $this->getUrl('users/' . $this->user->catlab_id . '/orders');

        $headers = [];
        if ($this->user) {
            $headers['Authorization'] = 'Bearer ' . $this->user->catlab_access_token;
        }

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
        // (client credentials) or by a member of the order's profile (bearer).
        // The product always owns its orders and its credentials never
        // expire, so prefer them: the order sync must keep working after the
        // user's access token has expired.
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
     * (or to a target on behalf of the user)
     * @param $subject
     * @param $body
     * @param null $target
     * @return mixed
     */
    public function sendEmail($subject, $body, $target = null)
    {
        $client = $this->getHttpClient();

        $url = $this->getUrl('users/me/mail');

        $headers = [];
        if ($this->user) {
            $headers['Authorization'] = 'Bearer ' . $this->user->catlab_access_token;
        }

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

        $headers = [];
        if ($this->user) {
            $headers['Authorization'] = 'Bearer ' . $this->user->catlab_access_token;
        }

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
     *
     * The link logs the user in on accounts through an `authcode` parameter.
     * This mints a single-use login token (valid for 15 minutes) for that,
     * so the url never carries the user's long-lived access token through
     * browser history, referrers and logs. When the token cannot be minted
     * this throws rather than falling back to the access token.
     *
     * @param $path
     * @param array $parameters
     * @return string
     * @throws \RuntimeException when no login token could be minted
     */
    public function getAccountLink($path, $parameters = [])
    {
        $url = \Config::get('services.catlab.url') . $path;

        $parameters['authcode'] = $this->createLoginToken();

        return $url .= '?' . http_build_query($parameters);
    }

    /**
     * Mint a single-use login token for the current user.
     *
     * POST users/me/login-token with the user's bearer token; accounts
     * answers 200 {"token": "<opaque>", "expires": <unix timestamp>}. The
     * token is single-use and expires 15 minutes after minting.
     *
     * @return string
     * @throws \RuntimeException on a non-200 response, a transport error or
     *   an unexpected body; the live access token must never end up in a url,
     *   so there is deliberately no fallback.
     */
    protected function createLoginToken()
    {
        if (!$this->user || !$this->user->catlab_access_token) {
            throw new \RuntimeException(
                'ApiClient: cannot mint a login token without a user with a catlab_access_token.'
            );
        }

        try {
            $res = $this->getHttpClient()->post(
                $this->getUrl('users/me/login-token'),
                [
                    'headers' => $this->getUserAuthorizationHeaders(),
                    'http_errors' => false
                ]
            );
        } catch (\Exception $e) {
            throw new \RuntimeException(
                'ApiClient: could not mint a login token on accounts: ' . $e->getMessage(),
                0,
                $e
            );
        }

        if ($res->getStatusCode() !== 200) {
            throw new \RuntimeException(
                'ApiClient: accounts refused to mint a login token (HTTP ' . $res->getStatusCode() . ').'
            );
        }

        $data = json_decode($res->getBody(), true);
        if (!is_array($data) || empty($data['token']) || !is_string($data['token'])) {
            throw new \RuntimeException(
                'ApiClient: unexpected login token response from accounts: ' . $res->getBody()
            );
        }

        return $data['token'];
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
     * Falls back to the user's bearer token when no client credentials are
     * configured.
     * @return string[]
     */
    protected function getProductAuthorizationHeaders()
    {
        $clientId = \Config::get('services.catlab.client_id');
        $clientSecret = \Config::get('services.catlab.client_secret');

        if ($clientId && $clientSecret) {
            return [
                'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret)
            ];
        }

        return $this->getUserAuthorizationHeaders();
    }

    protected function getUrl($path)
    {
        return \Config::get('services.catlab.url') . '/api/1.0/' . $path;
    }
}
