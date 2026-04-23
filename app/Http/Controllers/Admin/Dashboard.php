<?php

namespace App\Http\Controllers\Admin;

use App\Facades\Content;
use App\Models\ApiLog;
use App\Models\Entry;
use App\Models\User;
use App\Rest\Client;
use Illuminate\Support\Number;

class Dashboard extends Controller
{
    public function index()
    {
        $content = Content::query()->inGroup('products')->published()->first();
        // print_r($content);

        $entry = Entry::inGroup('products')->where('handle', 'the-pragmatic-programmer')->first();

        //        $entry = Entry::where('handle', 'the-pragmatic-programmer')
        //            ->with('entryGroup')
        //            ->first()
        //            ?->entryGroup
        //            ?->handle; // e.g. "blog_posts" not "blog"
        //        print_r($entry);
        //        exit;

        //        $post = Content::query()
        //            ->inGroup(2)
        //            ->where('handle', 'the-pragmatic-programmer')
        //            ->firstOrFail();
        //
        //        print_r($post);
        //        exit;

        //        $entry = Content::find(1);
        //        echo $entry->field('body');
        //        print_r($entry);
        //        exit;
        //        $client = new Client();
        //        $data = $client->get('v1/users');
        //
        //        print_r($data);
        //        exit;
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
