<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryField extends Model
{
     use HasFactory;

    protected $fillable = [
        'category_id',
        'external_id',
        'name',
        'label',
        'field_type',
        'is_required',
        'is_searchable',
        'order',
        'validation_rules',
        'placeholder',
        'help_text',
        'metadata',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_searchable' => 'boolean',
        'order' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the category this field belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get all options for this field (for select/radio/checkbox).
     */
    public function options(): HasMany
    {
        return $this->hasMany(CategoryFieldOption::class)->orderBy('order');
    }

    /**
     * Get all ad field values using this field.
     */
    public function adFieldValues(): HasMany
    {
        return $this->hasMany(AdFieldValue::class);
    }

    /**
     * Check if this field has options (select, radio, checkbox).
     */
    public function hasOptions(): bool
    {
        return in_array($this->field_type, ['select', 'radio', 'checkbox']);
    }

    /**
     * Check if this field accepts multiple values.
     */
    public function isMultiple(): bool
    {
        return $this->field_type === 'checkbox';
    }

    /**
     * Get the Laravel validation rules for this field.
     */
    public function getValidationRules(): array
    {
        $rules = [];

        // Base required rule
        if ($this->is_required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        // Type-specific rules
        switch ($this->field_type) {
            case 'text':
            case 'textarea':
                $rules[] = 'string';
                break;
            case 'number':
                $rules[] = 'integer';
                break;
            case 'email':
                $rules[] = 'email';
                break;
            case 'url':
                $rules[] = 'url';
                break;
            case 'date':
                $rules[] = 'date';
                break;
            case 'select':
            case 'radio':
                $rules[] = 'exists:category_field_options,id,category_field_id,' . $this->id;
                break;
            case 'checkbox':
                $rules[] = 'array';
                break;
        }

        // Add custom validation rules if defined
        if ($this->validation_rules) {
            $customRules = explode('|', $this->validation_rules);
            $rules = array_merge($rules, $customRules);
        }

        return $rules;
    }

    /**
     * Scope to get fields for a specific category.
     */
    public function scopeForCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId)->orderBy('order');
    }
}
