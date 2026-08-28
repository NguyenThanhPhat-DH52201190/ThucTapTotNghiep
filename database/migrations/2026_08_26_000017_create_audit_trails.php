<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_trails', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('event_type', 80);
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // legacy users table is MyISAM
            $table->text('before_data')->nullable();
            $table->text('after_data')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_trails');
    }
};
