<?php

namespace App\Http\Controllers;

//use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class ApiDataController extends Controller
{
    //
    public function fetchData()
    {
        $url = 'https://3c67c548-a6d1-4bcb-a6ae-fc297157efe6.mock.pstmn.io';
        $response = Http::get($url . '//api/v2/issues');

        if ($response->successful()) {
            $data = collect($response->json()) -> map(function($item) {
                return [
                    'id' => $item['id'],
                    'title' => $item['summary'],
                    'url' => $item['url'],
                ];
            });


            return Inertia::render('import/Import',[
                'apiData' => $data,
            ]);
        } else {
            return Inertia::render('import/Import',[
                'error' => 'データの取得に失敗しました',
            ]);
        }
    }
}
