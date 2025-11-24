<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationStructure extends Model
{
    protected $casts = [
        'data' => 'array',
    ];

    // --- Node count accessor ---
    public function getNodeCountAttribute(): int
    {
        return $this->countNodes($this->data ?? []);
    }

    protected function countNodes(array $nodes): int
    {
        $count = 0;

        foreach ($nodes as $node) {
            $count++;

            if (!empty($node['children']) && is_array($node['children'])) {
                $count += $this->countNodes($node['children']);
            }
        }

        return $count;
    }

    // --- Level count accessor (tree depth) ---
    public function getLevelCountAttribute(): int
    {
        $data = $this->data ?? [];

        if (!is_array($data) || empty($data)) {
            return 0;
        }

        // root level is 1
        return $this->countLevels($data, 1);
    }

    protected function countLevels(array $nodes, int $currentLevel): int
    {
        // if there are nodes, minimum depth is currentLevel
        $max = $nodes ? $currentLevel : 0;

        foreach ($nodes as $node) {
            if (!empty($node['children']) && is_array($node['children'])) {
                $childDepth = $this->countLevels($node['children'], $currentLevel + 1);
                if ($childDepth > $max) {
                    $max = $childDepth;
                }
            }
        }

        return $max;
    }
}
