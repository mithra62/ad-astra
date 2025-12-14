<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rest\Client;
use Illuminate\Http\Request;

class Index extends Controller
{
    public function index()
    {
        return redirect('/login');
        $client = new Client;
        $data = $client->get('remittances/soybean');
        echo 'f';
        print_r($data);
        exit;
        return redirect('/login');

//        $user = User::find(7);
//        foreach ($user->tokens as $token) {
//            print_r($token->token);
//        }
//
//        exit;
//        $user->delete();
        return view('welcome');
    }
}
