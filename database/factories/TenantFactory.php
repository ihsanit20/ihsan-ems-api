<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $schoolName = fake()->company() . ' School';
        $domain = strtolower(str_replace(' ', '-', $schoolName)) . '.test';

        return [
            'name' => $schoolName,
            'domain' => $domain,
            'db_name' => 'tenant_' . str_replace(['-', '.'], '_', $domain),
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_username' => 'root',
            'db_password' => null,
            'is_active' => true,
            'branding' => null,
        ];
    }

    /**
     * Indicate that the tenant is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }
}
