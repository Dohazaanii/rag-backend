<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Modules\Chat\Http\Controllers\ChatController;

Route::post('/conversations/{id}/messages', [ChatController::class, 'sendMessage']);