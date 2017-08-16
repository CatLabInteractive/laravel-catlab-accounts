<?php

namespace CatLab\Accounts\Client\Controllers;

use CatLab\Accounts\Client\Models\User;
use Illuminate\Foundation\Auth\RedirectsUsers;
use Laravel\Socialite\Two\InvalidStateException;
use Route;
use Socialite;


/**
 * Class LoginController
 * @package CatLab\Accounts\Client\Controllers
 */
class LoginController
{
    use RedirectsUsers;

    /**
     * Set the routes for the login controller.
     */
    public static function setRoutes()
    {
        Route::get('/login', '\CatLab\Accounts\Client\Controllers\LoginController@login')->name('login');
        Route::get('/login/callback', '\CatLab\Accounts\Client\Controllers\LoginController@postLogin');

        Route::post('/logout', '\CatLab\Accounts\Client\Controllers\LoginController@logout')->name('logout');
    }

    /**
     * Redirect user to catlab-accounts
     */
    public function login()
    {
        return Socialite::driver('catlab')->redirect();
    }

    /**
     * After user came back from login
     */
    public function postLogin()
    {
        try {
            $socialiteUser = Socialite::driver('catlab')->user();

            $user = User::fromSocialite($socialiteUser);
            \Auth::login($user);

            return redirect()->intended($this->redirectPath());
        } catch (InvalidStateException $e) {
            return '<p>Invalid state.</p>';
        }
    }

    /**
     * Logout user
     */
    public function logout()
    {
        \Auth::guard($this->getGuard())->logout();

        return redirect(property_exists($this, 'redirectAfterLogout') ? $this->redirectAfterLogout : '/');
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return string|null
     */
    protected function getGuard()
    {
        return property_exists($this, 'guard') ? $this->guard : null;
    }
}