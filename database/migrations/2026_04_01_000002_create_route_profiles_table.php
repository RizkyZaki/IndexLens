<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('route_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('route')->index();
            $table->float('avg_query_count')->default(0);
            $table->float('avg_sql_time')->default(0);
            $table->float('avg_memory')->default(0);
            $table->unsignedInteger('request_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_profiles');
    }
};
