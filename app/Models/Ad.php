<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ad extends Model
{
   use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'description',
        'price',
        'status',
        'published_at',
        'expires_at',
        'views_count',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'views_count' => 'integer',
    ];

    /**
     * Get the user who posted this ad.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the category of this ad.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get all field values for this ad.
     */
    public function fieldValues(): HasMany
    {
        return $this->hasMany(AdFieldValue::class);
    }

    /**
     * Get a specific field value by field name.
     */
    public function getFieldValue(string $fieldName)
    {
        $fieldValue = $this->fieldValues()
            ->whereHas('categoryField', function ($query) use ($fieldName) {
                $query->where('name', $fieldName);
            })
            ->with(['categoryField', 'selectedOption'])
            ->first();

        return $fieldValue ? $fieldValue->getValue() : null;
    }

    /**
     * Get all dynamic fields as key-value pairs.
     */
    public function getDynamicFieldsAttribute(): array
    {
        $fields = [];
        
        foreach ($this->fieldValues as $fieldValue) {
            $fields[$fieldValue->categoryField->name] = [
                'label' => $fieldValue->categoryField->label,
                'value' => $fieldValue->getValue(),
                'type' => $fieldValue->categoryField->field_type,
            ];
        }

        return $fields;
    }

    /**
     * Scope to get only active ads.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to get ads by category.
     */
    public function scopeInCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope to get user's ads.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Increment views count.
     */
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }
}

