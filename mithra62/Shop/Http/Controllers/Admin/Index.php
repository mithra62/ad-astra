<?php

namespace mithra62\Shop\Http\Controllers\Admin;

use mithra62\Shop\Http\Controllers\Controller;
use mithra62\Shop\Rest\Rest\Client;

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
