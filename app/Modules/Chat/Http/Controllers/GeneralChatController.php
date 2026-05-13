<?php

namespace App\Modules\Chat\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use Illuminate\Support\Facades\Http;

class GeneralChatController extends Controller
{
    // ── Create a new general conversation ──────────────────────
    public function createConversation(Request $request)
    {
        $conversation = Conversation::create([
            'user_id' => $request->user()->id,
            'title'   => 'New conversation',
            'type'    => 'general',
            'mode' => 'general',  
        ]);

        return response()->json($conversation);
    }

    // ── Get messages for a conversation ────────────────────────
    public function getMessages($id)
    {
        $messages = Message::where('conversation_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($messages);
    }

    // ── Send a message and get Llama 3 reply ───────────────────
    public function sendMessage(Request $request, $id)
    {
        set_time_limit(300);

        $request->validate([
            'content' => 'required|string|max:4000',
        ]);

        $content = $request->input('content');

        // ── 1. Save user message ─────────────────────────────
        Message::create([
            'conversation_id' => $id,
            'role'            => 'user',
            'content'         => $content,
        ]);

        // ── 2. Build conversation history for context ─────────
        $history = Message::where('conversation_id', $id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($m) => [
                'role'    => $m->role,        // 'user' or 'assistant'
                'content' => $m->content,
            ])
            ->toArray();

        // ── 3. Call Llama 3 via Ollama ────────────────────────
        try {
            $ollamaResponse = Http::timeout(300)->post('http://127.0.0.1:11434/api/chat', [
                'model'    => 'llama3',
                'messages' => $history,
                'stream'   => false,           // get full reply at once
            ]);

            if (! $ollamaResponse->successful()) {
                throw new \Exception('Ollama returned status ' . $ollamaResponse->status());
            }

            $aiContent = $ollamaResponse->json('message.content')
                ?? 'No response from Llama 3.';

            // Sanitise to valid UTF-8
            $aiContent = mb_convert_encoding($aiContent, 'UTF-8', 'UTF-8');
            $aiContent = preg_replace(
                '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
                '',
                $aiContent
            );

        } catch (\Exception $e) {
            $aiContent = 'Llama 3 Error: ' . $e->getMessage();
        }

        // ── 4. Save AI message ───────────────────────────────
        $aiMessage = Message::create([
            'conversation_id' => $id,
            'role'            => 'assistant',
            'content'         => $aiContent,
        ]);

        // ── 5. Update conversation title on first message ─────
        $conversation = Conversation::find($id);
        if ($conversation && $conversation->title === 'New conversation') {
            $conversation->update([
                'title' => mb_substr($content, 0, 50),
            ]);
        }

        return response()->json([
            'id'      => $aiMessage->id,
            'role'    => 'assistant',
            'content' => $aiContent,
        ]);
    }
}