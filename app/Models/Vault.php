<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vault extends Model
{
    use HasFactory;

    protected $table = 'vaults';

    protected $fillable = [
        'name',
        'original_name',
        'file_path',
        'file_url',
        'token',
        'type',
        'parent_id',
        'is_folder',
        'is_hidden',
        'size',
    ];

    protected $casts = [
        'is_folder' => 'boolean',
        'is_hidden' => 'boolean',
        'size' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeFolders($query)
    {
        return $query->where('is_folder', true);
    }

    public function scopeFiles($query)
    {
        return $query->where('is_folder', false);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false);
    }

    public function scopeInFolder($query, $parentId = null)
    {
        return $query->where('parent_id', $parentId);
    }

    public function getFormattedSizeAttribute()
    {
        $bytes = $this->size;
        if (!$bytes) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    public function getBreadcrumbAttribute()
    {
        $crumbs = [];
        $current = $this;
        while ($current) {
            array_unshift($crumbs, ['id' => $current->id, 'name' => $current->original_name ?? $current->name]);
            $current = $current->parent;
        }
        return $crumbs;
    }
}
