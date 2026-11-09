<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Password123!'),
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_at' => null,
            'must_change_password' => false,
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'locked_at' => now(),
            'failed_login_attempts' => 5,
        ]);
    }

    public function deactivated(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'must_change_password' => true,
        ]);
    }

    public function withMfa(string $secret = '123456'): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
        ]);
    }
}
