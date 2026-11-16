<?php

namespace App\Models;

use Database\Factories\InvestorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The primary key is the investor_id supplied in imported files, so it is not auto-incrementing.
 */
#[Fillable(['id', 'name', 'age'])]
class Investor extends Model
{
    /** @use HasFactory<InvestorFactory> */
    use HasFactory;

    public $incrementing = false;

    /**
     * @return HasMany<Investment, $this>
     */
    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'age' => 'integer',
        ];
    }
}
