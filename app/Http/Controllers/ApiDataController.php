<?php

namespace App\Http\Controllers;

//use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ApiDataController extends Controller
{
    //
    public function fetchData()
    {
        $response = Http::get('https://3c67c548-a6d1-4bcb-a6ae-fc297157efe6.mock.pstmn.io//api/v2/issues');

        if ($response->successful()) {
            dd($response->json());
            exit;
            $data = $response->json();
            return view('data.show', ['data' => $data]);
        } else {
            return response()->json(['error' => 'データの取得に失敗しました']);
        }
    }
}
