<?php

use Illuminate\Support\Facades\Route;
use App\Modules\User\Http\Controllers\UserController;
use App\Modules\Chat\Http\Controllers\ChatController;
use App\Modules\Chat\Http\Controllers\GeneralChatController;
use Illuminate\Http\Request;
use App\Modules\Chat\Http\Controllers\KpiController;



Route::post('/kpi/analyze', [KpiController::class, 'analyze']);

Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/ask', function (\Illuminate\Http\Request $request) {
    return \App\Services\RagService::ask(
        $request->question,
        $request->file
    );
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [UserController::class, 'logout']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/conversations', [ChatController::class, 'getConversations']);
    Route::post('/conversations', [ChatController::class, 'createConversation']);
    Route::get('/conversations/{id}/messages', [ChatController::class, 'getMessages']);
    Route::post('/conversations/{id}/messages', [ChatController::class, 'sendMessage']);
    Route::delete('/conversations/{id}', [ChatController::class, 'deleteConversation']);
    Route::post('/general/conversations',    [GeneralChatController::class, 'createConversation']);
    Route::get('/general/{id}/messages',     [GeneralChatController::class, 'getMessages']);
    Route::post('/general/{id}/messages',    [GeneralChatController::class, 'sendMessage']);
});

