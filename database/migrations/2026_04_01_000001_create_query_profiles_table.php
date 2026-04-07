<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('query_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('route')->nullable()->index();
            $table->longText('sql');
            $table->longText('normalized_sql');
            $table->float('execution_time')->default(0);
            $table->float('memory_usage')->default(0);
            $table->unsignedInteger('duplicate_count')->default(1);
            $table->json('explain_plan')->nullable();
            $table->string('severity')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_profiles');
    }
};
