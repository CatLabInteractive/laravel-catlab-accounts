Laravel library to use catlab-accounts
======================================

Installation
------------
- Add to your app config providers:
```
   \CatLab\Accounts\Client\CatLabAccountsServiceProvider::class,
   \SocialiteProviders\Manager\ServiceProvider::class,
```
- Add to aliases:
```
    'Socialite' => Laravel\Socialite\Facades\Socialite::class,
```
- Add to your (web)routes:
```
\CatLab\Accounts\Client\Controllers\LoginController::setRoutes();
```

- Add to EventServiceProvider
```
/**
 * The event listener mappings for the application.
 *
 * @var array
 */
protected $listen = [
    \SocialiteProviders\Manager\SocialiteWasCalled::class => [
        CatLabExtendSocialite::class
    ],
];
```
