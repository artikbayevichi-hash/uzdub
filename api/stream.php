<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // Nginx uchun, Apache'da ham zarar qilmaydi

$data = [
    "model" => "llama3",
    "prompt" => $_POST['prompt'],
    "stream" => true,
    "keep_alive" => "30m"   // model xotirada saqlanadi, qayta yuklanmaydi
];

$ch = curl_init("http://localhost:11434/api/generate");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) {
    $lines = explode("\n", trim($chunk));
    foreach ($lines as $line) {
        if (!$line) continue;
        $json = json_decode($line, true);
        if (isset($json['response'])) {
            echo $json['response'];
            @ob_flush();
            @flush();
        }
    }
    return strlen($chunk);
});

curl_exec($ch);
curl_close($ch);