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
     * @return ApiClient
     */
    public function __construct($user)
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
        $res = $client->post(
            $url,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->user->catlab_access_token,
                ],
                'json' =>$data
            ]
        );

        $data = json_decode($res->getBody(), true);
        if (!$data) {
            throw new \LogicException("Could not decode create order json api request: " . $res->getBody());
        }

        return $data;
    }

    protected function getUrl($path)
    {
        return \Config::get('services.catlab.url') . '/api/1.0/' . $path;
    }
}