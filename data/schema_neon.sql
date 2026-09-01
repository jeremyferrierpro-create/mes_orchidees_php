-- ===========================================================================
-- SCHEMA NEON (PostgreSQL 16 Serverless) — Mes Orchidees
-- ===========================================================================
-- Auteur   : Jeremy Ferrier
-- Projet   : Mes Orchidees — Fil Rouge
-- BDD cible: Neon (PostgreSQL 16, serverless, branching, edge functions)
-- Version  : 1.0 — Phase 3 alternative (migration depuis localStorage / db.js)
--
-- Neon est un PostgreSQL serverless compatible avec Supabase sur le plan SQL.
-- Differences vs schema_supabase.sql :
--   - Pas de auth.users Supabase : authentification externe (NextAuth, Clerk, Auth.js)
--     ou JWT verifie manuellement via un middleware Neon Edge Function
--   - Pas de RLS natif gere par Supabase Auth : on utilise des politiques
--     RLS PostgreSQL standard avec SET LOCAL app.current_user_id
--   - Branches Neon : main (prod) / dev / preview (pour chaque PR/soutenance)
--   - Optimise pour les connexions courtes (serverless) : pas de LISTEN/NOTIFY
--   - Connection pooling via @neondatabase/serverless driver (WebSockets)
--   - Schemas separes par contexte : public (donnees), auth (sessions)
-- ===========================================================================

-- ---------------------------------------------------------------------------
-- 0. EXTENSIONS
-- ---------------------------------------------------------------------------
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";
CREATE EXTENSION IF NOT EXISTS "unaccent";
-- pgvector : utile pour une future recherche semantique des descriptions orchidees
-- CREATE EXTENSION IF NOT EXISTS "vector";

-- ---------------------------------------------------------------------------
-- SCHEMA AUTH (remplace Supabase Auth — compatible NextAuth / Clerk / JWT)
-- ---------------------------------------------------------------------------

CREATE SCHEMA IF NOT EXISTS auth;

