<?php
/**
 * Repository des emplacements — accès PDO à la table `emplacements`.
 *
 * ISOLATION RG1 : chaque requête (lecture comme écriture) est plafonnée par
 * `WHERE ... user_id = :uid`. Un endroit d'un autre propriétaire n'est jamais lu,
 * modifié ni supprimé. L'UUID est généré en PHP (comme CollectionRepository) et
 * inséré explicitement pour que create() retourne le vrai id.
 *
 * @package App\Repository
 * @author  Jérémy Ferrier
 * @version 3.0.0
 * @since   2026-10-22
 */

namespace App\Repository;

use App\Entity\Emplacement;
use PDO;

class EmplacementRepository
{
    private const SELECT = 'SELECT id, user_id, nom, temp_min, temp_max, hygro_min, hygro_max,
                                   lumiere, ventilation, created_at, updated_at
                              FROM emplacements';

    private ?PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    /** Connexion résolue à la demande (lazy). */
    private function pdo(): PDO
    {
        if ($this->db instanceof PDO) {
            return $this->db;
        }
        $this->db = getDb();
        return $this->db;
    }

    /**
     * Tous les endroits de l'utilisateur (RG1 : WHERE user_id), tri alphabétique.
     *
     * @param string $userId
     * @return Emplacement[]
     */
    public function findByUser(string $userId): array
    {
        $stmt = $this->pdo()->prepare(self::SELECT . ' WHERE user_id = :uid ORDER BY nom ASC');
        $stmt->execute([':uid' => $userId]);
        return array_map(fn(array $r) => Emplacement::fromRow($r), $stmt->fetchAll());
    }

    /**
     * Un endroit, UNIQUEMENT s'il appartient à l'utilisateur (RG1).
     *
     * @param string $id
     * @param string $userId
     * @return Emplacement|null
     */
    public function findByIdForUser(string $id, string $userId): ?Emplacement
    {
        $stmt = $this->pdo()->prepare(self::SELECT . ' WHERE id = :id AND user_id = :uid LIMIT 1');
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        $row = $stmt->fetch();
        return $row === false ? null : Emplacement::fromRow($row);
    }

    /**
     * Crée un endroit pour l'utilisateur. L'UUID est généré en PHP.
     *
     * @param Emplacement $e Entité peuplée (user_id renseigné).
     * @return string L'id (UUID) de la ligne insérée.
     */
    public function create(Emplacement $e): string
    {
        $id    = $this->uuid();
        $e->id = $id;
        $stmt  = $this->pdo()->prepare(
            'INSERT INTO emplacements (id, user_id, nom, temp_min, temp_max, hygro_min, hygro_max, lumiere, ventilation)
             VALUES (:id, :user_id, :nom, :temp_min, :temp_max, :hygro_min, :hygro_max, :lumiere, :ventilation)'
        );
        $stmt->execute($this->bind($e, $id));
        return $id;
    }

    /**
     * Met à jour un endroit, UNIQUEMENT s'il appartient à l'utilisateur (RG1).
     *
     * @param Emplacement $e
     * @param string      $userId
     * @return bool
     */
    public function updateForUser(Emplacement $e, string $userId): bool
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE emplacements
                SET nom = :nom, temp_min = :temp_min, temp_max = :temp_max,
                    hygro_min = :hygro_min, hygro_max = :hygro_max, lumiere = :lumiere,
                    ventilation = :ventilation
              WHERE id = :id AND user_id = :uid'
        );
        $bind = $this->bind($e, $e->id);
        $bind[':uid'] = $userId;
        $stmt->execute($bind);
        return $stmt->rowCount() > 0;
    }

    /**
     * Supprime un endroit, UNIQUEMENT s'il appartient à l'utilisateur (RG1).
     * Les plantes rattachées passent à emplacement_id NULL (FK ON DELETE SET NULL).
     *
     * @param string $id
     * @param string $userId
     * @return bool
     */
    public function deleteForUser(string $id, string $userId): bool
    {
        $stmt = $this->pdo()->prepare('DELETE FROM emplacements WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Assemble les paramètres liés (sans :uid) pour INSERT/UPDATE.
     *
     * @param Emplacement $e
     * @param string      $id
     * @return array
     */
    private function bind(Emplacement $e, string $id): array
    {
        return [
            ':id'         => $id,
            ':user_id'    => $e->userId,
            ':nom'        => $e->nom,
            ':temp_min'   => $e->tempMin,
            ':temp_max'   => $e->tempMax,
            ':hygro_min'  => $e->hygroMin,
            ':hygro_max'  => $e->hygroMax,
            ':lumiere'    => $e->lumiere,
            ':ventilation'=> $e->ventilation,
        ];
    }

    /**
     * Fabrique un UUID v4 (RFC 4122) en PHP pur — même algorithme que CollectionRepository.
     *
     * @return string
     */
    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40); // version 4
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80); // variante RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
