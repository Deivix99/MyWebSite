<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$body = file_get_contents('php://input');
if (!$body) { http_response_code(400); echo json_encode(['error'=>'empty body']); exit; }

$ch = curl_init('http://localhost:8080/v1/ai/chat');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  CURLOPT_POSTFIELDS => $body,
  CURLOPT_CONNECTTIMEOUT => 10,  // 10s conectar al :8080
  CURLOPT_TIMEOUT => 35,         // 35s total
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($res === false) { http_response_code(502); echo json_encode(['error'=>'bad_gateway','detail'=>curl_error($ch)]); }
else { http_response_code($code ?: 200); echo $res; }
curl_close($ch);
