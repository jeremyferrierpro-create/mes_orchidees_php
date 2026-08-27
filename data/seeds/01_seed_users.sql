-- ===========================================================================
-- SEED 01 — TABLE users
-- ===========================================================================
-- Ordre de migration : 1er (aucune FK entrante)
-- Source : /assets/js/data/users.json
-- BDD : Neon (PostgreSQL 16 serverless)
-- Schema : schema_neon.sql
--
-- SECURITE : Les mots de passe en clair ("demouser", "demoadmin") du JSON
-- sont REMPLACES par des hashs argon2id generés hors de ce fichier.
-- Ce seed contient des hashs de DEMONSTRATION uniquement.
-- En production :
--   PHP  : password_hash($password, PASSWORD_ARGON2ID)
--   Neon : stocké dans password_hash TEXT
-- ===========================================================================

-- Hashs argon2id de demonstration (generes avec PHP password_hash)
-- REMPLACER avant tout deploiement en production avec de vrais hashs generes
-- par l application PHP au moment de l inscription.
--
-- Correspondances (pour les tests locaux uniquement, ne pas commiter en prod) :
--   id=1 : "demouser"  => $argon2id$v=19$m=65536,t=4,p=1$...
--   id=2 : "demoadmin" => $argon2id$v=19$m=65536,t=4,p=1$...
--   id=3 : "demouser"  => $argon2id$v=19$m=65536,t=4,p=1$...
--
-- Pour regenerer : php -r "echo password_hash('demouser', PASSWORD_ARGON2ID);"

INSERT INTO public.users (id, nom, prenom, email, password_hash, role, created_at, updated_at)
VALUES
    -- Jean-Marc Dupont — utilisateur simple (id legacy = 1)
    (
        gen_random_uuid(),
        'Dupont',
        'Jean-Marc',
        'admin@mesorchidees.fr',
        '$argon2id$v=19$m=65536,t=4,p=1$SEED_DEMO_HASH_REPLACE_1',
        'user',
        '2026-01-15 00:00:00+01',
        '2026-04-01 00:00:00+02'
    ),
    -- Jeremy Ferrier — administrateur (id legacy = 2)
    (
        gen_random_uuid(),
        'FERRIER',
        'Jeremy',
        'jeremy.ferrierpro@gmail.com',
        '$argon2id$v=19$m=65536,t=4,p=1$SEED_DEMO_HASH_REPLACE_2',
        'admin',
        '2026-01-15 00:00:00+01',
        '2026-04-01 00:00:00+02'
    ),
    -- Jessica Martin — utilisatrice simple (id legacy = 3)
    (
        gen_random_uuid(),
        'Martin',
        'Jessica',
        'jessica.amateur@gmail.com',
        '$argon2id$v=19$m=65536,t=4,p=1$SEED_DEMO_HASH_REPLACE_3',
        'user',
        '2026-02-03 00:00:00+01',
        '2026-05-10 00:00:00+02'
    )
ON CONFLICT (email) DO UPDATE SET
    nom        = EXCLUDED.nom,
    prenom     = EXCLUDED.prenom,
    role       = EXCLUDED.role,
    updated_at = NOW();

-- Verification post-seed
DO $$
DECLARE v_count INT;
BEGIN
    SELECT COUNT(*) INTO v_count FROM public.users;
    ASSERT v_count >= 3, 'ERREUR : seed users incomplet (' || v_count || ' lignes)';
    RAISE NOTICE 'SEED 01 OK : % utilisateur(s) en base', v_count;
END $$;
