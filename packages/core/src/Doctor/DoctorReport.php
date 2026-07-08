<?php

namespace AdAstra\Doctor;

/**
 * Aggregated outcome of a doctor run: every check's results plus the
 * summary math and exit-code policy.
 */
final class DoctorReport
{
    /**
     * @var list<array{id: string, category: string, name: string, results: list<DoctorResult>}>
     */
    private array $entries = [];

    /**
     * @param list<DoctorResult> $results
     */
    public function add(DoctorCheck $check, array $results): void
    {
        $this->entries[] = [
            'id' => $check->id(),
            'category' => $check->category(),
            'name' => $check->name(),
            'results' => $results,
        ];
    }

    /**
     * @return list<array{id: string, category: string, name: string, results: list<DoctorResult>}>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Entries grouped by category, preserving execution order.
     *
     * @return array<string, list<array{id: string, category: string, name: string, results: list<DoctorResult>}>>
     */
    public function byCategory(): array
    {
        $grouped = [];
        foreach ($this->entries as $entry) {
            $grouped[$entry['category']][] = $entry;
        }

        return $grouped;
    }

    public function passed(): int
    {
        return $this->count(DoctorStatus::Pass);
    }

    public function warnings(): int
    {
        return $this->count(DoctorStatus::Warn);
    }

    public function failures(): int
    {
        return $this->count(DoctorStatus::Fail);
    }

    public function skipped(): int
    {
        return $this->count(DoctorStatus::Skip);
    }

    /**
     * 0 healthy · 1 warnings under --strict · 2 failures. Failures always
     * win regardless of flags.
     */
    public function exitCode(bool $strict = false): int
    {
        return match (true) {
            $this->failures() > 0 => 2,
            $strict && $this->warnings() > 0 => 1,
            default => 0,
        };
    }

    private function count(DoctorStatus $status): int
    {
        $total = 0;
        foreach ($this->entries as $entry) {
            foreach ($entry['results'] as $result) {
                if ($result->status === $status) {
                    $total++;
                }
            }
        }

        return $total;
    }
}
