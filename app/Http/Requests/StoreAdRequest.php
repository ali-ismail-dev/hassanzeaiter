<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Category;
use App\Models\CategoryField;


class StoreAdRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            // Base ad fields
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'min:5', 'max:100'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ];

        // Add dynamic field rules based on category
        $dynamicRules = $this->getDynamicFieldRules();

        return array_merge($rules, $dynamicRules);
    }

    /**
     * Get dynamic validation rules based on the selected category.
     */
    private function getDynamicFieldRules(): array
    {
        $rules = [];

        $categoryId = $this->input('category_id');
        if (!$categoryId) {
            return $rules;
        }

        $this->category = Category::with(['fields.options'])->find($categoryId);
        if (!$this->category) {
            return $rules;
        }

        foreach ($this->category->fields as $field) {
            // choose canonical key used in payload: prefer external_id, fallback to name or id
            $fieldKey = $field->external_id ?: $field->name ?: $field->id;

            // buildRulesForField now returns an associative array of top-level rules:
            // e.g. ['fields.make' => ['required','string'], 'fields.amenities.*' => ['integer']]
            $fieldRulesMap = $this->buildRulesForField($field, $fieldKey);

            // merge into main rules array
            $rules = array_merge($rules, $fieldRulesMap);
        }

        return $rules;
    }

    /**
     * Build validation rules for a specific category field.
     * Returns an associative array of ruleKey => rulesArray
     */
    private function buildRulesForField(\App\Models\CategoryField $field, string $fieldKey): array
    {
        $map = [];
        $baseKey = "fields.{$fieldKey}";

        // Required or nullable
        $baseRules = [];
        if ($field->is_required) {
            $baseRules[] = 'required';
        } else {
            $baseRules[] = 'nullable';
        }

        // Type-specific rules
        switch ($field->field_type) {
            case 'text':
            case 'textarea':
                $baseRules[] = 'string';
                break;

            case 'number':
                // numeric handles integers & decimals; use integer only if you know it's integer
                $baseRules[] = 'numeric';
                break;

            case 'email':
                $baseRules[] = 'email';
                $baseRules[] = 'max:255';
                break;

            case 'url':
                $baseRules[] = 'url';
                $baseRules[] = 'max:2048';
                break;

            case 'date':
                $baseRules[] = 'date';
                break;

            case 'select':
            case 'radio':
                // single option: expecting the option ID
                $baseRules[] = 'integer';
                $baseRules[] = Rule::exists('category_field_options', 'id')->where('category_field_id', $field->id);
                break;

            case 'checkbox':
                // For checkbox we need two rules: one for the array and one for each element
                $map[$baseKey] = $field->is_required ? array_merge($baseRules, ['array', 'min:1']) : array_merge($baseRules, ['array']);
                // element rules:
                $elementKey = $baseKey . '.*';
                $map[$elementKey] = [
                    'integer',
                    Rule::exists('category_field_options', 'id')->where('category_field_id', $field->id),
                ];

                // Add custom rules to baseKey if present (rare for checkboxes)
                if (!empty($field->validation_rules)) {
                    $custom = explode('|', $field->validation_rules);
                    $map[$baseKey] = array_merge($map[$baseKey], $custom);
                }

                return $map; // we've already set both keys
        }

        // Add custom validation rules from field definition (for non-checkbox)
        if (!empty($field->validation_rules)) {
            $customRules = explode('|', $field->validation_rules);
            $baseRules = array_merge($baseRules, $customRules);
        }

        $map[$baseKey] = $baseRules;
        return $map;
    }


    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        $attributes = [
            'category_id' => 'category',
            'title' => 'ad title',
            'description' => 'ad description',
            'price' => 'price',
        ];

        // Add friendly names for dynamic fields
        if ($this->category) {
            foreach ($this->category->fields as $field) {
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
            'category_id.required' => 'Please select a category for your ad.',
            'category_id.exists' => 'The selected category is invalid.',
            'title.required' => 'Your ad needs a title.',
            'title.min' => 'The title must be at least :min characters.',
            'title.max' => 'The title cannot exceed :max characters.',
            'description.required' => 'Please provide a description for your ad.',
            'description.min' => 'The description must be at least :min characters.',
            'price.numeric' => 'The price must be a valid number.',
            'price.min' => 'The price cannot be negative.',
            'fields.*.required' => 'The :attribute field is required.',
            'fields.*.integer' => 'The :attribute must be a valid number.',
            'fields.*.exists' => 'The selected :attribute is invalid.',
            'fields.*.array' => 'The :attribute must be a list of options.',
            'fields.*.email' => 'The :attribute must be a valid email address.',
            'fields.*.url' => 'The :attribute must be a valid URL.',
            'fields.*.date' => 'The :attribute must be a valid date.',
        ];
    }

    /**
     * Get the validated category.
     */
    public function getCategory(): ?Category
    {
        return $this->category;
    }

    /**
     * Get validated dynamic fields data.
     */
    public function getValidatedFields(): array
    {
        return $this->validated()['fields'] ?? [];
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
