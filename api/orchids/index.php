<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/middleware/http.php';

sendApiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJson(['error' => 'Methode non autorisee'], 405);
}

try {
    $pdo = getDb();
    $stmt = $pdo->query('SELECT id, name, vernacular, botanical_order as "order", species, genre, family, subfamily, tribu, subtribu, behavior, discovered, origin, img, short_desc as "shortDesc", long_desc as "longDesc" FROM public.orchids WHERE is_published = TRUE ORDER BY name ASC');
    $orchids = $stmt->fetchAll();

    sendJson($orchids);
} catch (Throwable $e) {
    error_log('Echec de lecture des orchidees: ' . $e->getMessage());
    sendJson(['error' => 'Erreur serveur'], 500);
}

