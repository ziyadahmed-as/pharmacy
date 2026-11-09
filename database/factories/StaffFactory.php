<?php

namespace Database\Factories;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Staff> */
class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '09'.fake()->numerify('########'),
            'address' => fake()->streetAddress(),
            'id_number' => fake()->numerify('ID-########'),
            'title' => fake()->randomElement(['Pharmacist', 'Pharmacy technician', 'Cashier']),
            'hire_date' => now()->subYears(2)->toDateString(),
            'status' => Staff::ACTIVE,
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => '09'.fake()->numerify('########'),
        ];
    }

    public function ended(): static
    {
        return $this->state(fn () => ['status' => Staff::ENDED, 'end_date' => now()->toDateString()]);
    }
}
