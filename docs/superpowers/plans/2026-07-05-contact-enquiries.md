# Contact Enquiries Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Capture contact-form submissions in the database and manage them in an in-app Filament inbox (New → Handled, with an unread nav badge), demoting the email to a notification.

**Architecture:** New `contact_enquiries` table + `ContactEnquiry` model; `ContactController@store` persists the enquiry before sending the notification email; a read-only `ContactEnquiryResource` (Filament) lists/views enquiries with a mark-handled action and a navigation badge counting new ones.

**Tech Stack:** Laravel 12, Filament v3.3, Livewire v3, PHPUnit 11, SQLite (in-memory for tests).

## Global Constraints

- Run `php artisan test` before considering any task done; all green.
- Scout tests set `config(['scout.driver' => 'collection'])` — not relevant here (no Searchable model added), but the base `Tests\TestCase` already applies `RefreshDatabase` + `withoutVite()`; new test classes inherit both (do not re-declare).
- Filament resource tests must `actingAs(User::factory()->create())` in `setUp()` (panel requires an authenticated user).
- PHPDoc / module-header comments per repo `CLAUDE.md`; comment the *why* where non-obvious.
- Never edit an already-run migration — this task adds a new one.
- Enquiries arrive only via the public form: the resource has no create/edit forms (`canCreate()` returns false).

---

### Task 1: `contact_enquiries` migration, `ContactEnquiry` model + factory

**Files:**
- Create: `database/migrations/<timestamp>_create_contact_enquiries_table.php` (via artisan)
- Create: `app/Models/ContactEnquiry.php`
- Create: `database/factories/ContactEnquiryFactory.php`
- Test: `tests/Unit/ContactEnquiryTest.php`

**Interfaces:**
- Produces: `App\Models\ContactEnquiry` with `$fillable = ['name','email','message','status','handled_at']`, cast `handled_at => datetime`, `status` values `'new'|'handled'` (default `'new'` via the DB column). `ContactEnquiry::factory()` (default `status='new'`, `handled_at=null`) with a `->handled()` state (`status='handled'`, `handled_at=now()`).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/ContactEnquiryTest.php`:

```php
<?php

// Unit tests for App\Models\ContactEnquiry — default status and datetime cast.

namespace Tests\Unit;

use App\Models\ContactEnquiry;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ContactEnquiryTest extends TestCase
{
    public function test_it_defaults_to_new_status_with_no_handled_timestamp(): void
    {
        $enquiry = ContactEnquiry::create([
            'name' => 'Jane Sailor',
            'email' => 'jane@example.com',
            'message' => 'When is the best time to visit Tarifa?',
        ]);

        $this->assertSame('new', $enquiry->fresh()->status);
        $this->assertNull($enquiry->fresh()->handled_at);
    }

    public function test_handled_at_is_cast_to_a_datetime(): void
    {
        $enquiry = ContactEnquiry::factory()->handled()->create();

        $this->assertInstanceOf(Carbon::class, $enquiry->fresh()->handled_at);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ContactEnquiryTest`
Expected: FAIL — `Class "App\Models\ContactEnquiry" not found` (and no table).

- [ ] **Step 3: Create the migration**

Run: `php artisan make:migration create_contact_enquiries_table`

Then set its `up()` body to:

```php
Schema::create('contact_enquiries', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email');
    $table->text('message');
    // Workflow state for the admin inbox — new until an admin marks it handled.
    $table->enum('status', ['new', 'handled'])->default('new');
    $table->timestamp('handled_at')->nullable();
    $table->timestamps();
});
```

(Leave the generated `down()` — `Schema::dropIfExists('contact_enquiries');`.)

- [ ] **Step 4: Create the model**

Create `app/Models/ContactEnquiry.php`:

```php
<?php

// A contact-form submission, captured in the app so it's never lost if mail
// fails. Managed from the Filament admin (ContactEnquiryResource) via a simple
// new -> handled status.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactEnquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'email', 'message', 'status', 'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/ContactEnquiryFactory.php`:

```php
<?php

// Factory for App\Models\ContactEnquiry — a new (unhandled) enquiry by default;
// ->handled() produces a handled one for status/badge tests.

namespace Database\Factories;

use App\Models\ContactEnquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactEnquiry>
 */
class ContactEnquiryFactory extends Factory
{
    protected $model = ContactEnquiry::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'message' => $this->faker->paragraph(),
            'status' => 'new',
            'handled_at' => null,
        ];
    }

    public function handled(): static
    {
        return $this->state(fn () => [
            'status' => 'handled',
            'handled_at' => now(),
        ]);
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=ContactEnquiryTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models/ContactEnquiry.php database/factories/ContactEnquiryFactory.php tests/Unit/ContactEnquiryTest.php
git commit -m "Add ContactEnquiry model, migration, factory"
```

---

### Task 2: Persist enquiries in `ContactController@store`

**Files:**
- Modify: `app/Http/Controllers/ContactController.php`
- Test: `tests/Feature/ContactControllerTest.php` (extend)

**Interfaces:**
- Consumes: `App\Models\ContactEnquiry` (Task 1); existing `ContactControllerTest` helpers `validPayload(array $overrides = [])` and `fakeRecaptcha(bool $success)`.

- [ ] **Step 1: Write the failing tests**

Add these methods to `tests/Feature/ContactControllerTest.php` (and add `use App\Models\ContactEnquiry;` to the imports):

```php
    public function test_store_persists_the_enquiry_on_success(): void
    {
        Mail::fake();
        $this->fakeRecaptcha(true);

        $this->post(route('contact.store'), $this->validPayload([
            'name' => 'Jane Sailor',
            'email' => 'jane@example.com',
        ]));

        $this->assertDatabaseHas('contact_enquiries', [
            'name' => 'Jane Sailor',
            'email' => 'jane@example.com',
            'status' => 'new',
        ]);
        Mail::assertSent(ContactFormMail::class);
    }

    public function test_store_does_not_persist_when_recaptcha_fails(): void
    {
        Mail::fake();
        $this->fakeRecaptcha(false);

        $this->post(route('contact.store'), $this->validPayload());

        $this->assertDatabaseCount('contact_enquiries', 0);
        Mail::assertNothingSent();
    }

    public function test_store_does_not_persist_on_validation_failure(): void
    {
        $this->post(route('contact.store'), []);

        $this->assertDatabaseCount('contact_enquiries', 0);
    }
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --filter=ContactControllerTest`
Expected: FAIL — `test_store_persists_the_enquiry_on_success` fails (`contact_enquiries` has 0 rows / table asserts fail) because the controller doesn't persist yet. (The two negative tests may pass trivially since nothing is created — that's fine; the positive test is the RED.)

