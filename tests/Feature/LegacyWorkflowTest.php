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
            ->assertSee('-- Select Line --')
            ->assertSee('Blue')
            ->assertSee('Sample')
            ->assertSeeInOrder(['CU1000', 'CU5899', 'CU5943']);
    }

    public function test_ppic_can_open_masterplan_page_and_edit_fabric_to_trim_only(): void
    {
        DB::table('ocs')->insert([
            'CS' => 'CU7777',
            'CsDate' => '2026-04-20',
            'SNo' => 'S-7777',
            'Sname' => 'Style 7777',
            'Customer' => 'Customer 7777',
            'Color' => 'Blue',
            'ONum' => 'PO-7777',
            'CMT' => 10,
            'Qty' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table('mtp')->insertGetId([
            'CU' => 'CU7777',
            'Line' => 'Green',
            'LineColor' => '#008000',
            'Fabric1' => 'OLD-FAB',
            'ETA1' => '2026-04-20',
            'Actual' => '2026-04-21',
            'Fabric2' => 'OLD-FAB2',
            'ETA2' => '2026-04-22',
            'Linning' => 'OLD-LINING',
            'ETA3' => '2026-04-23',
            'Pocket' => 'OLD-POCKET',
            'ETA4' => '2026-04-24',
            'Trim' => 'OLD-TRIM',
            'inWHDate' => '2026-04-25',
            'SoTK' => 'OLD-SOTK',
            'ExQty' => 12,
            'lt' => 2,
            'FirstOPT' => '2026-04-27',
            'Qty_dis' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->createUserRecord([
            'name' => 'ppic-masterplan',
            'email' => 'ppic-masterplan@local.test',
            'password' => 'Password123!',
            'role' => User::ROLE_PPIC,
        ]));

        $this->get(route('masterplan.view'))->assertOk();

        $this->put(route('masterplan.fabric.update', $id), [
            'Fabric1' => 'NEW-FAB',
            'ETA1' => '2026-05-01',
            'Actual' => '2026-05-02',
            'Fabric2' => 'NEW-FAB2',
            'ETA2' => '2026-05-03',
            'Linning' => 'NEW-LINING',
            'ETA3' => '2026-05-04',
            'Pocket' => 'NEW-POCKET',
            'ETA4' => '2026-05-05',
            'Trim' => 'NEW-TRIM',
            // These must stay unchanged for ppic scope.
            'Line' => 'Blue',
            'Qty_dis' => 999,
            'SoTK' => 'HACK',
        ])->assertRedirect(route('masterplan.view'));

        $updated = DB::table('mtp')->where('id', $id)->first();

        $this->assertSame('NEW-FAB', $updated->Fabric1);
        $this->assertSame('2026-05-01', $updated->ETA1);
        $this->assertSame('NEW-FAB2', $updated->Fabric2);
        $this->assertSame('NEW-LINING', $updated->Linning);
        $this->assertSame('NEW-TRIM', $updated->Trim);

        $this->assertSame('Green', $updated->Line);
        $this->assertSame(100, (int) $updated->Qty_dis);
        $this->assertSame('OLD-SOTK', $updated->SoTK);
    }

    public function test_ppic_cannot_delete_masterplan_records(): void
    {
        DB::table('ocs')->insert([
            'CS' => 'CU8888',
            'CsDate' => '2026-04-20',
            'SNo' => 'S-8888',
            'Sname' => 'Style 8888',
            'Customer' => 'Customer 8888',
            'Color' => 'Blue',
            'ONum' => 'PO-8888',
            'CMT' => 10,
            'Qty' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table('mtp')->insertGetId([
            'CU' => 'CU8888',
            'Line' => 'Green',
            'LineColor' => '#008000',
            'Qty_dis' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->createUserRecord([
            'name' => 'ppic-no-delete',
            'email' => 'ppic-no-delete@local.test',
            'password' => 'Password123!',
            'role' => User::ROLE_PPIC,
        ]));

        $this->delete(route('admin.masterplan.destroy', $id))->assertForbidden();
        $this->assertDatabaseHas('mtp', ['id' => $id]);
    }

    public function test_ship_balance_filter_shows_only_positive_values(): void
    {
        DB::table('ocs')->insert([
            [
                'CS' => 'CU-POS',
                'CsDate' => '2026-04-20',
                'SNo' => 'S-POS',
                'Sname' => 'Style POS',
                'Customer' => 'Customer POS',
                'Color' => 'Blue',
                'ONum' => 'PO-POS',
                'CMT' => 10,
                'Qty' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'CS' => 'CU-ZERO',
                'CsDate' => '2026-04-20',
                'SNo' => 'S-ZERO',
                'Sname' => 'Style ZERO',
                'Customer' => 'Customer ZERO',
                'Color' => 'Blue',
                'ONum' => 'PO-ZERO',
                'CMT' => 10,
                'Qty' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'CS' => 'CU-NEG',
                'CsDate' => '2026-04-20',
                'SNo' => 'S-NEG',
                'Sname' => 'Style NEG',
                'Customer' => 'Customer NEG',
                'Color' => 'Blue',
                'ONum' => 'PO-NEG',
                'CMT' => 10,
                'Qty' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('mtp')->insert([
            [
                'CU' => 'CU-POS',
                'Line' => 'Blue',
                'LineColor' => '#0000FF',
                'Qty_dis' => 100,
                'ExQty' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'CU' => 'CU-ZERO',
                'Line' => 'Blue',
                'LineColor' => '#0000FF',
                'Qty_dis' => 80,
                'ExQty' => 80,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'CU' => 'CU-NEG',
                'Line' => 'Blue',
                'LineColor' => '#0000FF',
                'Qty_dis' => 50,
                'ExQty' => 70,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($this->createUserRecord([
            'name' => 'ship-balance-admin',
            'email' => 'ship-balance-admin@local.test',
            'password' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]));

        $this->get(route('masterplan.view', ['ship_balance_only' => 1]))
            ->assertOk()
            ->assertSee('CU-POS')
            ->assertDontSee('CU-ZERO')
            ->assertDontSee('CU-NEG');
    }

    public function test_admin_can_create_and_update_color_master_record(): void
    {
        $admin = $this->createUserRecord([
            'name' => 'color-admin',
            'email' => 'color-admin@local.test',
            'password' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAs($admin);

        $this->post(route('admin.colors.store'), [
            'name' => 'Purple',
            'hex_code' => '#800080',
            'cate' => 'GSV',
            'is_active' => '1',
        ])->assertRedirect(route('admin.colors.index'));

        $this->assertDatabaseHas('colors', [
            'name' => 'Purple',
            'hex_code' => '#800080',
            'cate' => 'GSV',
            'is_active' => 1,
        ]);

        $id = DB::table('colors')->where('name', 'Purple')->value('id');

        $this->put(route('admin.colors.update', $id), [
            'name' => 'Purple Updated',
            'hex_code' => '#7B1FA2',
            'cate' => 'Subcon',
        ])->assertRedirect(route('admin.colors.index'));

        $this->assertDatabaseHas('colors', [
            'name' => 'Purple Updated',
            'hex_code' => '#7B1FA2',
            'cate' => 'Subcon',
            'is_active' => 0,
        ]);
    }
}