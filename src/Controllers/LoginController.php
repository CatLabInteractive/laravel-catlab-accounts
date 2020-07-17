<?php

namespace CatLab\Accounts\Client\Controllers;

use Illuminate\Foundation\Auth\RedirectsUsers;
use Illuminate\Support\Str;
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
        $return = request()->get('return');
        if ($return && !Str::startsWith(mb_strtolower($return), 'http')) {
            session()->put('postLoginDirect', request()->get('return'));
        }

        return Socialite::driver('catlab')->redirect();
    }

    /**
     * @return mixed|string
     */
    public function redirectTo()
    {
        if (session()->get('postLoginDirect')) {
            $path = session()->get('postLoginDirect');
            session()->remove('postLoginDirect');
            return $path;
        }

        return '/';
    }

    /**
     * After user came back from login
     */
    public function postLogin()
    {
        try {

            $socialiteUser = Socialite::driver('catlab')->user();

            $user = $this->getUserFromSocialite($socialiteUser);
            \Auth::login($user);

            return redirect()->intended($this->redirectPath());
        } catch (InvalidStateException $e) {
            return '<p>Invalid state.</p>';
        }
    }

    protected function getUserFromSocialite($socialiteUser)
    {
        // Look for email address
        $user = $this->getUserFromCatLabId($socialiteUser->getId());

        if (!$user) {

            $userClassName = $this->getUserClass();

            $user = new $userClassName;
            $user->catlab_id = $socialiteUser->getId();
        }

        $user->username = $socialiteUser->getNickname();
        $user->name = $socialiteUser->getName() ? $socialiteUser->getName() : $socialiteUser->getNickname();
        $user->email = $socialiteUser->getEmail();
        $user->catlab_access_token = $socialiteUser->token;

        $user->save();

        return $user;
    }

    /**
     * @param $id
     * @return User|null
     */
    protected function getUserFromCatLabId($id)
    {
        $userClassName = $this->getUserClass();
        return call_user_func([ $userClassName, 'query' ])->where('catlab_id', '=', $id)->first();
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

    /**
     * @return string
     */
    protected function getUserClass()
    {
        $classname = config('services.catlab.model');
        if (empty($classname)) {
            $classname = CatLab\Accounts\Client\Models\User::class;
        }

        return $classname;
    }
}
