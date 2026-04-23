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

        $content  = $request->input('content', '');
        $filePath = null;
        $fileName = null;

        // ── 1. Handle uploaded file ──────────────────────────
        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            $file     = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $stored   = $file->store('rag_uploads', 'local');
            $filePath = storage_path('app/' . $stored);
        }

        // ── 2. Save user message ─────────────────────────────
        Message::create([
            'conversation_id' => $id,
            'role'            => 'user',
            'content'         => $content ?: ('File: ' . $fileName),
            'file_name'       => $fileName,
        ]);

 // ── 3. Call Python RAG via SOCKET (DOCKER READY) ─────────
try {
    $question = $content ?: 'Please provide a concise summary of this document.';

    $socket = fsockopen("127.0.0.1", 9000, $errno, $errstr, 10);

    if (!$socket) {
        throw new \Exception("Cannot connect to RAG service: $errstr ($errno)");
    }

    $payload = json_encode([
        "question" => $question,
        "file"     => $filePath
    ]);

    fwrite($socket, $payload);

    $response = "";
    while (!feof($socket)) {
        $response .= fgets($socket, 1024);
    }

    fclose($socket);

    // Clean response
    $response = preg_replace('/^\xEF\xBB\xBF/', '', $response);
    $response = mb_convert_encoding($response, 'UTF-8', 'UTF-8');

    $output = json_decode($response, true);

    if (!$output) {
        throw new \Exception("Invalid JSON from RAG: " . $response);
    }

    $aiContent = $output['answer'] ?? 'No response.';

    // Clean encoding
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