<?php

namespace Tests\Feature;

use App\Imports\OCSImport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Tests\Support\CreatesLegacySchema;
use Tests\TestCase;

class LegacyWorkflowTest extends TestCase
{
    use CreatesLegacySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createLegacySchema();
    }

    public function test_public_auth_pages_are_accessible(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_register_logs_in_ppic_user_and_redirects_by_role(): void
    {
        $this->post('/register', [
            'username' => 'ppic.user',
            'role' => User::ROLE_PPIC,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('ordercutsheet.view'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'ppic.user',
            'role' => User::ROLE_PPIC,
        ]);
    }

    public function test_register_rejects_second_admin_account(): void
    {
        $this->createUserRecord([
            'name' => 'existing-admin',
            'email' => 'admin@local.test',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->post('/register', [
            'username' => 'second-admin',
            'role' => User::ROLE_ADMIN,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertSessionHasErrors('role');

        $this->assertGuest();
    }

    public function test_login_redirects_admin_user_to_admin_dashboard(): void
    {
        $this->createUserRecord([
            'name' => 'admin.user',
            'email' => 'admin.user@local.test',
            'password' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->post('/login', [
            'username' => 'admin.user',
            'password' => 'Password123!',
        ])->assertRedirect(route('admin.ocs.index'));

        $this->assertAuthenticated();
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->createUserRecord([
            'name' => 'ppic.login',
            'email' => 'ppic.login@local.test',
            'password' => 'Password123!',
            'role' => User::ROLE_PPIC,
        ]);

        $this->post('/login', [
            'username' => 'ppic.login',
            'password' => 'WrongPassword!',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_logout_invalidates_the_session(): void
    {
        $this->actingAs($this->createUserRecord([
            'name' => 'logout.user',
            'email' => 'logout.user@local.test',
            'password' => 'Password123!',
            'role' => User::ROLE_PPIC,
        ]));

        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_dashboard_redirects_admin_and_ppic_users_to_their_landing_pages(): void
    {
        $this->actingAs($this->createUserRecord([
            'name' => 'dashboard-admin',
            'email' => 'dashboard-admin@local.test',
            'password' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]));

        $this->get('/dashboard')->assertRedirect(route('admin.ocs.index'));

        $this->actingAs($this->createUserRecord([
            'name' => 'dashboard-ppic',
            'email' => 'dashboard-ppic@local.test',
            'password' => 'Password123!',
            'role' => User::ROLE_PPIC,
        ]));

        $this->get('/dashboard')->assertRedirect(route('ordercutsheet.view'));
    }

    public function test_admin_dashboard_enforces_role_access(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');

        $this->actingAs($this->createUserRecord([
            'name' => 'not-admin',
            'email' => 'not-admin@local.test',
            'password' => 'Password123!',
            'role' => User::ROLE_PPIC,
        ]));

        $this->get('/admin/dashboard')->assertForbidden();

        Route::middleware('role:admin')->get('/_tests/admin-only', fn () => 'ok');

        $this->actingAs($this->createUserRecord([
            'name' => 'yes-admin',
            'email' => 'yes-admin@local.test',
            'password' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]));

        $this->get('/_tests/admin-only')->assertOk()->assertSee('ok');
    }

    public function test_masterplan_store_rejects_sunday_first_opt(): void
    {
        $this->createOcsRecord([
            'CS' => 'CS-100',
            'Qty' => 100,
        ]);

        $this->actingAs($this->createUserRecord([
            'name' => 'masterplan-admin',
            'email' => 'masterplan-admin@local.test',
            'password' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]));

        $this->post(route('admin.masterplan.store'), [
            'CU' => 'CS-100',
            'Line' => '#008000',
            'LineColor' => '#008000',
            'Qty_dis' => 10,
            'lt' => 3,
            'FirstOPT' => '2026-04-26',
            'ExQty' => 0,
        ])->assertSessionHasErrors('FirstOPT');
    }

    public function test_masterplan_store_rejects_ex_qty_above_qty_dis(): void
    {
        $this->createOcsRecord([
            'CS' => 'CS-101',
            'Qty' => 100,
        ]);

        $this->actingAs($this->createUserRecord([
            'name' => 'masterplan-admin-2',
            'email' => 'masterplan-admin-2@local.test',
            'password' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]));

        $this->post(route('admin.masterplan.store'), [
            'CU' => 'CS-101',
            'Line' => '#008000',
            'LineColor' => '#008000',
            'Qty_dis' => 10,
            'lt' => 3,
            'FirstOPT' => '2026-04-27',
            'ExQty' => 11,
        ])->assertSessionHasErrors('ExQty');
    }

    public function test_ocs_import_parses_numeric_excel_dates_and_upserts_rows(): void
    {
        $import = new OCSImport();
        $excelDate = Date::dateTimeToExcel(Carbon::parse('2026-04-20'));

        $import->collection(collect([
            [
                'cs' => 'CS-900',
                'onum' => 'PO-900',
                'sno' => 'S-900',
                'sname' => 'Imported Style',
                'customer' => 'Imported Customer',
                'csdate' => $excelDate,
                'cmt' => 12.5,
                'color' => 'Red',
                'qty' => 250,
            ],
        ]));

        $this->assertDatabaseHas('ocs', [
            'CS' => 'CS-900',
            'CsDate' => '2026-04-20',
            'Qty' => 250,
        ]);

        $import->collection(collect([
            [
                'cs' => 'CS-900',
                'onum' => 'PO-900',
                'sno' => 'S-900',
                'sname' => 'Imported Style',
                'customer' => 'Imported Customer',
                'csdate' => '2026-04-21',
                'cmt' => 15,
                'color' => 'Blue',
                'qty' => 300,
            ],
        ]));

        $this->assertSame('2026-04-21', DB::table('ocs')->where('CS', 'CS-900')->value('CsDate'));
        $this->assertSame(300, (int) DB::table('ocs')->where('CS', 'CS-900')->value('Qty'));
    }

    public function test_holiday_store_persists_a_new_record(): void
    {
        $this->actingAs($this->createUserRecord([
            'name' => 'holiday-admin',
            'email' => 'holiday-admin@local.test',
            'password' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]));

        $this->post(route('admin.holidays.store'), [
            'holiday' => '2026-05-01',
            'name' => 'Labour Day',
        ])->assertRedirect(route('admin.holidays.index'));

        $this->assertDatabaseHas('holidays', [
            'holiday' => '2026-05-01',
            'name' => 'Labour Day',
        ]);
    }

    public function test_holiday_store_rejects_duplicate_dates(): void
    {
        DB::table('holidays')->insert([
            'holiday' => '2026-05-01',
            'name' => 'Existing Holiday',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->createUserRecord([
            'name' => 'holiday-admin-2',
            'email' => 'holiday-admin-2@local.test',
            'password' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]));

        $this->post(route('admin.holidays.store'), [
            'holiday' => '2026-05-01',
            'name' => 'Labour Day',
        ])->assertSessionHasErrors('holiday');
    }

    public function test_masterplan_add_view_receives_cus_sorted_by_cs(): void
    {
        DB::table('ocs')->insert([
            [
                'CS' => 'CU5943',
                'CsDate' => '2026-04-20',
                'SNo' => 'S-3',
                'Sname' => 'Style 3',
                'Customer' => 'Customer 3',
                'Color' => 'Blue',
                'ONum' => 'PO-3',
                'CMT' => 10,
                'Qty' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'CS' => 'CU5899',
                'CsDate' => '2026-04-20',
                'SNo' => 'S-2',
                'Sname' => 'Style 2',
                'Customer' => 'Customer 2',
                'Color' => 'Blue',
                'ONum' => 'PO-2',
                'CMT' => 10,
                'Qty' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'CS' => 'CU1000',
                'CsDate' => '2026-04-20',
                'SNo' => 'S-1',
                'Sname' => 'Style 1',
                'Customer' => 'Customer 1',
                'Color' => 'Blue',
                'ONum' => 'PO-1',
                'CMT' => 10,
                'Qty' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($this->createUserRecord([
            'name' => 'view-admin',
            'email' => 'view-admin@local.test',
            'password' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]));

        $this->get(route('admin.masterplan.create'))
            ->assertOk()
            ->assertSeeInOrder(['CU1000', 'CU5899', 'CU5943']);
    }
}