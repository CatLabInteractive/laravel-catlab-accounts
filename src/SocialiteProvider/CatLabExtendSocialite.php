<?php

namespace CatLab\Accounts\Client\SocialiteProvider;

use SocialiteProviders\Manager\SocialiteWasCalled;

class CatLabExtendSocialite
{
    /**
     * Execute the provider.
     */
    public function handle(SocialiteWasCalled $socialiteWasCalled)
    {
        $socialiteWasCalled->extendSocialite('catlab', __NAMESPACE__.'\Provider');
    }
}
