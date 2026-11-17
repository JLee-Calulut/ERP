<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        return $user;
    }

    public function test_accounts_require_authentication(): void
    {
        $this->get('/erp/accounting/accounts')->assertRedirect(route('erp.login'));
    }

    public function test_accounts_require_the_accounting_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/erp/accounting/accounts')
            ->assertForbidden();
    }

    public function test_staff_can_view_the_chart_of_accounts(): void
    {
        $this->actingAs($this->staff())
            ->get('/erp/accounting/accounts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Accounting/Accounts/Index')
                ->has('accounts', 16)
                ->has('types', 5)
                ->has('filters'));
    }

    public function test_account_mutations_require_the_accounting_manage_permission(): void
    {
        $this->actingAs($this->staff())
            ->post('/erp/accounting/accounts', ['code' => '7777', 'name' => 'Blocked Account'])
            ->assertForbidden();

        $account = Account::factory()->create();

        $this->actingAs($this->staff())
            ->put("/erp/accounting/accounts/{$account->id}", ['code' => '8888', 'name' => 'Blocked Update'])
            ->assertForbidden();

        $this->actingAs($this->staff())
            ->delete("/erp/accounting/accounts/{$account->id}")
            ->assertForbidden();
    }

    public function test_admin_can_view_the_create_account_form(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/accounting/accounts/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Accounting/Accounts/Form')
                ->where('account', null));
    }

    public function test_admin_can_create_an_account(): void
    {
        $this->actingAs($this->admin())
            ->post('/erp/accounting/accounts', [
                'code' => '9000',
                'name' => 'Petty Cash',
                'type' => 'asset',
                'is_active' => 1,
                'description' => 'Cash for small purchases.',
            ])
            ->assertRedirect(route('erp.accounting.accounts.index'));

        $this->assertDatabaseHas('accounts', [
            'code' => '9000',
            'name' => 'Petty Cash',
            'type' => 'asset',
            'is_active' => 1,
        ]);
    }

    public function test_an_account_code_is_required_and_unique_and_the_type_must_be_valid(): void
    {
        $this->actingAs($this->admin())
            ->post('/erp/accounting/accounts', ['name' => 'No Code'])
            ->assertSessionHasErrors('code');

        $this->actingAs($this->admin())
            ->post('/erp/accounting/accounts', ['code' => '6000', 'name' => 'Bad Type', 'type' => 'ledger'])
            ->assertSessionHasErrors('type');

        Account::factory()->create(['code' => '9500']);

        $this->actingAs($this->admin())
            ->post('/erp/accounting/accounts', ['code' => '9500', 'name' => 'Duplicate'])
            ->assertSessionHasErrors('code');
    }

    public function test_admin_can_edit_an_account(): void
    {
        $admin = $this->admin();
        $account = Account::factory()->create(['code' => '9600', 'name' => 'Old Name']);

        $this->actingAs($admin)
            ->put("/erp/accounting/accounts/{$account->id}", [
                'code' => '9700',
                'name' => 'New Name',
                'type' => 'expense',
                'is_active' => 0,
            ])
            ->assertRedirect(route('erp.accounting.accounts.index'));

        $account->refresh();
        $this->assertEquals('9700', $account->code);
        $this->assertEquals('New Name', $account->name);
        $this->assertEquals('expense', $account->type);
        $this->assertFalse($account->is_active);
    }

    public function test_admin_can_delete_an_account_not_in_use(): void
    {
        $admin = $this->admin();
        $account = Account::factory()->create(['code' => '9800', 'name' => 'Spare Account']);

        $this->actingAs($admin)
            ->delete("/erp/accounting/accounts/{$account->id}")
            ->assertRedirect(route('erp.accounting.accounts.index'));

        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    }

    public function test_an_account_used_by_journal_lines_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $account = Account::where('code', '1010')->firstOrFail(); // Cash, referenced by the seeded entries.

        $this->assertTrue($account->lines()->exists());

        $this->actingAs($admin)
            ->delete("/erp/accounting/accounts/{$account->id}")
            ->assertRedirect(route('erp.accounting.accounts.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    }
}
