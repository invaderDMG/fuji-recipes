<?php

namespace App\Http\Resources;

use App\Services\SimilarityResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SimilarityResult
 */
class SimilarityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'distance' => round($this->total, 4),
            'breakdown' => array_map(fn (float $v) => round($v, 4), $this->parts),
            'differing_fields' => $this->differingFields(),
            'top_contributors' => array_map(fn (float $v) => round($v, 4), $this->topContributors()),
            'recipe' => new RecipeResource($this->recipe),
        ];
    }
}
