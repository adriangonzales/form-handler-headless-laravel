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

        Schema::create('form_notifications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('form_id')->constrained();
            $table->enum('type', ['email', 'sms']);
            $table->string('value');
            $table->boolean('enabled')->default(true);
            $table->string('error')->nullable();
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
        Schema::dropIfExists('form_notifications');
    }
};
