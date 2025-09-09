<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class OIDCSetting extends Model
{
    protected $table = 'oidc_settings';

    protected $fillable = [
        'issuer_url',
        'authorize_url',
        'userinfo_url',
        'jwks_url',
        'logout_url',
        'allowed_mobile_redirect_uris',
        'button_text',
        'button_icon',
        'scopes',
        'auto_launch',
        'auto_register',
        'group_claim',
        'permission_claim',
        'ldap_enabled',
        'client_id',
        'client_secret',
        'redirect_path',
    ];

    protected $casts = [
        'allowed_mobile_redirect_uris' => 'array',
        'scopes'                       => 'array',
        'auto_launch'                  => 'boolean',
        'auto_register'                => 'boolean',
        'ldap_enabled'                 => 'boolean',
        'client_id'                   => 'string',
        'client_secret'               => 'string',
        'redirect_path'              => 'string',
    ];

    /**
     * Retrieve the singleton OIDC setting.
     */
    public static function get()
    {
        return Cache::rememberForever('oidc_setting', function () {
            return self::firstOrCreate([]);
        });
    }

    /**
     * Update the settings with an array of key/value pairs.
     */
    public function updateFromArray(array $data)
    {
        foreach ($this->fillable as $key) {
            if (array_key_exists($key, $data)) {
                $this->$key = $data[$key];
            }
        }
        $this->save();
    }

    /**
     * Return the configuration array for use in views or services.
     */
    public function toConfigArray()
    {
        return [
            'issuer_url'            => $this->issuer_url,
            'authorize_url'         => $this->authorize_url,
            'userinfo_url'          => $this->userinfo_url,
            'jwks_url'              => $this->jwks_url,
            'logout_url'            => $this->logout_url,
            'allowed_mobile_redirect_uris' => $this->allowed_mobile_redirect_uris,
            'button_text'           => $this->button_text,
            'button_icon'           => $this->button_icon,
            'scopes'                => $this->scopes,
            'auto_launch'           => $this->auto_launch,
            'auto_register'         => $this->auto_register,
            'group_claim'           => $this->group_claim,
            'permission_claim'      => $this->permission_claim,
            'ldap_enabled'          => $this->ldap_enabled,
            'client_id'             => $this->client_id,
            'client_secret'         => $this->client_secret,
            'redirect_path'        => $this->redirect_path,
        ];
    }
}
