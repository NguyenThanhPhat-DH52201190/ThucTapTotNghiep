<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Canonical domain name for the legacy `ocs` table (Order Cut Sheet). */
class OrderCutsheet extends Model
{
    protected $table = 'ocs';
    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
