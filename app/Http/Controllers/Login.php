<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Submission;
class Login extends Controller
{
    public function index()
    {

        //call service provider object
        //echo get_class(app('craft-client'));
        //exit;
        $id = 4490;
        $submission = Submission::find($id);
        foreach($submission->usstate As $state) {
            print_r($state->title);
            echo '<br>';
            //exit;
        }

        foreach($submission->organization As $organization) {
            print_r($organization->title);
            echo '<br>';
            //exit;
        }

        echo 'fdsa';
        exit;
        return view('login');
    }
}
