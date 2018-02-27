<?php

namespace CatLab\Accounts\Client\SocialiteProvider;

use Laravel\Socialite\Two\ProviderInterface;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider implements ProviderInterface
{
    /**
     * Unique Provider Identifier.
     */
    const IDENTIFIER = 'CATLAB';

    /**
     * {@inheritdoc}
     */
    protected $scopes = [''];

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        $url = $this->buildAuthUrlFromBase('', $state);
        $url = mb_substr($url, 1);

        $authorizeUrl = \Config::get('services.catlab.authorizePath', '/oauth2/authorize?reset=1');

        $url = $this->getUrl() . $authorizeUrl . $url;

        return $url;
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return $this->getUrl() . '/oauth2/token';
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get(
            $this->getUrl() . '/api/1.0/users/me',
            [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                ],
            ]
        );

        return json_decode($response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id'       => $user['id'],
            'nickname' => $user['username'],
            'email'    => $user['email']
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code'
        ]);
    }

    protected function getUrl()
    {
        return \Config::get('services.catlab.url');
    }
}
