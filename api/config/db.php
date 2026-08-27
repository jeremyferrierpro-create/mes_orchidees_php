<?php
/**
 * Configuration de la connexion PDO a la base de donnees Neon.
 */

require_once __DIR__ . '/env.php';

function getDb(): PDO {
    $dsn = $_ENV['NEON_DSN'] ?? getenv('NEON_DSN');
    if (!$dsn) {
        throw new RuntimeException('Variable NEON_DSN manquante dans .env');
    }

    $user = null;
    $password = null;

    // Si la chaine commence par postgresql:// ou postgres://, on la parse
    if (strpos($dsn, 'postgresql:') === 0 || strpos($dsn, 'postgres:') === 0) {
        if (strpos($dsn, 'postgresql:postgresql://') === 0) {
            $dsn = substr($dsn, 11);
        }

        $parsed = parse_url($dsn);
        if ($parsed) {
            $host = $parsed['host'] ?? '';
            $port = $parsed['port'] ?? 5432;
            $db = ltrim($parsed['path'] ?? '', '/');
            $user = $parsed['user'] ?? null;
            $password = $parsed['pass'] ?? null;
            
            // Format natif attendu par PDO pour PostgreSQL
            $pdoDsn = sprintf("pgsql:host=%s;port=%d;dbname=%s", $host, $port, $db);

            // Neon DB requires SNI. If older libpq, we must pass the endpoint ID explicitly.
            // Endpoint ID is the first part of the host (e.g., ep-empty-moon-b14n86hx)
            $hostParts = explode('.', $host);
            if (count($hostParts) > 0 && strpos($host, 'neon.tech') !== false) {
                $endpointId = $hostParts[0];
                $pdoDsn .= sprintf(";sslmode=require;options=endpoint=%s", $endpointId);
            }

            $dsn = $pdoDsn;
        }
    }

    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, 
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        throw new RuntimeException('Erreur de connexion a la base de donnees. Verifiez NEON_DSN. ' . $e->getMessage());
    }
}