-- Table de sessions : remplace le localStorage 'mo_user_session' de storage.js
CREATE TABLE IF NOT EXISTS auth.sessions (
    id          UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id     UUID        NOT NULL,
    token       TEXT        NOT NULL UNIQUE,
    expires_at  TIMESTAMPTZ NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_sessions_token   ON auth.sessions (token);
CREATE INDEX IF NOT EXISTS idx_sessions_user_id ON auth.sessions (user_id);

COMMENT ON TABLE auth.sessions IS 'Sessions utilisateurs. Remplace le localStorage mo_user_session de storage.js. Compatible JWT externe (NextAuth, Clerk).';

-- ---------------------------------------------------------------------------
-- 1. TABLE : users
-- ---------------------------------------------------------------------------
-- Correspond a /assets/js/data/users.json
-- Dans un contexte Neon + NextAuth : ce sont les donnees etendues (profil metier).
-- L authentification (hash MDP) est deleguee a NextAuth/Clerk.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.users (
    id          UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    -- email unique : cle de jointure avec le systeme d auth externe
    email       VARCHAR(255) NOT NULL UNIQUE,
    nom         VARCHAR(100) NOT NULL,
    prenom      VARCHAR(100) NOT NULL,
    -- Si auth interne (pas Clerk) : hash argon2id du mot de passe
    password_hash TEXT,
    -- role : 'user' | 'admin' | 'moderateur'
    -- Meme logique que dans administration.js : currentUser.role !== "admin"
    role        VARCHAR(20)  NOT NULL DEFAULT 'user' CHECK (role IN ('user', 'admin', 'moderateur')),
    -- Timestamps propres (remplace created "15/01/2026" et modified "01/04/2026" du JSON)
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

ALTER TABLE auth.sessions ADD CONSTRAINT fk_sessions_user
    FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;

CREATE OR REPLACE FUNCTION public.fn_set_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_users_updated_at
    BEFORE UPDATE ON public.users
    FOR EACH ROW EXECUTE FUNCTION public.fn_set_updated_at();

COMMENT ON TABLE public.users IS 'Profils utilisateurs. Correspond a users.json. Remplace auth-service.js (localStorage) et la logique de session dans storage.js.';
COMMENT ON COLUMN public.users.password_hash IS 'Hash argon2id. NULL si authentification externe (OAuth, Clerk, NextAuth). Securite phase 3.';

-- ---------------------------------------------------------------------------
-- 2. TABLE : orchids
-- ---------------------------------------------------------------------------
-- Identique a Supabase mais optimise pour Neon :
--   - search_vector GENERATED STORED (supporte par Neon PG16)
--   - Pas de proposed_by UUID (simplifie pour MVP)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.orchids (
    id              TEXT        PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,
    vernacular      VARCHAR(200),
    botanical_order VARCHAR(100),
    species         VARCHAR(200),
    genre           VARCHAR(100) NOT NULL,
    family          VARCHAR(100) NOT NULL DEFAULT 'Orchidaceae',
    subfamily       VARCHAR(100),
    tribu           VARCHAR(100),
    subtribu        VARCHAR(100),
    behavior        VARCHAR(100),
    discovered      VARCHAR(200),
    origin          TEXT,
    img             TEXT,
    short_desc      TEXT,
    long_desc       TEXT,
    -- Vecteur full-text genere automatiquement (remplace searchOrchids() en JS)
    search_vector   TSVECTOR GENERATED ALWAYS AS (
        to_tsvector('french',
            COALESCE(name, '') || ' ' ||
            COALESCE(vernacular, '') || ' ' ||
            COALESCE(short_desc, '') || ' ' ||
            COALESCE(origin, '')
        )
    ) STORED,
    is_published    BOOLEAN     NOT NULL DEFAULT TRUE,
    proposed_by_id  UUID        REFERENCES public.users(id) ON DELETE SET NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TRIGGER trg_orchids_updated_at
    BEFORE UPDATE ON public.orchids
    FOR EACH ROW EXECUTE FUNCTION public.fn_set_updated_at();

CREATE INDEX IF NOT EXISTS idx_orchids_search    ON public.orchids USING GIN (search_vector);
CREATE INDEX IF NOT EXISTS idx_orchids_name_trgm ON public.orchids USING GIN (name gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_orchids_behavior  ON public.orchids (behavior);

COMMENT ON TABLE public.orchids IS 'Catalogue botanique. Correspond a orchids.json. search_vector remplace searchOrchids() dans orchid-service.js.';

-- ---------------------------------------------------------------------------
-- 3. TABLE : collections
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.collections (
    id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    legacy_id       TEXT,
    user_id         UUID        NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    orchid_id       TEXT        NOT NULL REFERENCES public.orchids(id) ON DELETE RESTRICT,
    location        VARCHAR(200),
    notes           TEXT,
    temp            VARCHAR(50),
    hygro           VARCHAR(50),
    light           VARCHAR(100),
    ventilation     VARCHAR(100),
    added_at        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TRIGGER trg_collections_updated_at
    BEFORE UPDATE ON public.collections
    FOR EACH ROW EXECUTE FUNCTION public.fn_set_updated_at();

CREATE INDEX IF NOT EXISTS idx_collections_user_id   ON public.collections (user_id);
CREATE INDEX IF NOT EXISTS idx_collections_orchid_id ON public.collections (orchid_id);
CREATE UNIQUE INDEX IF NOT EXISTS uq_collections_user_legacy ON public.collections (user_id, legacy_id);

COMMENT ON TABLE public.collections IS 'Collection personnelle utilisateur. Remplace collections.json et collection-service.js.';
COMMENT ON COLUMN public.collections.legacy_id IS 'Cle JS originale col-xxxxx pour migration localStorage';

-- ---------------------------------------------------------------------------
-- 4. TABLE : soins
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.soins (
    id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    legacy_id       TEXT        UNIQUE,
    collection_id   UUID        NOT NULL REFERENCES public.collections(id) ON DELETE CASCADE,
    soin_date       DATE        NOT NULL DEFAULT CURRENT_DATE,
    type            VARCHAR(100) NOT NULL,
    notes           TEXT,
    engrais         BOOLEAN     NOT NULL DEFAULT FALSE,
    substrat        BOOLEAN     NOT NULL DEFAULT FALSE,
    ravageurs       BOOLEAN     NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_soins_collection_id ON public.soins (collection_id);
CREATE INDEX IF NOT EXISTS idx_soins_date          ON public.soins (soin_date DESC);

COMMENT ON TABLE public.soins IS 'Historique des soins. Extrait du careHistory[] de collections.json. Correspond a soins.json.';

-- ---------------------------------------------------------------------------
-- 5. TABLE : conseils
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.conseils (
    id              TEXT        PRIMARY KEY,
    type            VARCHAR(20)  NOT NULL CHECK (type IN ('category', 'species')),
    name            VARCHAR(200) NOT NULL,
    img             TEXT,
    category        VARCHAR(100),
    content         TEXT,
    care_cards      JSONB,
    orchid_id       TEXT        REFERENCES public.orchids(id) ON DELETE SET NULL,
    search_vector   TSVECTOR GENERATED ALWAYS AS (
        to_tsvector('french',
            COALESCE(name, '') || ' ' ||
            COALESCE(content, '') || ' ' ||
            COALESCE(category, '')
        )
    ) STORED,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TRIGGER trg_conseils_updated_at
    BEFORE UPDATE ON public.conseils
    FOR EACH ROW EXECUTE FUNCTION public.fn_set_updated_at();

CREATE INDEX IF NOT EXISTS idx_conseils_search ON public.conseils USING GIN (search_vector);
CREATE INDEX IF NOT EXISTS idx_conseils_type   ON public.conseils (type);
CREATE INDEX IF NOT EXISTS idx_conseils_orchid ON public.conseils (orchid_id);

COMMENT ON TABLE public.conseils IS 'Fiches conseils. Correspond a conseils.json. search_vector remplace searchConseils() dans conseil-service.js.';

-- ---------------------------------------------------------------------------
-- 6. TABLE : notifications
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.notifications (
    id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    legacy_id       INTEGER,
    user_id         UUID        NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    collection_id   UUID        REFERENCES public.collections(id) ON DELETE SET NULL,
    type            VARCHAR(50)  NOT NULL DEFAULT 'rappel_soin',
    message         TEXT         NOT NULL,
    is_read         BOOLEAN      NOT NULL DEFAULT FALSE,
    notif_date      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON public.notifications (user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_unread  ON public.notifications (user_id) WHERE is_read = FALSE;

COMMENT ON TABLE public.notifications IS 'Notifications utilisateur. Correspond a notifications.json.';

-- ===========================================================================
-- ROW LEVEL SECURITY (RLS) — Pattern Neon sans Supabase Auth
-- ===========================================================================
-- Sans Supabase Auth, le user_id courant est injecte via un parametre de session :
--   SET LOCAL app.current_user_id = 'uuid-de-lutilisateur';
-- Ce pattern est utilise avec @neondatabase/serverless dans les Edge Functions.
-- ===========================================================================

ALTER TABLE public.users          ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.collections    ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.soins          ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.notifications  ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.orchids        ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.conseils       ENABLE ROW LEVEL SECURITY;

-- Fonction helper : recupere l UUID injecte par le middleware
CREATE OR REPLACE FUNCTION public.current_app_user_id()
RETURNS UUID LANGUAGE sql STABLE AS $$
    SELECT NULLIF(current_setting('app.current_user_id', TRUE), '')::UUID;
$$;

-- Politiques : users
CREATE POLICY "user_see_own_record"
    ON public.users FOR SELECT
    USING (id = public.current_app_user_id());

CREATE POLICY "user_update_own_record"
    ON public.users FOR UPDATE
    USING (id = public.current_app_user_id());

CREATE POLICY "admin_all_users"
    ON public.users FOR ALL
    USING (
        EXISTS (SELECT 1 FROM public.users u WHERE u.id = public.current_app_user_id() AND u.role = 'admin')
    );

-- Politiques : collections
CREATE POLICY "user_see_own_collection"
    ON public.collections FOR SELECT
    USING (user_id = public.current_app_user_id());

CREATE POLICY "user_manage_own_collection"
    ON public.collections FOR ALL
    USING (user_id = public.current_app_user_id());

-- Politiques : soins
CREATE POLICY "user_manage_own_soins"
    ON public.soins FOR ALL
    USING (
        collection_id IN (
            SELECT id FROM public.collections
            WHERE user_id = public.current_app_user_id()
        )
    );

-- Politiques : notifications
CREATE POLICY "user_see_own_notifications"
    ON public.notifications FOR SELECT
    USING (user_id = public.current_app_user_id());

CREATE POLICY "user_update_own_notifications"
    ON public.notifications FOR UPDATE
    USING (user_id = public.current_app_user_id());

CREATE POLICY "admin_all_notifications"
    ON public.notifications FOR ALL
    USING (
        EXISTS (SELECT 1 FROM public.users u WHERE u.id = public.current_app_user_id() AND u.role = 'admin')
    );

-- Politiques : orchids (catalogue public accessible sans authentification)
CREATE POLICY "public_read_orchids"
    ON public.orchids FOR SELECT
    USING (is_published = TRUE);

CREATE POLICY "admin_manage_orchids"
    ON public.orchids FOR ALL
    USING (
        EXISTS (SELECT 1 FROM public.users u WHERE u.id = public.current_app_user_id() AND u.role = 'admin')
    );

-- Politiques : conseils (lecture publique)
CREATE POLICY "public_read_conseils"
    ON public.conseils FOR SELECT
    USING (TRUE);

CREATE POLICY "admin_manage_conseils"
    ON public.conseils FOR ALL
    USING (
        EXISTS (SELECT 1 FROM public.users u WHERE u.id = public.current_app_user_id() AND u.role = 'admin')
    );

-- ===========================================================================
-- VUE : v_collection_complete
-- ===========================================================================

CREATE OR REPLACE VIEW public.v_collection_complete AS
SELECT
    c.id               AS collection_id,
    c.legacy_id,
    c.user_id,
    u.nom              AS user_nom,
    u.prenom           AS user_prenom,
    u.email            AS user_email,
    o.id               AS orchid_id,
    o.name             AS orchid_name,
    o.vernacular,
    o.behavior,
    o.origin,
    o.img,
    o.short_desc,
    o.long_desc,
    o.genre,
    o.family,
    c.location,
    c.notes,
    c.temp,
    c.hygro,
    c.light,
    c.ventilation,
    c.added_at,
    c.updated_at
FROM public.collections c
JOIN public.users   u ON u.id = c.user_id
JOIN public.orchids o ON o.id = c.orchid_id;

COMMENT ON VIEW public.v_collection_complete IS 'Vue enrichie : remplace les jointures manuelles dans features/collection.js entre collections et orchids.';

-- ===========================================================================
-- SPECIFIQUE NEON : BRANCHES DE DEVELOPPEMENT
-- ===========================================================================
-- Neon permet de creer des branches de base de donnees comme Git.
-- Workflow recommande pour ce projet :
--
--   main (prod)  ->  soutenance (demo stable)  ->  dev (developpement actif)
--
-- Via Neon CLI :
--   neon branch create --name soutenance --parent main
--   neon branch create --name dev --parent soutenance
--
-- Chaque branche partage les donnees seed de main au moment du fork.
-- La branche "soutenance" est une copie figee de main pour la presentation.
-- ===========================================================================

-- ===========================================================================
-- SEED (depuis users.json — sans mots de passe en clair)
-- ===========================================================================

INSERT INTO public.users (nom, prenom, email, role) VALUES
    ('Dupont',  'Jean-Marc', 'admin@mesorchidees.fr',       'user'),
    ('FERRIER', 'Jeremy',    'jeremy.ferrierpro@gmail.com', 'admin'),
    ('Martin',  'Jessica',   'jessica.amateur@gmail.com',   'user')
ON CONFLICT (email) DO NOTHING;

-- Seed orchids, conseils, notifications : voir fichiers seed_*.sql separement
