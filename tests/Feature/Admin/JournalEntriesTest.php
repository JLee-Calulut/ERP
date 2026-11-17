<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JournalEntriesTest extends TestCase
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

    public function test_journal_requires_authentication(): void
    {
        $this->get('/erp/accounting/journal')->assertRedirect(route('erp.login'));
    }

    public function test_journal_requires_the_accounting_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/erp/accounting/journal')
            ->assertForbidden();
    }

    public function test_the_journal_index_renders(): void
    {
        $this->actingAs($this->staff())
            ->get('/erp/accounting/journal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Accounting/Journal/Index')
                ->has('entries', 15)
                // Newest journal date first.
                ->where('entries.0.description', 'Received other income')
                ->where('entries.0.total', 800)
                ->where('entries.0.lines_count', 2));
    }

    public function test_posting_an_entry_requires_the_accounting_manage_permission(): void
    {
        $staff = $this->staff();
        $cash = Account::where('code', '1010')->firstOrFail();
        $equity = Account::where('code', '3010')->firstOrFail();

        $this->actingAs($staff)
            ->post('/erp/accounting/journal', [
                'journal_date' => now()->toDateString(),
                'description' => 'Blocked entry',
                'lines' => [
                    ['account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
                    ['account_id' => $equity->id, 'debit' => 0, 'credit' => 100],
                ],
            ])
            ->assertForbidden();
    }

    public function test_admin_can_view_the_post_entry_form(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/accounting/journal/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Accounting/Journal/Form')
                ->has('accounts', 16));
    }

    public function test_posting_a_balanced_entry_persists_the_entry_lines_and_derives_a_unique_number(): void
    {
        $admin = $this->admin();
        $cash = Account::where('code', '1010')->firstOrFail();
        $equity = Account::where('code', '3010')->firstOrFail();

        $this->actingAs($admin)
            ->post('/erp/accounting/journal', [
                'journal_date' => now()->toDateString(),
                'description' => 'Owner contribution',
                'lines' => [
                    ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0, 'memo' => 'Cash in'],
                    ['account_id' => $equity->id, 'debit' => 0, 'credit' => 1000, 'memo' => 'Equity'],
                ],
            ])
            ->assertRedirect(route('erp.accounting.journal.index'));

        $entry = JournalEntry::latest('id')->first();

        $this->assertStringStartsWith('JE-', $entry->entry_number);
        $this->assertStringEndsWith(str_pad((string) $entry->id, 6, '0', STR_PAD_LEFT), $entry->entry_number);
        $this->assertEquals('Owner contribution', $entry->description);
        $this->assertEquals(1000.0, $entry->total());

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $cash->id,
            'debit' => 1000,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $equity->id,
            'debit' => 0,
            'credit' => 1000,
        ]);
        $this->assertCount(2, $entry->lines);
    }

    public function test_a_single_line_entry_is_rejected(): void
    {
        $cash = Account::where('code', '1010')->firstOrFail();

        $this->actingAs($this->admin())
            ->post('/erp/accounting/journal', [
                'journal_date' => now()->toDateString(),
                'description' => 'Not a journal',
                'lines' => [
                    ['account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
                ],
            ])
            ->assertSessionHasErrors('lines');

        $this->assertDatabaseCount('journal_entries', 15);
    }

    public function test_an_unbalanced_entry_is_rejected(): void
    {
        $cash = Account::where('code', '1010')->firstOrFail();
        $equity = Account::where('code', '3010')->firstOrFail();

        $this->actingAs($this->admin())
            ->post('/erp/accounting/journal', [
                'journal_date' => now()->toDateString(),
                'description' => 'Unbalanced entry',
                'lines' => [
                    ['account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
                    ['account_id' => $equity->id, 'debit' => 0, 'credit' => 90],
                ],
            ])
            ->assertSessionHasErrors('lines');

        $this->assertDatabaseCount('journal_entries', 15);
        $this->assertDatabaseCount('journal_entry_lines', 30);
    }

    public function test_an_entry_line_must_reference_an_active_account(): void
    {
        $inactive = Account::factory()->create(['is_active' => false]);
        $equity = Account::where('code', '3010')->firstOrFail();

        $this->actingAs($this->admin())
            ->post('/erp/accounting/journal', [
                'journal_date' => now()->toDateString(),
                'description' => 'Bad account',
                'lines' => [
                    ['account_id' => $inactive->id, 'debit' => 100, 'credit' => 0],
                    ['account_id' => $equity->id, 'debit' => 0, 'credit' => 100],
                ],
            ])
            ->assertSessionHasErrors('lines.0.account_id');

        $this->assertDatabaseCount('journal_entries', 15);
    }

    public function test_an_entry_with_zero_amounts_is_rejected(): void
    {
        $cash = Account::where('code', '1010')->firstOrFail();
        $equity = Account::where('code', '3010')->firstOrFail();

        $this->actingAs($this->admin())
            ->post('/erp/accounting/journal', [
                'journal_date' => now()->toDateString(),
                'description' => 'Nothing posted',
                'lines' => [
                    ['account_id' => $cash->id, 'debit' => 0, 'credit' => 0],
                    ['account_id' => $equity->id, 'debit' => 0, 'credit' => 0],
                ],
            ])
            ->assertSessionHasErrors('lines');

        $this->assertDatabaseCount('journal_entries', 15);
    }
}
