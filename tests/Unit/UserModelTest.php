<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserModelTest extends TestCase
{
    public function test_role_helpers_recognize_admin_and_roles(): void
    {
        $admin = new User([
            'role' => User::ROLE_ADMIN,
        ]);

        $member = new User([
            'role' => User::ROLE_PPIC,
        ]);

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->hasRole(User::ROLE_ADMIN, User::ROLE_IE));
        $this->assertFalse($admin->hasRole(User::ROLE_PPIC));

        $this->assertFalse($member->isAdmin());
        $this->assertTrue($member->hasRole(User::ROLE_PPIC));
        $this->assertFalse($member->hasRole(User::ROLE_ADMIN, User::ROLE_IE));
    }
}