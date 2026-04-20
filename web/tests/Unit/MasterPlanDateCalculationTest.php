<?php

namespace Tests\Unit;

use App\Http\Controllers\MasterPlanController;
use PHPUnit\Framework\TestCase;
use Carbon\Carbon;

class MasterPlanDateCalculationTest extends TestCase
{
    public function test_finish_sew_includes_first_opt_as_day_1(): void
    {
        $controller = new MasterPlanController();

        // FirstOPT = 2026-04-13, LT = 3 means count days 13, 14, 15 = 3 days
        $finish = $controller->calcFinishSew('2026-04-13', 3, []);

        $this->assertSame('2026-04-15', $finish->toDateString());
    }

    public function test_finish_sew_skips_sunday_and_cascading_holidays(): void
    {
        $controller = new MasterPlanController();

        // FirstOPT = 2026-04-24 (Fri), LT = 3
        $finish = $controller->calcFinishSew('2026-04-24', 3, [
            '2026-04-27',
            '2026-04-28',
            '2026-04-29',
            '2026-04-30',
            '2026-05-01',
            '2026-05-02',
        ]);

        $this->assertSame('2026-05-04', $finish->toDateString());
    }

    public function test_finish_sew_with_zero_days_returns_start_date(): void
    {
        $controller = new MasterPlanController();

        $finish = $controller->calcFinishSew('2026-04-13', 0, []);

        $this->assertSame('2026-04-13', $finish->toDateString());
    }

    public function test_ex_fact_skips_non_working_days_until_stable(): void
    {
        $controller = new MasterPlanController();

        $exFact = $controller->calcExFact('2026-04-29', 3, [
            '2026-04-30',
            '2026-05-01',
            '2026-05-02',
        ]);

        $this->assertSame('2026-05-06', $exFact->toDateString());
    }

    public function test_skip_sunday_adjusts_sunday_to_monday(): void
    {
        $controller = new MasterPlanController();

        // 2026-04-26 is Sunday
        $sunday = Carbon::parse('2026-04-26');
        $this->assertTrue($sunday->isSunday());

        $adjusted = $this->invokePrivateMethod($controller, 'skipSunday', [$sunday]);
        $this->assertSame('2026-04-27', $adjusted->toDateString());
        $this->assertTrue($adjusted->isMonday());
    }

    public function test_skip_sunday_leaves_non_sunday_unchanged(): void
    {
        $controller = new MasterPlanController();

        $friday = Carbon::parse('2026-04-24');
        $this->assertFalse($friday->isSunday());

        $adjusted = $this->invokePrivateMethod($controller, 'skipSunday', [$friday]);
        $this->assertSame('2026-04-24', $adjusted->toDateString());
    }

    public function test_finish_sew_ending_on_sunday_gets_adjusted(): void
    {
        $controller = new MasterPlanController();

        // 2026-04-26 is Sunday, so calcFinishSew('2026-04-26', 0) = 2026-04-26 (Sunday)
        // With skipSunday applied, it should become 2026-04-27 (Monday)
        $result = $this->invokePrivateMethod($controller, 'skipSunday', [
            $controller->calcFinishSew('2026-04-26', 0, [])
        ]);

        $this->assertSame('2026-04-27', $result->toDateString());
        $this->assertTrue($result->isMonday());
    }

    private function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
