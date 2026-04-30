<?php

namespace App\Services;

class RagService
{
    public static function ask($question, $file = null)
    {
        $socket = fsockopen("127.0.0.1", 9000, $errno, $errstr, 10);

        if (!$socket) {
            return [
                "answer" => "RAG not reachable: $errstr ($errno)",
                "pages" => []
            ];
        }

        $payload = json_encode([
            "question" => $question,
            "file"     => $file
        ]);

        // envoyer données
        fwrite($socket, $payload);

        // 🔥 IMPORTANT → signal fin envoi
        stream_socket_shutdown($socket, STREAM_SHUT_WR);

        // lire réponse
        $response = "";
        while (!feof($socket)) {
            $response .= fread($socket, 1024);
        }

        fclose($socket);

        return json_decode($response, true) ?? [
            "answer" => "Invalid JSON response",
            "pages"  => []
        ];
    }
}