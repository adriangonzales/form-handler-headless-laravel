<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('form_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('form_id')->constrained();
            $table->json('input')->nullable();
            $table->string('ip')->nullable();
            $table->string('ip_location_display')->nullable();
            $table->string('referer')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('user_agent_display')->nullable();
            $table->boolean('spam')->nullable();
            $table->decimal('spam_score', 3, 2)->default(0);
            $table->string('spam_reason')->nullable();
            $table->timestamp('spam_checked_at')->nullable();
            $table->boolean('starred')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_entries');
    }
};
