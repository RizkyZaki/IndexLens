<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('regression_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('route')->index();
            $table->float('baseline_query_count')->default(0);
            $table->float('current_query_count')->default(0);
            $table->float('baseline_time')->default(0);
            $table->float('current_time')->default(0);
            $table->float('delta')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regression_snapshots');
    }
};
