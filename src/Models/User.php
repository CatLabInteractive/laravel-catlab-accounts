<?php

namespace CatLab\Accounts\Client\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

/**
 * Class User
 * @package CatLab\Accounts\Client
 */
class User extends Model implements
    AuthenticatableContract,
    AuthorizableContract
{
    use Authenticatable, Authorizable;

    /**
     * @param $socialiteUser
     * @return self
     */
    public static function fromSocialite(\Laravel\Socialite\Two\User $socialiteUser)
    {
        // Look for email address
        $user = self::findFromCatLabId($socialiteUser->getId());

        if (!$user) {
            $user = new static();
            $user->catlab_id = $socialiteUser->getId();
        }

        $user->username = $socialiteUser->getNickname();
        $user->email = $socialiteUser->getEmail();
        $user->catlab_access_token = $socialiteUser->token;

        $user->save();

        return $user;
    }

    /**
     * @param $id
     * @return null|static
     * @internal param int $Id
     */
    public static function findFromCatLabId($id)
    {
        $user = self::where('catlab_id', '=', $id)->get();
        if ($user->count() > 0) {
            return $user->first();
        }
        return null;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'nickname'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];
}