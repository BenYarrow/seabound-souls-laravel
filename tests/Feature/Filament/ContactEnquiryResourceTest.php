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
