<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Category;
use App\Models\CategoryField;

class StoreAdRequest extends FormRequest
{
    /**
     * The loaded category (if available).
     */
    private ?Category $category = null;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Note: actual route protection should be done via Sanctum middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Base ad fields + dynamically generated rules for category fields.
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
     *
     * Returns a rules array compatible with Laravel Validator, e.g.:
     * [
     *   'fields.make' => ['required','integer'],
     *   'fields.amenities.*' => ['integer', Rule::exists(...)]
     * ]
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
            // canonical key used in payload
            $fieldKey = $this->getFieldKey($field);

            // buildRulesForField returns associative map of rules
            $fieldRulesMap = $this->buildRulesForField($field, $fieldKey);

            // merge into main rules array
            $rules = array_merge($rules, $fieldRulesMap);
        }

        return $rules;
    }

    /**
     * Build validation rules for a specific category field.
     * Returns an associative array: ruleKey => rulesArray
     *
     * @param  CategoryField  $field
     * @param  string  $fieldKey
     * @return array
     */
    private function buildRulesForField(CategoryField $field, string $fieldKey): array
    {
        $map = [];
        $baseKey = "fields.{$fieldKey}";

        // Base rules: required vs nullable
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
                // Use numeric to accept ints/decimals; if you need integer-only, change to integer
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
                // single selected option -> expect option id that exists for this field
                $baseRules[] = 'integer';
                $baseRules[] = Rule::exists('category_field_options', 'id')->where('category_field_id', $field->id);
                break;

            case 'checkbox':
                // For checkbox we need two rules: one for the array, and one for each element
                $map[$baseKey] = $field->is_required ? array_merge($baseRules, ['array', 'min:1']) : array_merge($baseRules, ['array']);

                // element rules: each element must be an integer option id that belongs to this field
                $elementKey = $baseKey . '.*';
                $map[$elementKey] = [
                    'integer',
                    Rule::exists('category_field_options', 'id')->where('category_field_id', $field->id),
                ];

                // Add custom rules to the base array if present (rare for checkboxes)
                if (!empty($field->validation_rules)) {
                    $custom = explode('|', $field->validation_rules);
                    $map[$baseKey] = array_merge($map[$baseKey], $custom);
                }

                return $map;
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
     * Compute the canonical payload key for a category field.
     * Preference order: external_id -> name -> id
     */
    private function getFieldKey(CategoryField $field): string
    {
        if (!empty($field->external_id)) {
            return (string) $field->external_id;
        }

        if (!empty($field->name)) {
            // sanitize name (optional) - keep as-is to match seed payloads
            return (string) $field->name;
        }

        return (string) $field->id;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * Use the same canonical keys as the rules so messages show friendly labels.
     */
    public function attributes(): array
    {
        $attributes = [
            'category_id' => 'category',
            'title' => 'ad title',
            'description' => 'ad description',
            'price' => 'price',
        ];

        if ($this->category) {
            foreach ($this->category->fields as $field) {
                $key = $this->getFieldKey($field);
                $attributes["fields.{$key}"] = $field->label ?? $field->name ?? $key;
                // for checkbox elements
                $attributes["fields.{$key}.*"] = $field->label ?? $field->name ?? $key;
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
     * Get the validated category (if loaded).
     */
    public function getCategory(): ?Category
    {
        return $this->category;
    }

    /**
     * Return the validated dynamic fields payload as array.
     * Defensive: ensure it always returns an array.
     */
    public function getValidatedFields(): array
    {
        $validated = $this->validated() ?? [];

        // The validator returns nested arrays when using keys like 'fields.<key>'
        return $validated['fields'] ?? [];
    }

    /**
     * Handle a failed validation attempt - return standardized JSON for API.
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