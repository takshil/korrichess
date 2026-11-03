<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_mot_de_passe_est_hache(): void
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'secret-korrig',
        ]);

        $this->assertNotSame('secret-korrig', $user->password);
        $this->assertTrue(password_verify('secret-korrig', $user->password));
    }
}
