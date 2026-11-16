<?php

namespace Tests\Integration\Repository;

use App\Models\User;
use App\Repository\Contracts\UserRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_create_instead_of_misusing_eloquent_save_options(): void
    {
        $repo = app(UserRepositoryContract::class);
        $created = $repo->create([
            'name' => 'Test Administrator',
            'email' => 'admin@planner.test',
            'password' => 'secure-dummy-password',
        ]);

        $this->assertInstanceOf(User::class, $created);
        $this->assertTrue($created->exists);
        $this->assertSame($created->id, $repo->findByEmail('admin@planner.test')?->id);
        $this->assertNull($repo->findByEmail('absent@planner.test'));
    }
}
