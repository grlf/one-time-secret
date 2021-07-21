<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class Secret extends Controller
{
    public function create()
    {
        return view('secret.create');
    }

    public function get(Request $request)
    {
        $validatedData = $request->validate([
            'key' => 'required'
        ]);

        $key = $request->get('key');
        $my_secret = Redis::get($key);
        Redis::del($key);

        if ($my_secret) {
            $my_secret = decrypt($my_secret);
            return response($my_secret, 200, ['Content-Type' => 'text/plain']);
        }

        return response(
            'Your secret cannot be found.  Please ask the sender to try again.',
            404,
            ['Content-Type' => 'text/plain']
        );
    }

    public function show($key)
    {
        return view('secret.show', compact('key'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'secret' => 'required',
            'expires' => 'required|integer'
        ]);

        $my_secret = $request->get('secret');
        $my_secret = encrypt($my_secret);

        //Generate Key
        $key = Str::random(20);

        $expires = $request->get('expires');
        //Don't let this sit around for longer than a week
        if ($expires > 604800) {
            $expires = 604800;
        }

        //Save to redis
        Redis::set($key, $my_secret, 'EX', $expires);

        return back()->with('success', $key);
    }
}
