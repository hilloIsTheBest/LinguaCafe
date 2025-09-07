<?php

namespace App\Services\Auth;

use App\Models\Setting;

class LdapService
{
    protected function get(string $name, $default = null)
    {
        $s = Setting::where('user_id', -1)->where('name', $name)->first();
        return $s ? json_decode($s->value, true) : $default;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->get('ldapEnabled', false);
    }

    /**
     * Attempt LDAP auth. Returns array [email, name] on success; null on failure.
     */
    public function authenticate(string $username, string $password): ?array
    {
        if (!function_exists('ldap_connect')) {
            return null;
        }

        $host = (string) $this->get('ldapHost', '');
        $port = (int) ($this->get('ldapPort', 0) ?: 0);
        if (!$host) {
            return null;
        }

        $conn = @ldap_connect($host, $port ?: null);
        if (!$conn) {
            return null;
        }

        // common options
        @ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        @ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

        if ($this->get('ldapUseStartTls', false)) {
            @ldap_start_tls($conn);
        }

        $bindDn = (string) $this->get('ldapBindDn', '');
        $bindPw = (string) $this->get('ldapBindPassword', '');
        if ($bindDn !== '') {
            if (!@ldap_bind($conn, $bindDn, $bindPw)) {
                @ldap_unbind($conn);
                return null;
            }
        } else {
            if (!@ldap_bind($conn)) {
                @ldap_unbind($conn);
                return null;
            }
        }

        $baseDn = (string) $this->get('ldapBaseDn', '');
        $filterTpl = (string) $this->get('ldapUserFilter', '(uid=%s)');
        $filter = sprintf($filterTpl, $this->escapeFilterValue($username));
        $attrs = [$this->get('ldapEmailAttribute', 'mail'), $this->get('ldapNameAttribute', 'cn')];

        $sr = @ldap_search($conn, $baseDn, $filter, $attrs);
        if (!$sr) {
            @ldap_unbind($conn);
            return null;
        }
        $entries = @ldap_get_entries($conn, $sr);
        if (!$entries || $entries['count'] < 1) {
            @ldap_unbind($conn);
            return null;
        }
        $entry = $entries[0];
        $userDn = $entry['dn'] ?? null;
        if (!$userDn) {
            @ldap_unbind($conn);
            return null;
        }

        // bind as user to verify password
        $ok = @ldap_bind($conn, $userDn, $password);
        if (!$ok) {
            @ldap_unbind($conn);
            return null;
        }

        $emailAttr = $this->get('ldapEmailAttribute', 'mail');
        $nameAttr = $this->get('ldapNameAttribute', 'cn');
        $email = $this->firstAttr($entry, $emailAttr);
        $name = $this->firstAttr($entry, $nameAttr);

        @ldap_unbind($conn);
        if (!$email) {
            return null;
        }

        return [$email, $name ?: $username];
    }

    protected function firstAttr(array $entry, string $attr): ?string
    {
        $attrLower = strtolower($attr);
        if (!isset($entry[$attrLower])) return null;
        $val = $entry[$attrLower];
        if (is_array($val)) {
            if (isset($val['count']) && $val['count'] > 0) {
                return (string) $val[0];
            }
            return (string) ($val[0] ?? '');
        }
        return (string) $val;
    }

    protected function escapeFilterValue(string $value): string
    {
        // RFC 4515 escaping
        return str_replace(['\\', '*', '(', ')', "\0"], ['\\5c', '\\2a', '\\28', '\\29', '\\00'], $value);
    }
}

