<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bom_headers', function (Blueprint $table) {
            $table->unsignedBigInteger('template_id')->nullable()->after('id');
            $table->unsignedBigInteger('cutsheet_id')->nullable()->after('template_id');
            $table->string('bom_kind', 20)->default('template')->after('cutsheet_id');
            $table->string('mapping_status', 30)->default('not_applicable')->after('bom_kind');
            $table->index(['bom_kind', 'cutsheet_id']);
        });
        Schema::table('bom_items', function (Blueprint $table) {
            $table->string('size_rule', 30)->default('all')->after('size');
        });
        Schema::create('bom_item_size_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_item_id')->constrained('bom_items')->cascadeOnDelete();
            $table->foreignId('order_size_id')->constrained('order_sizes')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['bom_item_id', 'order_size_id'], 'bom_item_order_size_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_item_size_mappings');
        Schema::table('bom_items', fn (Blueprint $table) => $table->dropColumn('size_rule'));
        Schema::table('bom_headers', function (Blueprint $table) {
            $table->dropIndex(['bom_kind', 'cutsheet_id']);
            $table->dropColumn(['template_id', 'cutsheet_id', 'bom_kind', 'mapping_status']);
        });
    }
};
