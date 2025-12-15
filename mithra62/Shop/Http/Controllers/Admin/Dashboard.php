<?php
namespace mithra62\Shop\Http\Controllers\Admin;

use App\Models\Remittance;
use App\Models\Submission;
use Illuminate\Support\Number;
use mithra62\Shop\Http\Controllers\Controller;
use mithra62\Shop\Models\ApiLog;
use mithra62\Shop\Models\User;

class Dashboard extends Controller
{
    public function index()
    {
        $params = [
            'total_users' => User::count(),
            'total_api_requests' => ApiLog::count(),
            'api_logs' => ApiLog::limit(100)->with(['user'])->get(),
            'latest_users' => User::limit(9)->orderBy('created_at', 'desc')->get(),
            'total_remittances' => 0,
            'total_submissions' => 0,
            'total_corn_remittances' => 0,
            'total_soybean_remittances' => 0,
            'sum_corn_remittance_totals' => Number::currency(0),
            'sum_soybean_remittance_totals' => Number::currency(0),
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
