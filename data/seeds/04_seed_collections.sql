-- ===========================================================================
-- SEED 04 — TABLE collections
-- ===========================================================================
-- Ordre de migration : 4eme (users + orchids doivent exister)
-- Source : /assets/js/data/collections.json (vide en seed initial)
-- BDD : Neon (PostgreSQL 16 serverless)
--
-- En production, la table collections est VIDE au deploiement.
-- Elle sera alimentee par les utilisateurs via l application PHP.
-- Ce seed insere des donnees de demonstration pour les tests de recette.
--
-- IMPORTANT : les collections.json du localStorage utilisaient des
-- collectionId string (ex: "col-001"). En SQL ce devient un UUID dans
-- la colonne id, avec legacy_id TEXT qui conserve l ancien identifiant
-- pour la migration sans perte de donnees.
-- ===========================================================================

-- Exemples de demonstration (correspondent aux notifications.json)
-- Ils utilisent des sous-requetes pour resoudre les UUID depuis l email
-- (independant de l ordre d insertion des users)

INSERT INTO public.collections (id, legacy_id, user_id, orchid_id, location, notes, added_at)
VALUES
    -- Jean-Marc : col-001 → ACACALIS CYANEA
    (gen_random_uuid(), 'col-001',
     (SELECT id FROM public.users WHERE email = 'admin@mesorchidees.fr' LIMIT 1),
     'acacalis_cyanea',
     'Veranda Sud', 'Tres belle floraison en mars',
     '2026-03-01 00:00:00+01'),

    -- Jean-Marc : col-002 → BLETILLA OCHRACEA
    (gen_random_uuid(), 'col-002',
     (SELECT id FROM public.users WHERE email = 'admin@mesorchidees.fr' LIMIT 1),
     'bletilla_ochracea',
     'Jardin pleine terre', 'Hiberne en hiver',
     '2026-04-15 00:00:00+02'),

    -- Jean-Marc : col-003 → VANILLA PLANIFOLIA
    (gen_random_uuid(), 'col-003',
     (SELECT id FROM public.users WHERE email = 'admin@mesorchidees.fr' LIMIT 1),
     'vanilla_planifolia',
     'Serre chauffee', 'Pollinisation manuelle en cours',
     '2026-05-10 00:00:00+02'),

    -- Jessica : col-004 → ANGRAECUM SESQUIPEDALE
    (gen_random_uuid(), 'col-004',
     (SELECT id FROM public.users WHERE email = 'jessica.amateur@gmail.com' LIMIT 1),
     'angraecum_sesquipedale',
     'Fenetre Est salon', 'Premier arrosage soin enregistre',
     '2026-06-01 00:00:00+02'),

    -- Jessica : col-005 → AERANGIS FASTUOSA
    (gen_random_uuid(), 'col-005',
     (SELECT id FROM public.users WHERE email = 'jessica.amateur@gmail.com' LIMIT 1),
     'aerangis_fastuosa',
     'Terrarium humide', NULL,
     '2026-07-20 00:00:00+02'),

    -- Jessica : col-006 → ANGULOA VIRGINALIS
    (gen_random_uuid(), 'col-006',
     (SELECT id FROM public.users WHERE email = 'jessica.amateur@gmail.com' LIMIT 1),
     'anguloa_virginalis',
     'Serre froide', 'Repos hivernal programme',
     '2026-08-01 00:00:00+02')

ON CONFLICT (legacy_id) DO NOTHING;

-- Verification post-seed
DO $$
DECLARE v_count INT;
BEGIN
    SELECT COUNT(*) INTO v_count FROM public.collections;
    RAISE NOTICE 'SEED 04 OK : % entree(s) de collection en base', v_count;
END $$;
