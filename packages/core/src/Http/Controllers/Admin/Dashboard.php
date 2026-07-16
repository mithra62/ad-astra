<?php

namespace AdAstra\Http\Controllers\Admin;

use AdAstra\Enums\UserStatus;
use AdAstra\Facades\Users;
use AdAstra\Models\ApiLog;
use AdAstra\Models\Entry;
use AdAstra\Models\EntryGroup;
use AdAstra\Models\Media;
use AdAstra\Models\User;
use Illuminate\Support\Facades\DB;

class Dashboard extends Controller
{
    public function index()
    {
        $totalEntries = Entry::count();
        $publishedCount = Entry::published()->count();

        $params = [
            'total_entries' => $totalEntries,
            'published_entries' => $publishedCount,
            'draft_entries' => $totalEntries - $publishedCount,
            'total_users' => Users::getTotalCount(),
            'active_users' => User::where('status', UserStatus::ACTIVE)->count(),
            'total_media' => Media::count(),
            'total_api_requests' => ApiLog::count(),
            'recent_api_errors' => ApiLog::where('response_status_code', '>=', 500)
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'entry_groups' => EntryGroup::withCount('entries')->ordered()->get(),
            'recent_entries' => Entry::latest()
                ->with(['creator', 'entryGroup'])
                ->limit(10)
                ->get(),
            'top_api_routes' => ApiLog::select(
                'request_route',
                DB::raw('count(*) as hits'),
                DB::raw('sum(case when response_status_code >= 500 then 1 else 0 end) as errors')
            )
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('request_route')
                ->orderByDesc('hits')
                ->limit(5)
                ->get(),
            'api_logs' => ApiLog::latest()->limit(20)->with(['user'])->get(),
        ];

        return $this->view('dashboard', $params);
    }

    public function chart()
    {
        $continuous = app('api')->getDashboardGraph();

        return response()->json([
            'labels' => $continuous->keys()->values(),
            'data' => $continuous->values(),
        ]);
    }
}
