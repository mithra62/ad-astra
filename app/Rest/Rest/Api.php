<?php
namespace App\Rest\Rest;

use App\Models\ApiLog;

class Api
{
    public function getDashboardGraph()
    {
        $start = now()->subDays(14)->startOfDay();
        $end   = now()->endOfDay();

        // 1. Retrieve aggregated data from database
        $raw = ApiLog::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');
        // produces: ['2025-02-01' => 5, '2025-02-03' => 8, …]

        // 2. Build continuous date range
        $continuous = collect();
        $date = $start->clone();

        while ($date->lte($end)) {
            $formatted = $date->format('Y-m-d');
            $continuous->put(
                $formatted,
                $raw->get($formatted, 0)   // default to 0 if missing
            );
            $date->addDay();
        }

        return $continuous;
    }
}
