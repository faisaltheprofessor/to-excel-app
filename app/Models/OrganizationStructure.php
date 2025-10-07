<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OrganizationStructure extends Model
{
    use SoftDeletes;

    protected $table = 'organization_structures';

    protected $fillable = [
        'title',
        'tree_json',          // JSON column (array cast)
        'created_by',
        'version_group_id',
        'version',
        'status',
        'locked_by',
        'locked_at',
        'closed_at',
        'branched_from_id',
    ];

    protected $attributes = [
        'tree_json' => '[]',   // safe default at DB write time
        'status'    => 'draft',
    ];

    protected $casts = [
        'tree_json' => 'array',     // read as array automatically
        'locked_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (!$model->version_group_id) {
                $model->version_group_id = (string) Str::uuid();
            }
            if (!$model->version) {
                $model->version = 1;
            }
            if (!$model->status) {
                $model->status = 'draft';
            }
        });
    }

    /**
     * Coerce writes to tree_json into an array (defensive against strings).
     * Works together with the 'array' cast above for reads.
     */
    protected function treeJson(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if (is_array($value)) {
                    return $value;
                }
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    return is_array($decoded) ? $decoded : [];
                }
                return [];
            }
        );
    }

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function locker()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function audits()
    {
        return $this->hasMany(OrganizationStructureAudit::class, 'organization_structure_id');
    }

    // Helpers
    public function isLocked(): bool
    {
        if (!$this->locked_by || !$this->locked_at) return false;

        $ttl = config('tree.lock_ttl_minutes');
        return now()->diffInMinutes($this->locked_at) < $ttl;
    }

    public function lockExpired(): bool
    {
        if (!$this->locked_at) return true;
        $ttl = config('tree.lock_ttl_minutes');
        return now()->diffInMinutes($this->locked_at) >= $ttl;
    }

    public function canBeEditedBy(?int $userId): bool
    {
        if ($this->status === 'abgeschlossen') return false;
        if (!$this->isLocked()) return true;
        return (int)$this->locked_by === (int)$userId;
    }

    public function finalize(): void
    {
        $this->status = 'abgeschlossen';
        $this->closed_at = now();
        $this->locked_by = null;
        $this->locked_at = null;
        $this->save();
    }

    public function acquireLock(int $userId): bool
    {
        if ($this->status === 'abgeschlossen') return false;

        if (!$this->isLocked() || $this->lockExpired() || (int)$this->locked_by === $userId) {
            $this->locked_by = $userId;
            $this->locked_at = now();
            $this->status = 'in_progress';
            $this->save();

            $this->audits()->create([
                'user_id' => $userId,
                'action'  => 'locked',
            ]);

            return true;
        }
        return false;
    }

    public function releaseLock(?int $userId = null): void
    {
        $this->locked_by = null;
        $this->locked_at = null;
        $this->save();

        $this->audits()->create([
            'user_id' => $userId,
            'action'  => 'unlocked',
        ]);
    }

    public function cloneToNewVersion(int $userId): self
    {
        $next = $this->replicate(['status', 'locked_by', 'locked_at', 'closed_at']);
        $next->version = $this->version + 1;
        $next->status = 'draft';
        $next->locked_by = $userId;
        $next->locked_at = now();
        $next->branched_from_id = $this->id;
        $next->save();

        $next->audits()->create([
            'user_id' => $userId,
            'action'  => 'version_created',
            'before'  => ['from_version' => $this->version],
            'after'   => ['to_version' => $next->version],
        ]);

        return $next;
    }

    // Scopes
    public function scopeGroup($query, string $groupId)
    {
        return $query->where('version_group_id', $groupId)->orderBy('version');
    }
}
