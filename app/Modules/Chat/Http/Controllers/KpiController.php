<?php

namespace App\Modules\Chat\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

class KpiController extends Controller
{
public function analyze(Request $request)
{
    $request->validate(['question' => 'required|string']);

    try {
        $response = Http::timeout(300)->post('http://127.0.0.1:9000', [
            'user_id'  => auth()->id() ?? 1,
            'question' => $request->question,
            'mode'=>'kpi',
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Le service Python a retourné une erreur.'], 502);
        }

        return response()->json($response->json());

    } catch (\Exception $e) {
        return response()->json(['error' => 'Service KPI injoignable : ' . $e->getMessage()], 503);
    }
}
}
