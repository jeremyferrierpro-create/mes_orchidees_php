-- ===========================================================================
-- Migration — Paramétrage de l'environnement de culture (« endroits »)
-- ===========================================================================
-- Auteur   : Jeremy Ferrier
-- Date     : 2026-10-22
-- Objet    : (1) nouvelle table `emplacements` — un lieu de culture par
--            utilisateur, avec ses constantes climatiques STRUCTUREES
--            (temperature min/max, hygro min/max, lumiere, ventilation) ;
--            (2) colonne `collections.emplacement_id` — une plante est rattachee
--            a UN SEUL endroit (cle trangere, ON DELETE SET NULL : la plante
--            survit si l'endroit est supprime) ;
--            (3) backfill — chaque `collections.location` (texte libre) distinct
--            par utilisateur devient un `emplacements` rattache aux plantes.
--
-- Aucun DROP, aucune casse applicative : les champs texte par plante
-- (location/temp/hygro/light/ventilation) restent en place (retro-compatibilite).
-- Idempotent : CREATE TABLE IF NOT EXISTS + gardes information_schema.
-- ===========================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. TABLE : emplacements
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS emplacements (
    id          CHAR(36)     NOT NULL,
    user_id     CHAR(36)     NOT NULL,
    nom         VARCHAR(200) NOT NULL COMMENT 'Ex : Serre chaude, Fenetre Est du salon',
    temp_min    DECIMAL(4,1) NULL COMMENT 'Temperature minimale (degres C)',
    temp_max    DECIMAL(4,1) NULL COMMENT 'Temperature maximale (degres C)',
    hygro_min   TINYINT      NULL COMMENT 'Hygrometrie minimale (%)',
    hygro_max   TINYINT      NULL COMMENT 'Hygrometrie maximale (%)',
    lumiere     ENUM('faible','moyenne','forte') NULL COMMENT 'Niveau de lumiere dominant',
    ventilation VARCHAR(100) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_emplacements_user (user_id),
    CONSTRAINT fk_emplacements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Endroits de culture d''un collectionneur, avec leurs constantes climatiques structurees.';

-- ---------------------------------------------------------------------------
-- 2. COLONNE : collections.emplacement_id (cle trangere, ADD unique)
-- ---------------------------------------------------------------------------
-- Test en deux temps cote application ; en SQL pur on s'appuie sur un
-- ALTER protege par un garde information_schema (pas de DROP, ajout unique).
DROP PROCEDURE IF EXISTS mig_emplacements_column();
DELIMITER $$
CREATE PROCEDURE mig_emplacements_column()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'collections'
          AND COLUMN_NAME  = 'emplacement_id'
    ) THEN
        ALTER TABLE collections
            ADD COLUMN emplacement_id CHAR(36) NULL AFTER orchid_id;
        CREATE INDEX idx_collections_emplacement
            ON collections (emplacement_id);
        ALTER TABLE collections
            ADD CONSTRAINT fk_collections_emplacement
            FOREIGN KEY (emplacement_id) REFERENCES emplacements(id) ON DELETE SET NULL;
    END IF;
END$$
DELIMITER ;
CALL mig_emplacements_column();
DROP PROCEDURE IF EXISTS mig_emplacements_column;

-- ---------------------------------------------------------------------------
-- 3. Backfill — un endroit par (user, location) texte distinct non vide
-- ---------------------------------------------------------------------------
-- On cree des lignes `emplacements` (nom = location saisie, constantes NULL)
-- pour chaque utilisateur possedant des plantes avec une location non nulle,
-- puis on rattache les plantes correspondantes. Plantes sans location : NULL.
-- id genere par UUID() ; la double-materialisation (sous-selectee x) evite la
-- restriction MySQL « target table in FROM clause » et la garde e.id IS NULL
-- empeche tout doublon a re-execution.
INSERT INTO emplacements (id, user_id, nom)
SELECT UUID(), x.user_id, x.nom FROM (
    SELECT d.user_id, d.nom
      FROM (
            SELECT DISTINCT user_id, location AS nom
              FROM collections
             WHERE location IS NOT NULL AND TRIM(location) <> ''
           ) d
      LEFT JOIN emplacements e
             ON e.user_id = d.user_id AND e.nom = d.nom
     WHERE e.id IS NULL
) x;

UPDATE collections c
   JOIN emplacements e
     ON e.user_id = c.user_id AND e.nom = c.location
    SET c.emplacement_id = e.id
  WHERE c.emplacement_id IS NULL
    AND c.location IS NOT NULL AND TRIM(c.location) <> '';
