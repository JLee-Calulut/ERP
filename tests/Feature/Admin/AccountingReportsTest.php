<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountingReportsTest extends TestCase
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

    // ----------------------------------------------------------------------
    // Authorization
    // ----------------------------------------------------------------------

    public function test_the_trial_balance_requires_authentication(): void
    {
        $this->get('/erp/accounting/reports/trial-balance')
            ->assertRedirect(route('erp.login'));
    }

    public function test_the_general_ledger_requires_authentication(): void
    {
        $this->get('/erp/accounting/reports/ledger')
            ->assertRedirect(route('erp.login'));
    }

    public function test_reports_require_the_accounting_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/erp/accounting/reports/trial-balance')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/erp/accounting/reports/ledger')
            ->assertForbidden();
    }

    public function test_a_staff_role_without_accounting_view_cannot_see_reports(): void
    {
        $viewer = Role::create([
            'name' => 'Read-only Viewer',
            'slug' => 'viewer',
            'description' => 'Sees only the dashboard.',
        ]);
        $viewer->permissions()->sync(
            Permission::where('slug', 'dashboard.view')->pluck('id')
        );

        $user = User::factory()->create();
        $user->assignRole('viewer');

        $this->actingAs($user)
            ->get('/erp/accounting/reports/trial-balance')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/erp/accounting/reports/ledger')
            ->assertForbidden();
    }

    public function test_staff_with_accounting_view_can_view_both_reports(): void
    {
        $this->actingAs($this->staff())
            ->get('/erp/accounting/reports/trial-balance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Accounting/Reports/TrialBalance'));

        $this->actingAs($this->staff())
            ->get('/erp/accounting/reports/ledger')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Accounting/Reports/GeneralLedger'));
    }

    // ----------------------------------------------------------------------
    // Trial balance
    // ----------------------------------------------------------------------

    public function test_the_trial_balance_renders_every_account_with_the_correct_balance_and_balanced_totals(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/accounting/reports/trial-balance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Accounting/Reports/TrialBalance')
                ->has('accounts', 16)
                // Accounts are ordered by type then code: Cash is the first.
                ->where('accounts.0.code', '1010')
                ->where('accounts.0.name', 'Cash')
                ->where('accounts.0.debit_total', 58300)
                ->where('accounts.0.credit_total', 12650)
                ->where('accounts.0.balance', -45650)
                // Accounts Receivable ends with a 5,498 credit balance once
                // the seeded invoices (INV-000005 $155 + INV-000006 $343) are
                // posted to accounting by the seeder.
                ->where('accounts.1.code', '1020')
                ->where('accounts.1.balance', -5498)
                // Owner's Equity (first equity account) holds 50,000 credit.
                ->where('accounts.4.code', '3010')
                ->where('accounts.4.credit_total', 50000)
                ->where('accounts.4.balance', 50000)
                // Accounts Payable (first liability account) nets +17,840
                // once the seeded TechSource receipt ($1,140) is posted.
                ->where('accounts.12.code', '2010')
                ->where('accounts.12.debit_total', 9000)
                ->where('accounts.12.credit_total', 26840)
                ->where('accounts.12.balance', 17840)
                // Sales Revenue (first revenue account) carries 12,998 credit.
                ->where('accounts.14.code', '4010')
                ->where('accounts.14.credit_total', 12998)
                // The full journal sums to equal debits and credits.
                ->where('totals.debit', 125288)
                ->where('totals.credit', 125288)
                ->where('totals.balanced', true));
    }

    // ----------------------------------------------------------------------
    // General ledger
    // ----------------------------------------------------------------------

    public function test_the_general_ledger_renders_postings_with_a_running_balance(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/accounting/reports/ledger')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Accounting/Reports/GeneralLedger')
                // 15 seeded entries x 2 lines, ordered by journal date.
                ->has('postings', 30)
                ->where('postings.0.description', 'Owner capital contribution')
                ->where('postings.0.debit', 50000)
                ->where('postings.0.credit', 0)
                ->where('postings.0.balance', -50000)
                ->where('selectedAccount', null)
                ->where('filters.account', null));
    }

    public function test_the_general_ledger_account_filter_returns_only_that_accounts_history(): void
    {
        $cash = Account::where('code', '1010')->firstOrFail();

        $this->actingAs($this->admin())
            ->get('/erp/accounting/reports/ledger?account='.$cash->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Accounting/Reports/GeneralLedger')
                ->has('postings', 6)
                ->where('filters.account', $cash->id)
                ->where('selectedAccount.code', '1010')
                ->where('selectedAccount.name', 'Cash')
                ->where('selectedAccount.balance', -45650)
                // Cash postings in chronological order: capital, rent, etc.
                ->where('postings.0.description', 'Owner capital contribution')
                ->where('postings.0.account_code', '1010')
                ->where('postings.0.balance', -50000)
                ->where('postings.1.description', 'Paid facility rent in cash')
                ->where('postings.1.balance', -47000)
                ->where('postings.5.description', 'Received other income')
                ->where('postings.5.balance', -45650));
    }

    public function test_the_general_ledger_rejects_an_unknown_account_filter(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/accounting/reports/ledger?account=999999')
            ->assertNotFound();
    }
}
