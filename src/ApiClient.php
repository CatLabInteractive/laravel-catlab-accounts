<?php

namespace CatLab\Accounts\Client;

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
     * @param null $user
     */
    public function __construct($user = null)
    {
        $this->user = $user;
    }

    /**
     * @param $data
     * @return mixed
     */
    public function createOrder($data)
    {
        $client = new \GuzzleHttp\Client();

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
        $client = new \GuzzleHttp\Client();

        $url = $this->getUrl('orders/' . $id);

        if ($expanded) {
            $url .= '?expanded=1';
        }

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
        $client = new \GuzzleHttp\Client();

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

    protected function getUrl($path)
    {
        return \Config::get('services.catlab.url') . '/api/1.0/' . $path;
    }
}
