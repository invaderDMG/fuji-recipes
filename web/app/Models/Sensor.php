<?php

namespace App\Models;

use Database\Factories\SensorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sensor extends Model
{
    /** @use HasFactory<SensorFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class)->withPivot('index_label');
    }
}
