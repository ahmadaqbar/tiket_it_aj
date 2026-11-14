<?php

namespace Tests\Unit;

use App\Enums\RoleCode;
use PHPUnit\Framework\TestCase;

class RoleCodeTest extends TestCase
{
    public function test_role_hierarchy_and_it_staff_flag(): void
    {
        $this->assertSame([1, 2, 3], array_map(fn (RoleCode $r) => $r->level(), RoleCode::cases()));
        $this->assertFalse(RoleCode::USER->isItStaff());
        $this->assertTrue(RoleCode::IT->isItStaff());
        $this->assertTrue(RoleCode::IT_ADMIN->isItStaff());
    }
}
