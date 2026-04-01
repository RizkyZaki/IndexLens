<?php

namespace IndexLens\IndexLens\Models;

use Illuminate\Database\Eloquent\Model;

class QueryProfile extends Model
{
    protected $table = 'query_profiles';

    protected $guarded = [];

    protected $casts = [
        'execution_time' => 'float',
        'memory_usage' => 'float',
        'duplicate_count' => 'integer',
        'explain_plan' => 'array',
    ];
}
