<?php

namespace AdAstra\Doctor;

/**
 * A single installation diagnostic. Implementations must be read-only,
 * secrets-safe, and environment-sensitive (SKIP — never FAIL — when the
 * check does not apply). Most checks should extend AbstractDoctorCheck
 * rather than implement this directly; see docs/DOCTOR_EXTENDING.md.
 *
 * Register implementations by tagging them in a service provider:
 *
 *     $this->app->tag([MyCheck::class], 'adastra.doctor.checks');
 */
interface DoctorCheck
{
    /**
     * Stable machine ID in "category.check-name" form, e.g.
     * "database.required-tables". Never changes once shipped.
     */
    public function id(): string;

    /**
     * Category the check reports under, e.g. "database".
     */
    public function category(): string;

    /**
     * Human-readable name shown in the report.
     */
    public function name(): string;

    /**
     * Check IDs (or category IDs) that must pass before this check runs.
     * When a dependency fails or is skipped, this check is reported as
     * SKIP instead of executing.
     *
     * @return list<string>
     */
    public function dependsOn(): array;

    /**
     * Execute the diagnostic. Yield one DoctorResult per finding; yielding
     * nothing is treated as a pass.
     *
     * @return iterable<DoctorResult>
     */
    public function run(): iterable;
}
