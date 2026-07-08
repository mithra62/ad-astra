<?php

namespace Tests\Unit\Doctor;

use AdAstra\Doctor\AbstractDoctorCheck;
use AdAstra\Doctor\DoctorReport;
use AdAstra\Doctor\DoctorResult;
use PHPUnit\Framework\TestCase;

class DoctorReportTest extends TestCase
{
    private function reportWith(DoctorResult ...$results): DoctorReport
    {
        $check = new class () extends AbstractDoctorCheck {
            protected string $id = 'test.check';
            protected string $name = 'Test check';

            public function run(): iterable
            {
                return [];
            }
        };

        $report = new DoctorReport();
        $report->add($check, array_values($results));

        return $report;
    }

    public function test_healthy_report_exits_zero(): void
    {
        $report = $this->reportWith(DoctorResult::pass('ok'));

        $this->assertSame(0, $report->exitCode(strict: false));
        $this->assertSame(0, $report->exitCode(strict: true));
    }

    public function test_warnings_exit_zero_unless_strict(): void
    {
        $report = $this->reportWith(DoctorResult::warn('careful'));

        $this->assertSame(0, $report->exitCode(strict: false));
        $this->assertSame(1, $report->exitCode(strict: true));
    }

    public function test_failures_always_exit_two(): void
    {
        $report = $this->reportWith(DoctorResult::warn('careful'), DoctorResult::fail('broken'));

        $this->assertSame(2, $report->exitCode(strict: false));
        $this->assertSame(2, $report->exitCode(strict: true));
    }

    public function test_skips_do_not_affect_exit_code(): void
    {
        $report = $this->reportWith(DoctorResult::skip('n/a'));

        $this->assertSame(0, $report->exitCode(strict: false));
        $this->assertSame(0, $report->exitCode(strict: true));
    }

    public function test_summary_counts(): void
    {
        $report = $this->reportWith(
            DoctorResult::pass('ok'),
            DoctorResult::warn('careful'),
            DoctorResult::warn('careful again'),
            DoctorResult::fail('broken'),
            DoctorResult::skip('n/a'),
        );

        $this->assertSame(1, $report->passed());
        $this->assertSame(2, $report->warnings());
        $this->assertSame(1, $report->failures());
        $this->assertSame(1, $report->skipped());
    }
}
