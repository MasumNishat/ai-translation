<?php

namespace Masum\AiTranslator\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TranslationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'language' => new LanguageResource($this->whenLoaded('language')),
            'language_id' => $this->language_id,
            'group' => $this->group,
            'key' => $this->key,
            'value' => $this->value,
            'is_active' => $this->is_active,
            'is_auto_translated' => $this->is_auto_translated,
            'translated_by_user_id' => $this->translated_by_user_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
