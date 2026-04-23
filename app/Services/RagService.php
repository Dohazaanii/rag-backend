<?php

namespace App\Services;

class RagService
{
    public static function ask($question, $file = null)
    {
        $socket = fsockopen("127.0.0.1", 9000, $errno, $errstr, 5);

        if (!$socket) {
            return ["answer" => "RAG not reachable", "pages" => []];
        }

        $payload = json_encode([
            "question" => $question,
            "file" => $file
        ]);

        fwrite($socket, $payload);

        $response = "";
        while (!feof($socket)) {
            $response .= fgets($socket, 1024);
        }

        fclose($socket);

        return json_decode($response, true);
    }
}