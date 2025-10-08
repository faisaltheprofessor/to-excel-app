<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationStructureVersion extends Model
{
    protected $fillable = ['organization_structure_id','version','title','data','created_by'];
    protected $casts = ['data' => 'array'];

    public function structure(){ return $this->belongsTo(OrganizationStructure::class, 'organization_structure_id'); }
    public function author()   { return $this->belongsTo(User::class, 'created_by'); }
}
