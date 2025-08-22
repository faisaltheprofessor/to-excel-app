<?php

namespace App\Ldap;

use LdapRecord\Models\Entry;
use LdapRecord\Query\Model\Builder;

class User extends Entry
{
    protected string $guidKey = 'uid';

    public function getContext(): string
    {
        return substr(preg_replace(['/[a-zA-Z]+=/', '/,/'], ['.'], $this->getDn()), 8);
    }

    public function scopeStartingWithP1(Builder $query): void
    {
        $query->where('uid', 'starts_with', 'p1');
    }
}
