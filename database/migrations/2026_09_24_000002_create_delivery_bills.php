<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cutsheet_id')->index();
            $table->unsignedBigInteger('issue_id')->unique();
            $table->uuid('submission_key')->unique();
            $table->string('number', 50)->unique();
            $table->date('issued_on');
            $table->json('header');
            $table->json('lines');
            $table->json('priority_snapshot');
            $table->text('priority_reason')->nullable();
            $table->string('file_path');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('delivery_bills'); }
};
