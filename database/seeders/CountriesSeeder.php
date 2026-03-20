<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CountriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            ['name' => 'Canary Islands', 'slug' => 'canary-islands', 'continent' => 'africa'],
            ['name' => 'Egypt', 'slug' => 'egypt', 'continent' => 'africa'],
            ['name' => 'Greece', 'slug' => 'greece', 'continent' => 'europe'],
            ['name' => 'Mauritius', 'slug' => 'mauritius', 'continent' => 'africa'],
            ['name' => 'Morocco', 'slug' => 'morocco', 'continent' => 'africa'],
            ['name' => 'South Africa', 'slug' => 'south-africa', 'continent' => 'africa'],
        ];

        foreach ($countries as $country) {
            \App\Models\Country::updateOrCreate(
                ['slug' => $country['slug']],
                $country
            );
        }
    }
}
