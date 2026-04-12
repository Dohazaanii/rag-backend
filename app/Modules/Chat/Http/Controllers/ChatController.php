<?php

namespace App\Modules\Chat\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    // Récupérer toutes les conversations de l'utilisateur
    public function getConversations(Request $request)
    {
        $conversations = Conversation::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($conversations);
    }

    // Créer une nouvelle conversation
    public function createConversation(Request $request)
    {
        $conversation = Conversation::create([
            'user_id' => $request->user()->id,
            'title'   => 'Nouvelle conversation',
        ]);

        return response()->json($conversation);
    }

    // Récupérer les messages d'une conversation
    public function getMessages($id)
    {
        $messages = Message::where('conversation_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($messages);
    }

    // Envoyer un message et obtenir une réponse d'Ollama
    public function sendMessage(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        // Sauvegarder le message de l'utilisateur
        Message::create([
            'conversation_id' => $id,
            'role'            => 'user',
            'content'         => $request->content,
        ]);

        // Récupérer l'historique des messages
        $history = Message::where('conversation_id', $id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($m) => [
                'role'    => $m->role,
                'content' => $m->content,
            ]);

        // Appeler Ollama
        $response = Http::timeout(120)->post('http://localhost:11434/api/chat', [
            'model'    => 'llama3',
            'messages' => $history,
            'stream'   => false,
        ]);

        $aiContent = $response->json()['message']['content'];

        // Sauvegarder la réponse de l'IA
        $aiMessage = Message::create([
            'conversation_id' => $id,
            'role'            => 'assistant',
            'content'         => $aiContent,
        ]);

        // Mettre à jour le titre si c'est le premier message
        $conversation = Conversation::find($id);
        if ($conversation->title === 'Nouvelle conversation') {
            $conversation->update([
                'title' => substr($request->content, 0, 50),
            ]);
        }

        return response()->json($aiMessage);
    }

    // Supprimer une conversation
    public function deleteConversation($id)
    {
        Conversation::find($id)->delete();
        return response()->json(['message' => 'Conversation supprimée']);
    }
}