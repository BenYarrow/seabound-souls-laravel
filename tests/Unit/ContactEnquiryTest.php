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
