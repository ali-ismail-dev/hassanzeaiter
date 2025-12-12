<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

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
     * Relationships
     */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function categoryField(): BelongsTo
    {
        return $this->belongsTo(CategoryField::class);
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(CategoryFieldOption::class, 'category_field_option_id');
    }

    /**
     * Return the "value" in a normalized form depending on the field type.
     * - For select/radio returns option info array if loaded, otherwise id.
     * - For checkbox returns array.
     * - For numeric/date/boolean returns appropriate PHP types.
     */
    public function getValue()
    {
        // Defensive: if categoryField not loaded, try to load it (avoid fatal)
        if (!isset($this->categoryField) && $this->relationLoaded('categoryField') === false) {
            $this->load('categoryField');
        }

        $type = $this->categoryField->field_type ?? null;

        switch ($type) {
            case 'text':
            case 'textarea':
            case 'email':
            case 'url':
                return $this->value_text;

            case 'number':
                return $this->value_integer;

            case 'decimal':
            case 'price':
                // return float (casts to string sometimes) — cast explicitly
                return $this->value_decimal !== null ? (float) $this->value_decimal : null;

            case 'date':
                // return ISO string or Carbon depending on usage; here return string for API
                return $this->value_date ? Carbon::parse($this->value_date)->toDateString() : null;

            case 'boolean':
                return $this->value_boolean;

            case 'checkbox':
                return $this->value_json ?? [];

            case 'select':
            case 'radio':
                // If the selectedOption relationship is loaded, return structured info
                if ($this->relationLoaded('selectedOption') || $this->selectedOption) {
                    $opt = $this->selectedOption;
                    return $opt ? [
                        'id' => $opt->id,
                        'value' => $opt->value ?? null,
                        'label' => $opt->label ?? null,
                    ] : null;
                }

                // fallback: return stored option id
                return $this->category_field_option_id ?? null;

            default:
                return $this->value_text;
        }
    }

    /**
     * Set the value based on the category field's type.
     * IMPORTANT: the categoryField relation MUST be set (or loaded) before calling setValue().
     */
    public function setValue($value): void
    {
        if (!isset($this->categoryField)) {
            throw new \RuntimeException('categoryField relation must be set/loaded before calling setValue().');
        }

        // Clear all typed columns first to avoid stale values
        $this->value_text = null;
        $this->value_integer = null;
        $this->value_decimal = null;
        $this->value_date = null;
        $this->value_boolean = null;
        $this->value_json = null;
        $this->category_field_option_id = null;

        $type = $this->categoryField->field_type;

        switch ($type) {
            case 'text':
            case 'textarea':
            case 'email':
            case 'url':
                $this->value_text = $value !== null ? (string) $value : null;
                break;

            case 'number':
                // Accept int-like or numeric string; preserve nulls
                $this->value_integer = $value !== null ? (int) $value : null;
                break;

            case 'decimal':
            case 'price':
                $this->value_decimal = $value !== null ? (float) $value : null;
                break;

            case 'date':
                // Accept string or Date instance; store as Y-m-d
                if ($value === null) {
                    $this->value_date = null;
                } else {
                    $this->value_date = Carbon::parse($value)->toDateString();
                }
                break;

            case 'boolean':
                $this->value_boolean = $value !== null ? (bool) $value : null;
                break;

            case 'checkbox':
                // Expect array of option ids or single value
                if ($value === null) {
                    $this->value_json = [];
                } elseif (is_array($value)) {
                    $this->value_json = array_values($value);
                } else {
                    $this->value_json = [$value];
                }
                break;

            case 'select':
            case 'radio':
                // Expect single option id (int or numeric string)
                $this->category_field_option_id = $value !== null ? (int) $value : null;
                break;

            default:
                // Fallback to text
                $this->value_text = $value !== null ? (string) $value : null;
        }
    }
}
