<?php

namespace Tests\Feature\Client;

use App\Client\Access\CentralGroups;
use App\Models\Company\AuditLog;
use App\Models\Company\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** central:import-staff: one Central login per mailbox, a temporary password printed once and changed at first sign-in. */
class ImportStaffTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Branch::query()->create(['name' => 'Surabaya', 'code' => 'SBY', 'used_all_user' => false, 'is_active' => true]);
        $this->file = tempnam(sys_get_temp_dir(), 'staff').'.csv';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    private function csv(string $rows): void
    {
        file_put_contents($this->file, "email,role,name,branches\n".$rows);
    }

    public function test_it_creates_each_login_with_its_group_branches_and_a_password_to_change(): void
    {
        $this->csv("keuangan@javaindo.org,finance,,\nadminmarketing01@javaindo.org,marketing,,\nmarketing01@javaindo.org,sales,Budi,SBY\nhrd@javaindo.org,,,\n");

        $this->artisan('central:import-staff', ['file' => $this->file])->assertSuccessful();

        $finance = User::query()->where('email', 'keuangan@javaindo.org')->sole();
        $this->assertTrue(CentralGroups::isMember($finance, CentralGroups::FINANCE));
        $this->assertTrue($finance->password_change_required);
        $this->assertTrue($finance->is_active);
        $this->assertSame('Keuangan', $finance->name);
        $this->assertSame([Branch::default()->id], $finance->branches()->pluck('branches.id')->all());

        $marketing = User::query()->where('email', 'adminmarketing01@javaindo.org')->sole();
        $this->assertTrue(CentralGroups::isMember($marketing, CentralGroups::MARKETING));
        $this->assertSame('Adminmarketing 01', $marketing->name);
        $this->assertEqualsCanonicalizing(Branch::query()->where('is_active', true)->pluck('id')->all(), $marketing->branches()->pluck('branches.id')->all(), 'marketing reads every branch');

        $sales = User::query()->where('email', 'marketing01@javaindo.org')->sole();
        $this->assertSame('Budi', $sales->name);
        $this->assertTrue(CentralGroups::isMember($sales, CentralGroups::SALES));
        $this->assertSame(['SBY'], $sales->branches()->pluck('code')->all());

        $hrd = User::query()->where('email', 'hrd@javaindo.org')->sole();
        $this->assertSame(0, $hrd->accessGroups()->count());
        $this->assertFalse($hrd->isAdministrator());

        $log = AuditLog::query()->where('action', 'staff_imported')->get();
        $this->assertCount(4, $log);
        foreach ($log as $row) {
            $this->assertStringNotContainsStringIgnoringCase('password', (string) json_encode($row->meta));
        }
    }

    public function test_an_existing_login_is_skipped_and_its_password_kept(): void
    {
        $user = User::factory()->create(['email' => 'marketing02@javaindo.org', 'password' => Hash::make('kept-secret-1')]);
        $this->csv("marketing02@javaindo.org,sales,,\n");

        $this->artisan('central:import-staff', ['file' => $this->file])->assertSuccessful()->expectsOutputToContain('already exists');

        $this->assertTrue(Hash::check('kept-secret-1', $user->fresh()->password));
        $this->assertSame(0, $user->fresh()->accessGroups()->count());
    }

    public function test_a_bad_row_is_refused_and_the_rest_still_load(): void
    {
        $this->csv("boss@javaindo.org,administrator,,\nnot-an-email,sales,,\nmarketing03@javaindo.org,sales,,XXX\nmarketing05@javaindo.org,sales,,\n");

        $this->artisan('central:import-staff', ['file' => $this->file])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'boss@javaindo.org']);
        $this->assertDatabaseMissing('users', ['email' => 'marketing03@javaindo.org']);
        $this->assertDatabaseHas('users', ['email' => 'marketing05@javaindo.org']);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->csv("marketing07@javaindo.org,sales,,\n");

        $this->artisan('central:import-staff', ['file' => $this->file, '--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'marketing07@javaindo.org']);
    }
}
