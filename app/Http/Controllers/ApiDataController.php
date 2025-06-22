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
        $response = Http::get('https://3c67c548-a6d1-4bcb-a6ae-fc297157efe6.mock.pstmn.io//api/v2/issues');

        if ($response->successful()) {
            $data = collect($response->json()) -> map(function($item) {
                return [
                    'id' => $item['id'],
                    'title' => $item['summary'],
                ];
            });

            return Inertia::render('Import/Import',[
                'apiData' => $data,
            ]);
        } else {
            return Inertia::render('Import/Import',[
                'error' => 'データの取得に失敗しました',
            ]);
        }
    }
}
