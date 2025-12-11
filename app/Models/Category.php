<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
      use HasFactory;

    protected $fillable = [
        'external_id',
        'name',
        'slug',
        'description',
        'parent_id',
        'order',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'order' => 'integer',
    ];

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get all child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('order');
    }

    /**
     * Get all fields for this category.
     */
    public function fields(): HasMany
    {
        return $this->hasMany(CategoryField::class)->orderBy('order');
    }

    /**
     * Get all required fields for this category.
     */
    public function requiredFields(): HasMany
    {
        return $this->fields()->where('is_required', true);
    }

    /**
     * Get all ads in this category.
     */
    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    /**
     * Scope to get only root categories.
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')->orderBy('order');
    }

    /**
     * Get category by external ID.
     */
    public function scopeByExternalId($query, string $externalId)
    {
        return $query->where('external_id', $externalId);
    }
}