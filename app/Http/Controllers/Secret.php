<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;


class Secret extends Controller
{
    public function create(){
        return view('secret.create');
    }

    public function show($key) {
        $my_secret = Redis::get($key);
        Redis::del($key);

        if ($my_secret) {
            $my_secret = decrypt($my_secret);
        }else {
            $my_secret = '';
        }

        return view('secret.show', ['secret' => $my_secret]);
    }

    public function store(Request $request){
        $validatedData = $request->validate([
            'secret' => 'required'
        ]);

        $my_secret = $request->get('secret');

        //Encode secret.
        $my_secret = encrypt($my_secret);

        //Generate Key
        $key = str_random(20);

        //Save to redis
        Redis::set($key, $my_secret, 'EX', 60);

        return back()->with('success', $key);

    }
}
