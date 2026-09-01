-- ===========================================================================
-- SEED 05 — TABLE soins
-- ===========================================================================
-- Ordre de migration : 5eme (collections doit exister)
-- Source : /assets/js/data/soins.json (vide au seed initial)
-- BDD : Neon (PostgreSQL 16 serverless)
--
-- En production, la table soins est VIDE au deploiement.
-- Elle est alimentee par l application PHP via addCareEntry().
-- Ce seed insere des donnees de demonstration basees sur les
-- messages des notifications.json pour les tests.
-- ===========================================================================

INSERT INTO public.soins (id, legacy_id, collection_id, soin_date, type, notes, engrais, substrat, ravageurs)
VALUES
    -- Soin Jean-Marc : arrosage col-001 (acacalis_cyanea)
    (gen_random_uuid(), 'care-demo-001',
     (SELECT id FROM public.collections WHERE legacy_id = 'col-001' LIMIT 1),
     '2026-08-14', 'arrosage', 'Arrosage complet trempage 15 minutes eau douce', FALSE, FALSE, FALSE),

    -- Soin Jean-Marc : engrais col-001
    (gen_random_uuid(), 'care-demo-002',
     (SELECT id FROM public.collections WHERE legacy_id = 'col-001' LIMIT 1),
     '2026-07-28', 'engrais', 'Engrais orchidee N20-P20-K20 dilue au 1/4', TRUE, FALSE, FALSE),

    -- Soin Jessica : arrosage col-004 (angraecum_sesquipedale)
    (gen_random_uuid(), 'care-demo-003',
     (SELECT id FROM public.collections WHERE legacy_id = 'col-004' LIMIT 1),
     '2026-08-15', 'arrosage', 'Arrosage enregistre - prochain rappel fixe au 22/08/2026', FALSE, FALSE, FALSE),

    -- Soin Jessica : verification ravageurs col-005
    (gen_random_uuid(), 'care-demo-004',
     (SELECT id FROM public.collections WHERE legacy_id = 'col-005' LIMIT 1),
     '2026-08-10', 'ravageurs', 'Controle cochenilles - traitement preventif huile de neem', FALSE, FALSE, TRUE)

ON CONFLICT (legacy_id) DO NOTHING;

-- Verification post-seed
DO $$
DECLARE v_count INT;
BEGIN
    SELECT COUNT(*) INTO v_count FROM public.soins;
    RAISE NOTICE 'SEED 05 OK : % soin(s) enregistre(s)', v_count;
END $$;
