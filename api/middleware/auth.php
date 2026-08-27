<?php
require_once dirname(__DIR__) . '/config/env.php';

function base64UrlEncode(string $text): string {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
}

function base64UrlDecode(string $text): string|false {
    $text = str_replace(['-', '_'], ['+', '/'], $text);
    return base64_decode($text . str_repeat('=', (4 - strlen($text) % 4) % 4), true);
}

function verifyJWT($jwt) {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return null;

    list($header64, $payload64, $signature64) = $parts;
    
    $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET');
    if (!is_string($secret) || $secret === '') return null;
    $expectedSignature = hash_hmac('sha256', $header64 . "." . $payload64, $secret, true);
    
    if (hash_equals(base64UrlEncode($expectedSignature), $signature64)) {
        $decodedPayload = base64UrlDecode($payload64);
        $payload = is_string($decodedPayload) ? json_decode($decodedPayload, true) : null;
        if (is_array($payload) && isset($payload['sub'], $payload['role'], $payload['exp']) && $payload['exp'] > time()) {
            return $payload;
        }
    }
    return null;
}

function getCurrentUser() {
    $jwt = $_COOKIE['session'] ?? null;
    if (!$jwt) return null;
    return verifyJWT($jwt);
}

function requireAuth() {
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Non authentifie']);
        exit;
    }
    return $user;
}
