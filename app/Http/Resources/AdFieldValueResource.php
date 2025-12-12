<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdFieldValueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
         return [
            'field_name' => $this->categoryField->name,
            'field_label' => $this->categoryField->label,
            'field_type' => $this->categoryField->field_type,
            'value' => $this->getValue(),
            'display_value' => $this->getDisplayValue(),
        ];
    }

    /**
     * Get formatted display value for the field.
     */
    private function getDisplayValue()
    {
        $value = $this->getValue();

        switch ($this->categoryField->field_type) {
            case 'select':
            case 'radio':
                return $value['label'] ?? $value;
            
            case 'checkbox':
                if (is_array($value)) {
                    $options = $this->categoryField->options()
                        ->whereIn('id', $value)
                        ->get();
                    return $options->pluck('label')->toArray();
                }
                return [];
            
            case 'date':
                return $value ? \Carbon\Carbon::parse($value)->format('Y-m-d') : null;
            
            default:
                return $value;
        }
    }
}
