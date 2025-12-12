<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'ad_id',
        'category_field_id',
        'value_text',
        'value_integer',
        'value_decimal',
        'value_date',
        'value_boolean',
        'value_json',
        'category_field_option_id',
    ];

    protected $casts = [
        'value_integer' => 'integer',
        'value_decimal' => 'decimal:2',
        'value_date' => 'date',
        'value_boolean' => 'boolean',
        'value_json' => 'array',
    ];

    /**
     * Get the ad this value belongs to.
     */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    /**
     * Get the category field definition.
     */
    public function categoryField(): BelongsTo
    {
        return $this->belongsTo(CategoryField::class);
    }

    /**
     * Get the selected option (for select/radio fields).
     */
    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(CategoryFieldOption::class, 'category_field_option_id');
    }

    /**
     * Get the actual value based on field type.
     */
    public function getValue()
    {
        switch ($this->categoryField->field_type) {
            case 'text':
            case 'textarea':
            case 'email':
            case 'url':
                return $this->value_text;
            
            case 'number':
                return $this->value_integer;
            
            case 'date':
                return $this->value_date;
            
            case 'checkbox':
                return $this->value_json;
            
            case 'select':
            case 'radio':
                return $this->selectedOption ? [
                    'id' => $this->selectedOption->id,
                    'value' => $this->selectedOption->value,
                    'label' => $this->selectedOption->label,
                ] : null;
            
            default:
                return $this->value_text;
        }
    }

    /**
     * Set the value based on field type.
     */
    public function setValue($value): void
{
    if (!isset($this->categoryField)) {
        throw new \Exception('categoryField relation must be loaded before calling setValue.');
    }

    switch ($this->categoryField->field_type) {
        case 'text':
        case 'textarea':
        case 'email':
        case 'url':
            $this->value_text = $value;
            break;
        case 'number':
            $this->value_integer = (int) $value;
            break;
        case 'decimal':
        case 'price':
            $this->value_decimal = (float) $value;
            break;
        case 'date':
            $this->value_date = $value;
            break;
        case 'checkbox':
            // store as JSON array
            $this->value_json = json_encode($value);
            break;
        case 'radio':
        case 'select':
            $this->category_field_option_id = $value;
            break;
        case 'boolean':
            $this->value_boolean = (bool) $value;
            break;
        default:
            $this->value_text = $value;
    }
}

}
