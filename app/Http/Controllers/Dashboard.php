<?php
namespace App\Http\Controllers;

use App\Models\Remittance;
use App\Models\Submission;
use App\Models\User;
use App\Models\ApiLog;
use Illuminate\Support\Number;

class Dashboard extends Controller
{
    public function index()
    {
        $params = [
            'total_users' => User::count(),
            'total_api_requests' => ApiLog::count(),
            'api_logs' => ApiLog::limit(100)->with(['user'])->get(),
            'latest_users' => User::limit(9)->orderBy('created_at', 'desc')->get(),
            'total_remittances' => Remittance::count(),
            'total_submissions' => Submission::count(),
            'total_corn_remittances' => Remittance::where(['type' => 'corn'])->count(),
            'total_soybean_remittances' => Remittance::where(['type' => 'soybean'])->count(),
            'sum_corn_remittance_totals' => Number::currency(Remittance::where(['type' => 'corn'])->sum('total')),
            'sum_soybean_remittance_totals' => Number::currency(Remittance::where(['type' => 'soybean'])->sum('total')),
        ];

        return view('dashboard', $params);
    }

    public function chart()
    {
        $continuous = app('api')->getDashboardGraph();
        return response()->json([
            'labels' => $continuous->keys()->values(),
            'data'   => $continuous->values(),
        ]);
    }
}
