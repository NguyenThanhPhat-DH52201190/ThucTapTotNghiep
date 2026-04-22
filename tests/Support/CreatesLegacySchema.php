<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CreatesLegacySchema
{
    protected function createLegacySchema(): void
    {
        $connection = config('database.default');
        $database = config('database.connections.' . $connection . '.database');

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException(sprintf(
                'Refusing to reset schema on non-test database. Active connection: %s, database: %s',
                (string) $connection,
                (string) $database
            ));
        }


        $this->createUsersTable();
        $this->createColorsTable();
        $this->createOcsTable();
        $this->createHolidaysTable();
        $this->createMtpTable();
    }

    protected function createUserRecord(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'user-' . uniqid(),
            'email' => uniqid('user-', true) . '@local.test',
            'password' => 'Password123!',
            'role' => User::ROLE_USER,
        ], $attributes));
    }

    protected function createOcsRecord(array $attributes = []): void
    {
        DB::table('ocs')->insert(array_merge([
            'CS' => 'CS-001',
            'CsDate' => '2026-04-20',
            'SNo' => 'S-001',
            'Sname' => 'Sample Style',
            'Customer' => 'Sample Customer',
            'Color' => 'Blue',
            'ONum' => 'PO-001',
            'CMT' => 10,
            'Qty' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    protected function createUsersTable(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default(User::ROLE_USER);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    protected function createOcsTable(): void
    {
        Schema::create('ocs', function (Blueprint $table): void {
            $table->id();
            $table->string('CS')->unique();
            $table->date('CsDate')->nullable();
            $table->string('SNo')->nullable();
            $table->string('Sname')->nullable();
            $table->string('Customer')->nullable();
            $table->string('Color')->nullable();
            $table->string('ONum')->nullable();
            $table->decimal('CMT', 10, 2)->nullable();
            $table->unsignedInteger('Qty')->default(0);
            $table->timestamps();
        });
    }

    protected function createColorsTable(): void
    {
        Schema::create('colors', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('hex_code', 7);
            $table->string('cate', 10)->default('GSV');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('colors')->insert([
            [
                'name' => 'Blue',
                'hex_code' => '#0000FF',
                'cate' => 'GSV',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Green',
                'hex_code' => '#008000',
                'cate' => 'GSV',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Orange',
                'hex_code' => '#FFA500',
                'cate' => 'GSV',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Yellow',
                'hex_code' => '#FFFF00',
                'cate' => 'GSV',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Sample',
                'hex_code' => '#808080',
                'cate' => 'GSV',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    protected function createHolidaysTable(): void
    {
        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->date('holiday')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    protected function createMtpTable(): void
    {
        Schema::create('mtp', function (Blueprint $table): void {
            $table->id();
            $table->string('CU');
            $table->string('Line');
            $table->string('LineColor', 7)->nullable();
            $table->string('Fabric1', 50)->nullable();
            $table->date('ETA1')->nullable();
            $table->date('Actual')->nullable();
            $table->string('Fabric2', 50)->nullable();
            $table->date('ETA2')->nullable();
            $table->string('Linning', 50)->nullable();
            $table->date('ETA3')->nullable();
            $table->string('Pocket', 50)->nullable();
            $table->date('ETA4')->nullable();
            $table->string('Trim', 50)->nullable();
            $table->date('Norm_date')->nullable();
            $table->date('inWHDate')->nullable();
            $table->string('3rd_PartyInspection', 50)->nullable();
            $table->date('ShipDate2')->nullable();
            $table->string('SoTK', 50)->nullable();
            $table->unsignedInteger('ExQty')->nullable();
            $table->integer('lt')->nullable();
            $table->date('FirstOPT')->nullable();
            $table->unsignedInteger('Qty_dis')->nullable();
            $table->timestamps();
        });
    }
}