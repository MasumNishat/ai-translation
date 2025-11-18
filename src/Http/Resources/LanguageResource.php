<?php

namespace Masum\AiTranslator\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LanguageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'native_name' => $this->native_name,
            'direction' => $this->direction,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'country_code' => $this->country_code,
            'region' => $this->region,
            'is_rtl' => $this->isRtl(),
            'country_info' => $this->getCountryInfo(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
