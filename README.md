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

Configuration
-------------
`config/services.php` reads these environment variables:

- `CATLAB_API`: base url of the accounts server (default `https://accounts.catlab.eu/`).
- `CATLAB_CLIENT_ID` / `CATLAB_CLIENT_SECRET`: the product's OAuth2 client
  credentials. **Required.** Besides the login flow they authenticate the
  product-level API calls in `ApiClient` (`createOrder`, `sendEmail`,
  `getOrder`), which use HTTP Basic `client_id:client_secret` instead of the
  user's access token. Accounts does not accept a user token on those
  routes, so `ApiClient` throws a `LogicException` when they are missing.
