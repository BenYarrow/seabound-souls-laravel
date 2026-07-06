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
