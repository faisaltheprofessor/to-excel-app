<?php

namespace App\Ldap\Rules;

use Illuminate\Database\Eloquent\Model as Eloquent;
use LdapRecord\Laravel\Auth\Rule;
use LdapRecord\Models\Model as LdapRecord;

class OnlyFachadmins implements Rule
{
    /**
     * Check if the rule passes validation.
     */
    public function passes(LdapRecord $user, ?Eloquent $model = null): bool
    {
        $allowedGroupDn = config('users.LDAP.group');

        if (method_exists($user, 'inGroup')) {
            // true = recursive (nested groups)
            return $user->inGroup($allowedGroupDn, true);
        }

        // Fallback for directories that expose `groupMembership` (eDirectory):
        $memberships = array_map('strtolower', (array) $user->getAttribute('groupMembership', []));
        return in_array(strtolower($allowedGroupDn), $memberships, true);
    }
}
