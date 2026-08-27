<?php
/**
 * Protections HTTP partagees par les points d'entree de l'API.
 */

function sendApiHeaders(): void
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

function sendJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

/**
 * Bloque les requetes d'ecriture initiees depuis un autre site.
 * SameSite=Strict reste la protection principale du cookie de session.
 */
function requireSameOriginRequest(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return;
    }

    $originHost = parse_url($origin, PHP_URL_HOST);
    $requestHost = strtolower(preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));

    if (!is_string($originHost) || strtolower($originHost) !== $requestHost) {
        sendJson(['error' => 'Origine de requete non autorisee'], 403);
    }
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return $_POST;
    }

    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        sendJson(['error' => 'Corps JSON invalide'], 400);
    }

    if (!is_array($data)) {
        sendJson(['error' => 'Corps JSON invalide'], 400);
    }

    return $data;
}
