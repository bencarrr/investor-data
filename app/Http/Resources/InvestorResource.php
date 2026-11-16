<?php

namespace App\Http\Resources;

use App\Models\Investor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Investor
 */
class InvestorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{investor_id: int, name: string, age: int, total_investment_amount: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'investor_id' => $this->id,
            'name' => $this->name,
            'age' => $this->age,
            'total_investment_amount' => number_format((float) $this->total_investment_amount, 2, '.', ''),
        ];
    }
}
