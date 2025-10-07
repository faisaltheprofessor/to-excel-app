<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationStructureAudit extends Model
{
    protected $fillable = [
        'organization_structure_id',
        'user_id',
        'action',
        'path',
        'field',
        'before',
        'after',
    ];

    protected $casts = [
        'before' => 'array',
        'after'  => 'array',
    ];

    public function tree()
    {
        return $this->belongsTo(OrganizationStructure::class, 'organization_structure_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
