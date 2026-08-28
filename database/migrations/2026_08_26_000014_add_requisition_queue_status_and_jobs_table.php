<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('ocs', function (Blueprint $table) {
            if (!Schema::hasColumn('ocs', 'requisition_job_status')) $table->string('requisition_job_status', 20)->default('none');
            if (!Schema::hasColumn('ocs', 'requisition_job_error')) $table->text('requisition_job_error')->nullable();
        });
        if (!Schema::hasTable('jobs')) Schema::create('jobs', function (Blueprint $table) { $table->id(); $table->string('queue')->index(); $table->longText('payload'); $table->unsignedTinyInteger('attempts'); $table->unsignedInteger('reserved_at')->nullable(); $table->unsignedInteger('available_at'); $table->unsignedInteger('created_at'); });
        if (!Schema::hasTable('failed_jobs')) Schema::create('failed_jobs', function (Blueprint $table) { $table->id(); $table->string('uuid')->unique(); $table->text('connection'); $table->text('queue'); $table->longText('payload'); $table->longText('exception'); $table->timestamp('failed_at')->useCurrent(); });
    }
    public function down(): void { Schema::dropIfExists('failed_jobs'); Schema::dropIfExists('jobs'); Schema::table('ocs', function (Blueprint $table) { $table->dropColumn(['requisition_job_status','requisition_job_error']); }); }
};
