<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryFieldOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_field_id',
        'external_id',
        'value',
        'label',
        'order',
        'is_default',
        'metadata',
    ];

    protected $casts = [
        'order' => 'integer',
        'is_default' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the category field this option belongs to.
     */
    public function categoryField(): BelongsTo
    {
        return $this->belongsTo(CategoryField::class);
    }

    /**
     * Get all ad field values using this option.
     */
    public function adFieldValues(): HasMany
    {
        return $this->hasMany(AdFieldValue::class);
    }
}
