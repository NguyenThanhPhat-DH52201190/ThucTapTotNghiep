<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('colors')) {
            Schema::create('colors', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->string('hex_code', 7);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $defaults = [
            ['name' => 'Blue', 'hex_code' => '#0000FF'],
            ['name' => 'Green', 'hex_code' => '#008000'],
            ['name' => 'Orange', 'hex_code' => '#FFA500'],
            ['name' => 'Yellow', 'hex_code' => '#FFFF00'],
            ['name' => 'Sample', 'hex_code' => '#808080'],
        ];

        foreach ($defaults as $row) {
            DB::table('colors')->updateOrInsert(
                ['name' => $row['name']],
                [
                    'hex_code' => $row['hex_code'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('colors');
    }
};
