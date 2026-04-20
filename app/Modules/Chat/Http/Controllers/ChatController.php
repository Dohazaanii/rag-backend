<?php

namespace App\Modules\Chat\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use Symfony\Component\Process\Process;

class ChatController extends Controller
{
    public function getConversations(Request $request)
    {
        $conversations = Conversation::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($conversations);
    }

    public function createConversation(Request $request)
    {
        $conversation = Conversation::create([
            'user_id' => $request->user()->id,
            'title'   => 'Nouvelle conversation',
        ]);

        return response()->json($conversation);
    }

    public function getMessages($id)
    {
        $messages = Message::where('conversation_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($messages);
    }

    public function sendMessage(Request $request, $id)
    {
        set_time_limit(120);

        $request->validate([
            'content' => 'required|string',
        ]);

        // 1. ✅ Sauvegarder le message USER en premier
        Message::create([
            'conversation_id' => $id,
            'role'            => 'user',
            'content'         => $request->content,
        ]);

        // 2. ✅ Appeler le script Python RAG
        try {
            $process = new Process([
                env('PYTHON_PATH', 'python'),
                env('RAG_SCRIPT_PATH'),
                $request->content
            ]);

            $process->setTimeout(120);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getErrorOutput());
            }

            $output    = json_decode($process->getOutput(), true);
            $aiContent = $output['answer'] ?? 'Pas de réponse.';

        } catch (\Exception $e) {
            $aiContent = '❌ Erreur RAG : ' . $e->getMessage();
        }

        // 3. ✅ Sauvegarder la réponse IA
        $aiMessage = Message::create([
            'conversation_id' => $id,
            'role'            => 'assistant',
            'content'         => $aiContent,
        ]);

        // 4. ✅ Mettre à jour le titre
        $conversation = Conversation::find($id);
        if ($conversation->title === 'Nouvelle conversation') {
            $conversation->update([
                'title' => substr($request->content, 0, 50),
            ]);
        }

        return response()->json($aiMessage);
    }

    public function deleteConversation($id)
    {
        Conversation::find($id)->delete();
        return response()->json(['message' => 'Conversation supprimée']);
    }
}