- [ ] **Step 3: Persist in the controller**

In `app/Http/Controllers/ContactController.php`, add the import `use App\Models\ContactEnquiry;`, then in `store()` — after the reCAPTCHA success check and **before** the `Mail::to(...)` call — insert:

```php
        // Persist the enquiry before notifying, so it's captured even if mail fails.
        $data = $request->validated();

        ContactEnquiry::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'message' => $data['message'],
        ]);
```

Then change the existing mail line to reuse `$data`:

```php
        Mail::to(config('mail.to.address', 'hello@seaboundsouls.com'))
            ->send(new ContactFormMail($data));
```

(Update the class-level PHPDoc on `store()` to mention it now records the enquiry before emailing.)

- [ ] **Step 4: Run to verify they pass**

Run: `php artisan test --filter=ContactControllerTest`
Expected: PASS (all ContactControllerTest tests, including the 3 new ones).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ContactController.php tests/Feature/ContactControllerTest.php
git commit -m "Persist contact enquiries before sending notification"
```

---

### Task 3: `ContactEnquiryResource` (Filament inbox) + unread badge

**Files:**
- Create: `app/Filament/Resources/ContactEnquiryResource.php`
- Create: `app/Filament/Resources/ContactEnquiryResource/Pages/ListContactEnquiries.php`
- Create: `app/Filament/Resources/ContactEnquiryResource/Pages/ViewContactEnquiry.php`
- Test: `tests/Feature/Filament/ContactEnquiryResourceTest.php`

**Interfaces:**
- Consumes: `App\Models\ContactEnquiry` + factory (Task 1).
- Produces: `ContactEnquiryResource::getNavigationBadge(): ?string` (count of `status='new'`, or null when zero); table actions `markHandled` / `markNew`; pages `index` (list) and `view`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/ContactEnquiryResourceTest.php`:

```php
<?php

// Feature tests for the Filament ContactEnquiryResource — the admin enquiry
// inbox. Acts as an authenticated user (the panel requires one).

namespace Tests\Feature\Filament;

use App\Filament\Resources\ContactEnquiryResource;
use App\Filament\Resources\ContactEnquiryResource\Pages\ListContactEnquiries;
use App\Models\ContactEnquiry;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class ContactEnquiryResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_list_page_lists_enquiries(): void
    {
        $enquiry = ContactEnquiry::factory()->create(['name' => 'Jane Sailor']);

        Livewire::test(ListContactEnquiries::class)
            ->assertCanSeeTableRecords([$enquiry])
            ->assertSee('Jane Sailor');
    }

    public function test_mark_handled_action_sets_status_and_timestamp(): void
    {
        $enquiry = ContactEnquiry::factory()->create(['status' => 'new']);

        Livewire::test(ListContactEnquiries::class)
            ->callTableAction('markHandled', $enquiry);

        $enquiry->refresh();
        $this->assertSame('handled', $enquiry->status);
        $this->assertNotNull($enquiry->handled_at);
    }

    public function test_navigation_badge_counts_new_enquiries(): void
    {
        ContactEnquiry::factory()->count(2)->create(['status' => 'new']);
        ContactEnquiry::factory()->handled()->create();

        $this->assertSame('2', ContactEnquiryResource::getNavigationBadge());
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=ContactEnquiryResourceTest`
Expected: FAIL — `Class "App\Filament\Resources\ContactEnquiryResource" not found`.

