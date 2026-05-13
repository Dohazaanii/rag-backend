<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Modules\Chat\Http\Controllers\ChatController;
use App\Modules\Chat\Http\Controllers\KpiController;


Route::post('/kpi/analyze', [KpiController::class, 'analyze']);
Route::post('/conversations/{id}/messages', [ChatController::class, 'sendMessage']);