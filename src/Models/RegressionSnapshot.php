<?php

namespace IndexLens\IndexLens\Models;

use Illuminate\Database\Eloquent\Model;

class RegressionSnapshot extends Model
{
    protected $table = 'regression_snapshots';

    protected $guarded = [];

    protected $casts = [
        'baseline_query_count' => 'float',
        'current_query_count' => 'float',
        'baseline_time' => 'float',
        'current_time' => 'float',
        'delta' => 'float',
    ];
}
