<?php
namespace App\Modules\Chat\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use Illuminate\Support\Facades\Http;

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
            'title'   => 'New conversation',
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
        set_time_limit(300);

        $request->validate([
            'content' => 'nullable|string',
            'file'    => 'nullable|file|mimes:pdf,doc,docx|max:20480',
        ]);

        $content     = $request->input('content', '');
        $fileContent = null;
        $fileName    = null;

        // ── 1. Handle uploaded file ──────────────────────────
        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            $file        = $request->file('file');
            $fileName    = $file->getClientOriginalName();
            $fileContent = base64_encode(file_get_contents($file->getRealPath()));
        }

        // ── 2. Save user message ─────────────────────────────
        Message::create([
            'conversation_id' => $id,
            'role'            => 'user',
            'content'         => $content ?: ('File: ' . $fileName),
            'file_name'       => $fileName,
        ]);

        // ── 3. Call Python RAG via HTTP ──────────────────────────
        try {
            $question = $content ?: 'Please provide a concise summary of this document.';

            $ragResponse = Http::timeout(300)->post('http://127.0.0.1:9000', [
                'question'     => $question,
                'file_content' => $fileContent,
                'file_name'    => $fileName,
            ]);

            $output = $ragResponse->json();

            if (!$output) {
                throw new \Exception("Invalid JSON from RAG: " . $ragResponse->body());
            }

            $aiContent = $output['answer'] ?? 'No response.';

            $aiContent = mb_convert_encoding($aiContent, 'UTF-8', 'UTF-8');
            $aiContent = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $aiContent);

            if (!empty($output['pages'])) {
                $pages = implode(', ', $output['pages']);
                $aiContent .= "\n\nSources: pages " . $pages;
            }

        } catch (\Exception $e) {
            $aiContent = 'RAG Error: ' . $e->getMessage();
        }

        // ── 4. Save AI message ───────────────────────────────
        $aiMessage = Message::create([
            'conversation_id' => $id,
            'role'            => 'assistant',
            'content'         => $aiContent,
        ]);

        // ── 5. Update conversation title ─────────────────────
        $conversation = Conversation::find($id);
        if ($conversation->title === 'New conversation') {
            $title = $content ?: $fileName;
            $conversation->update([
                'title' => mb_substr($title, 0, 50),
            ]);
        }

        return response()->json($aiMessage);
    }

    public function deleteConversation($id)
    {
        Conversation::find($id)->delete();
        return response()->json(['message' => 'Conversation deleted']);
    }
}