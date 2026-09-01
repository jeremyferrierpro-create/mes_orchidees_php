-- ===========================================================================
-- SEED 06 — TABLE notifications
-- ===========================================================================
-- Ordre de migration : 6eme (collections doit exister pour les FK)
-- Source : /assets/js/data/notifications.json (6 entrees)
-- BDD : Neon (PostgreSQL 16 serverless)
--
-- Traductions :
--   userId      → user_id UUID (resolu depuis email via sous-requete)
--   collectionId string "col-001" → collection_id UUID via legacy_id
--   date "dd/MM/yyyy" → notif_date TIMESTAMPTZ
--   isRead boolean → is_read BOOLEAN
--   type "rappel_soin" → type VARCHAR(50) DEFAULT 'rappel_soin'
-- ===========================================================================

INSERT INTO public.notifications (id, legacy_id, user_id, collection_id, type, message, is_read, notif_date)
VALUES
    -- Notification 1 : Jean-Marc / col-001 / non lue
    (gen_random_uuid(), 1,
     (SELECT id FROM public.users WHERE email = 'admin@mesorchidees.fr' LIMIT 1),
     (SELECT id FROM public.collections WHERE legacy_id = 'col-001' LIMIT 1),
     'rappel_soin',
     'Jean-Marc : prochain arrosage prevu le 21/08/2026 pour ACACALIS CYANEA.',
     FALSE,
     '2026-08-14 00:00:00+02'),

    -- Notification 2 : Jean-Marc / col-002 / non lue
    (gen_random_uuid(), 2,
     (SELECT id FROM public.users WHERE email = 'admin@mesorchidees.fr' LIMIT 1),
     (SELECT id FROM public.collections WHERE legacy_id = 'col-002' LIMIT 1),
     'rappel_soin',
     'Jean-Marc : arrosage a prevoir pour BLETILLA OCHRACEA.',
     FALSE,
     '2026-08-17 00:00:00+02'),

    -- Notification 3 : Jean-Marc / col-003 / non lue
    (gen_random_uuid(), 3,
     (SELECT id FROM public.users WHERE email = 'admin@mesorchidees.fr' LIMIT 1),
     (SELECT id FROM public.collections WHERE legacy_id = 'col-003' LIMIT 1),
     'rappel_soin',
     'Jean-Marc : apport d eau et d engrais a prevoir pour VANILLA PLANIFOLIA.',
     FALSE,
     '2026-08-19 00:00:00+02'),

    -- Notification 4 : Jessica / col-004 / LUE
    (gen_random_uuid(), 4,
     (SELECT id FROM public.users WHERE email = 'jessica.amateur@gmail.com' LIMIT 1),
     (SELECT id FROM public.collections WHERE legacy_id = 'col-004' LIMIT 1),
     'rappel_soin',
     'Jessica : arrosage enregistre pour ANGRAECUM SESQUIPEDALE. Le prochain rappel est fixe au 22/08/2026.',
     TRUE,
     '2026-08-15 00:00:00+02'),

    -- Notification 5 : Jessica / col-005 / non lue
    (gen_random_uuid(), 5,
     (SELECT id FROM public.users WHERE email = 'jessica.amateur@gmail.com' LIMIT 1),
     (SELECT id FROM public.collections WHERE legacy_id = 'col-005' LIMIT 1),
     'rappel_soin',
     'Jessica : prochain arrosage prevu le 23/08/2026 pour AERANGIS FASTUOSA.',
     FALSE,
     '2026-08-16 00:00:00+02'),

    -- Notification 6 : Jessica / col-006 / non lue
    (gen_random_uuid(), 6,
     (SELECT id FROM public.users WHERE email = 'jessica.amateur@gmail.com' LIMIT 1),
     (SELECT id FROM public.collections WHERE legacy_id = 'col-006' LIMIT 1),
     'rappel_soin',
     'Jessica : arrosage a prevoir pour ANGULOA VIRGINALIS.',
     FALSE,
     '2026-08-16 00:00:00+02')

ON CONFLICT DO NOTHING;

-- Verification post-seed
DO $$
DECLARE v_total INT; v_unread INT;
BEGIN
    SELECT COUNT(*) INTO v_total FROM public.notifications;
    SELECT COUNT(*) INTO v_unread FROM public.notifications WHERE is_read = FALSE;
    ASSERT v_total = 6, 'ERREUR : seed notifications incomplet (' || v_total || '/6)';
    RAISE NOTICE 'SEED 06 OK : % notification(s) dont % non lue(s)', v_total, v_unread;
END $$;
