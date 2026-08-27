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
            $this->httpClient = new \GuzzleHttp\Client();
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
