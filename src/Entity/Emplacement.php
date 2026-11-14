<?php
/**
 * Entité Emplacement — un lieu de culture d'un collectionneur (table MySQL/MariaDB `emplacements`).
 *
 * Représente « l'endroit où se situe la collection » : un espace nommé (serre,
 * fenêtre du salon…) porteur de constantes climatiques STRUCTURÉES (température
 * min/max en °C, hygrométrie min/max en %, niveau de lumière, ventilation).
 *
 * Une plante de la collection (`collections`) est rattachée à UN SEUL endroit via
 * `collections.emplacement_id`. La carte « CLIMAT » du tableau de bord agrège les
 * constantes des endroits des plantes possédées.
 *
 * `id` est un UUID CHAR(36) généré en PHP (comme Collection) : jamais saisi en base.
 *
 * @package App\Entity
 * @author  Jérémy Ferrier
 * @version 3.0.0
 * @since   2026-10-22
 */

namespace App\Entity;

class Emplacement
{
    public string $id = '';
    public string $userId = '';
    public string $nom = '';
    public ?float $tempMin = null;
    public ?float $tempMax = null;
    public ?int $hygroMin = null;
    public ?int $hygroMax = null;
    /** 'faible' | 'moyenne' | 'forte' | null. */
    public ?string $lumiere = null;
    public ?string $ventilation = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** Niveaux de lumière acceptés (vocabulaire fermé de la colonne ENUM). */
    public const LUMIERES = ['faible', 'moyenne', 'forte'];

    /**
     * Construit une entité à partir d'une ligne snake_case de `emplacements`.
     *
     * @param array $row
     * @return self
     */
    public static function fromRow(array $row): self
    {
        $e = new self();
        $e->id           = (string) ($row['id'] ?? '');
        $e->userId       = (string) ($row['user_id'] ?? '');
        $e->nom          = (string) ($row['nom'] ?? '');
        $e->tempMin      = isset($row['temp_min']) && $row['temp_min'] !== null ? (float) $row['temp_min'] : null;
        $e->tempMax      = isset($row['temp_max']) && $row['temp_max'] !== null ? (float) $row['temp_max'] : null;
        $e->hygroMin     = isset($row['hygro_min']) && $row['hygro_min'] !== null ? (int) $row['hygro_min'] : null;
        $e->hygroMax     = isset($row['hygro_max']) && $row['hygro_max'] !== null ? (int) $row['hygro_max'] : null;
        $e->lumiere      = $row['lumiere'] ?? null;
        $e->ventilation  = $row['ventilation'] ?? null;
        $e->createdAt    = $row['created_at'] ?? null;
        $e->updatedAt    = $row['updated_at'] ?? null;
        return $e;
    }

    /**
     * Point milieu de la fourchette de température (min et/ou max), null si aucune.
     * Sert à l'agrégation de la carte CLIMAT.
     *
     * @return float|null
     */
    public function tempMoyenne(): ?float
    {
        $vals = array_filter([$this->tempMin, $this->tempMax], static fn($v) => $v !== null);
        return $vals === [] ? null : array_sum($vals) / count($vals);
    }

    /**
     * Point milieu de la fourchette d'hygrométrie, null si aucune.
     *
     * @return float|null
     */
    public function hygroMoyenne(): ?float
    {
        $vals = array_filter([$this->hygroMin, $this->hygroMax], static fn($v) => $v !== null);
        return $vals === [] ? null : array_sum($vals) / count($vals);
    }

    /**
     * Libellé de température lisible : « 18–22 °C », « 20 °C » ou null.
     *
     * @return string|null
     */
    public function tempLabel(): ?string
    {
        if ($this->tempMin !== null && $this->tempMax !== null) {
            return $this->fmtTemp($this->tempMin) . '–' . $this->fmtTemp($this->tempMax) . ' °C';
        }
        $unique = $this->tempMin ?? $this->tempMax;
        return $unique !== null ? $this->fmtTemp($unique) . ' °C' : null;
    }

    /**
     * Libellé d'hygrométrie lisible : « 60–70 % », « 65 % » ou null.
     *
     * @return string|null
     */
    public function hygroLabel(): ?string
    {
        if ($this->hygroMin !== null && $this->hygroMax !== null) {
            return $this->hygroMin . '–' . $this->hygroMax . ' %';
        }
        $unique = $this->hygroMin ?? $this->hygroMax;
        return $unique !== null ? $unique . ' %' : null;
    }

    /**
     * Libellé de lumière capitalisé (« Moyenne »), null si non réglé.
     *
     * @return string|null
     */
    public function lightLabel(): ?string
    {
        return $this->lumiere !== null ? ucfirst($this->lumiere) : null;
    }

    /**
     * Rend une température sans décimale inutile (20.0 → « 20 », 20.5 → « 20,5 »).
     *
     * @param float $v
     * @return string
     */
    private function fmtTemp(float $v): string
    {
        $rounded = round($v, 1);
        return $rounded === floor($rounded)
            ? (string) (int) $rounded
            : rtrim(rtrim(number_format($rounded, 1, ',', ''), '0'), ',');
    }
}
