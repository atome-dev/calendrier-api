<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holiday extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uid',
        'zone',
        'summary',
        'description',
        'starts_at',
        'ends_at',
        'population',
        'raw_ics_hash'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    protected $guarded = [];

    public function scopeZone($query, string $zone)
    {
        return $query->where('zone', $zone);
    }
}
