<?php

namespace App\Http\Requests;

use App\Models\Ad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdRequest extends FormRequest
{
    private ?Ad $adToUpdate = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $ad = $this->route('ad');
        
        // User can only update their own ads
        return $ad && $this->user()->id === $ad->user_id;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $this->adToUpdate = $this->route('ad');

        $rules = [
            'title' => ['sometimes', 'string', 'min:5', 'max:100'],
            'description' => ['sometimes', 'string', 'min:20', 'max:5000'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'sold', 'expired'])],
        ];

        // If updating fields, validate them dynamically
        if ($this->has('fields') && $this->adToUpdate) {
            $dynamicRules = $this->getDynamicFieldRules();
            $rules = array_merge($rules, $dynamicRules);
        }

        return $rules;
    }

    /**
     * Get dynamic validation rules for the ad's category.
     */
   private function getDynamicFieldRules(): array
{
    $rules = [];

    if (!$this->adToUpdate || !$this->adToUpdate->category) {
        return $rules;
    }

    $category = $this->adToUpdate->category()->with(['fields.options'])->first();

    foreach ($category->fields as $field) {
        $fieldKey = $field->external_id ?: $field->name ?: $field->id;

        // Determine required only if ad doesn't have value for it yet
        $baseKey = "fields.{$fieldKey}";
        $map = [];

        if ($field->is_required && !$this->adToUpdate->fieldValues()->where('category_field_id', $field->id)->exists()) {
            $map[$baseKey] = ['required'];
        } else {
            $map[$baseKey] = ['nullable'];
        }

        // type rules (use numeric for numbers)
        switch ($field->field_type) {
            case 'text':
            case 'textarea':
                $map[$baseKey][] = 'string';
                break;
            case 'number':
                $map[$baseKey][] = 'numeric';
                break;
            case 'email':
                $map[$baseKey][] = 'email';
                break;
            case 'url':
                $map[$baseKey][] = 'url';
                break;
            case 'date':
                $map[$baseKey][] = 'date';
                break;
            case 'select':
            case 'radio':
                $map[$baseKey][] = 'integer';
                $map[$baseKey][] = Rule::exists('category_field_options', 'id')->where('category_field_id', $field->id);
                break;
            case 'checkbox':
                $map[$baseKey] = array_merge($map[$baseKey], ['array']);
                $map["{$baseKey}.*"] = [
                    'integer',
                    Rule::exists('category_field_options', 'id')->where('category_field_id', $field->id),
                ];
                break;
            default:
                $map[$baseKey][] = 'string';
        }

        if ($field->validation_rules) {
            $custom = explode('|', $field->validation_rules);
            $map[$baseKey] = array_merge($map[$baseKey], $custom);
        }

        $rules = array_merge($rules, $map);
    }

    return $rules;
}


    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        $attributes = [
            'title' => 'ad title',
            'description' => 'ad description',
            'price' => 'price',
            'status' => 'ad status',
        ];

        if ($this->adToUpdate && $this->adToUpdate->category) {
            foreach ($this->adToUpdate->category->fields as $field) {
                $attributes["fields.{$field->name}"] = $field->label;
            }
        }

        return $attributes;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.min' => 'The title must be at least :min characters.',
            'description.min' => 'The description must be at least :min characters.',
            'price.numeric' => 'The price must be a valid number.',
            'status.in' => 'Invalid status value.',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization()
    {
        throw new \Illuminate\Auth\Access\AuthorizationException(
            'You are not authorized to update this ad.'
        );
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Validation\ValidationException(
            $validator,
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