- [ ] **Step 3: Create the resource**

Create `app/Filament/Resources/ContactEnquiryResource.php`:

```php
<?php

// Filament admin inbox for contact-form enquiries. Read-only (submissions
// arrive via the public form, never created here); supports viewing, a
// new<->handled status toggle, a status filter, and a nav badge counting
// unhandled enquiries.

namespace App\Filament\Resources;

use App\Filament\Resources\ContactEnquiryResource\Pages;
use App\Models\ContactEnquiry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactEnquiryResource extends Resource
{
    protected static ?string $model = ContactEnquiry::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Enquiries';

    protected static ?int $navigationSort = 5;

    /** Enquiries are only created by the public contact form. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Count of unhandled enquiries, shown as a sidebar badge (null hides it). */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'new')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('message')->limit(60)->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'new' ? 'warning' : 'gray'),
                TextColumn::make('created_at')->label('Received')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['new' => 'New', 'handled' => 'Handled']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('markHandled')
                    ->label('Mark handled')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ContactEnquiry $record): bool => $record->status === 'new')
                    ->action(fn (ContactEnquiry $record) => $record->update([
                        'status' => 'handled',
                        'handled_at' => now(),
                    ])),
                Tables\Actions\Action::make('markNew')
                    ->label('Mark as new')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (ContactEnquiry $record): bool => $record->status === 'handled')
                    ->action(fn (ContactEnquiry $record) => $record->update([
                        'status' => 'new',
                        'handled_at' => null,
                    ])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('name'),
            TextEntry::make('email')->copyable(),
            TextEntry::make('message')->columnSpanFull(),
            TextEntry::make('status')
                ->badge()
                ->color(fn (string $state): string => $state === 'new' ? 'warning' : 'gray'),
            TextEntry::make('created_at')->label('Received')->dateTime(),
            TextEntry::make('handled_at')->dateTime()->placeholder('—'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactEnquiries::route('/'),
            'view' => Pages\ViewContactEnquiry::route('/{record}'),
        ];
    }
}
```

- [ ] **Step 4: Create the pages**

Create `app/Filament/Resources/ContactEnquiryResource/Pages/ListContactEnquiries.php`:

```php
<?php

namespace App\Filament\Resources\ContactEnquiryResource\Pages;

use App\Filament\Resources\ContactEnquiryResource;
use Filament\Resources\Pages\ListRecords;

class ListContactEnquiries extends ListRecords
{
    protected static string $resource = ContactEnquiryResource::class;
}
```

Create `app/Filament/Resources/ContactEnquiryResource/Pages/ViewContactEnquiry.php`:

```php
<?php

namespace App\Filament\Resources\ContactEnquiryResource\Pages;

use App\Filament\Resources\ContactEnquiryResource;
use Filament\Resources\Pages\ViewRecord;

class ViewContactEnquiry extends ViewRecord
{
    protected static string $resource = ContactEnquiryResource::class;
}
```

- [ ] **Step 5: Run to verify it passes**

Run: `php artisan test --filter=ContactEnquiryResourceTest`
Expected: PASS (3 tests). If a test errors with a panel/tenancy or 403 issue, confirm the test's `setUp()` calls `actingAs(User::factory()->create())`.

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: PASS (all previous + Task 1/2/3 additions).

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/ContactEnquiryResource.php app/Filament/Resources/ContactEnquiryResource/Pages tests/Feature/Filament/ContactEnquiryResourceTest.php
git commit -m "Add ContactEnquiryResource: admin inbox + unread nav badge"
```

---

## Controller verification (after Task 2, by the controller of the run)

Not a task — a note for whoever runs the plan: after Task 2, a real submission through `https://seaboundsouls.test/contact` should create a row (visible in `/admin` → Enquiries) and drop a notification email into Herd's Mail tab. Browser verification of the admin inbox + badge happens after Task 3.

## Self-review notes

- **Spec coverage:** A (model) → Task 1; B (persist before notify) → Task 2; C (resource + mark-handled + filter + view + nav badge) → Task 3; D (email notification unchanged) → preserved in Task 2; Testing → each task's TDD + full-suite run.
- **Type consistency:** `ContactEnquiry` fillable/casts identical across model, factory, controller create, and tests. `status` values `'new'`/`'handled'` consistent everywhere. `getNavigationBadge()` returns `?string` and the test asserts the string `'2'`.
- **No placeholders:** every step carries concrete code/commands. Migration filename uses the artisan-generated timestamp (Step 3 of Task 1 creates it).
