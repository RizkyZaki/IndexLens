<?php

namespace IndexLens\IndexLens\Models;

use Illuminate\Database\Eloquent\Model;

class RouteProfile extends Model
{
    protected $table = 'route_profiles';

    protected $guarded = [];

    protected $casts = [
        'avg_query_count' => 'float',
        'avg_sql_time' => 'float',
        'avg_memory' => 'float',
        'request_count' => 'integer',
    ];
}
