<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationStructureChange extends Model
{
    protected $fillable = ['organization_structure_id','user_id','diff','reason'];
    protected $casts = ['diff' => 'array'];

    public function structure(){ return $this->belongsTo(OrganizationStructure::class); }
    public function user()     { return $this->belongsTo(User::class); }
}
