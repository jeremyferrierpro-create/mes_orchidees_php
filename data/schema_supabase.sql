-- ===========================================================================
-- SCHEMA SUPABASE (PostgreSQL 15+) — Mes Orchidees
-- ===========================================================================
-- Auteur   : Jeremy Ferrier
-- Projet   : Mes Orchidees — Fil Rouge
-- BDD cible: Supabase (PostgreSQL 15+ avec pgvector, Row Level Security)
-- Version  : 1.0 — Phase 3 (migration depuis localStorage / db.js)
--
-- Ce schema est la traduction directe du Pattern Repository implemente dans
-- core/db.js. Chaque table JSON (/assets/js/data/*.json) devient ici une vraie
-- table PostgreSQL. Les commentaires refletent les intentions du code JS.
--
-- Architecture :
--   - extensions       : pgcrypto pour UUID, pg_trgm pour la recherche LIKE
--   - schema public    : tables metier + triggers + vues
--   - Row Level Security (RLS) : remplace le filtre JS simule dans collection-service.js
--   - auth.users       : geree par Supabase Auth (remplace le localStorage session)
-- ===========================================================================

-- ---------------------------------------------------------------------------
-- 0. EXTENSIONS
-- ---------------------------------------------------------------------------
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";
CREATE EXTENSION IF NOT EXISTS "unaccent";

-- ---------------------------------------------------------------------------
-- 1. TABLE : profiles (users)
-- ---------------------------------------------------------------------------
-- Correspond au fichier /assets/js/data/users.json
-- En Phase 3, auth.users (Supabase Auth) sera le systeme principal.
-- Champs inferes depuis users.json : id, nom, prenom, email, password, role, created, modified
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.profiles (
    id          UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    auth_id     UUID        UNIQUE REFERENCES auth.users(id) ON DELETE CASCADE,
    nom         VARCHAR(100) NOT NULL,
    prenom      VARCHAR(100) NOT NULL,
    email       VARCHAR(255) NOT NULL UNIQUE,
    role        VARCHAR(20)  NOT NULL DEFAULT 'user' CHECK (role IN ('user', 'admin', 'moderateur')),
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE public.profiles IS 'Profils utilisateurs etendus. Lie Supabase Auth (auth.users) aux donnees metier. Remplace users.json + la gestion de session localStorage dans auth-service.js.';
COMMENT ON COLUMN public.profiles.auth_id IS 'FK vers auth.users.id gere par Supabase Auth. Null si compte non encore valide.';
COMMENT ON COLUMN public.profiles.role IS 'Role applicatif : user ou admin. Verifie dans administration.js pour le controle acces.';

CREATE OR REPLACE FUNCTION public.set_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_profiles_updated_at
    BEFORE UPDATE ON public.profiles
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE OR REPLACE FUNCTION public.handle_new_user()
RETURNS TRIGGER LANGUAGE plpgsql SECURITY DEFINER AS $$
BEGIN
    INSERT INTO public.profiles (auth_id, nom, prenom, email, role)
    VALUES (
        NEW.id,
        COALESCE(NEW.raw_user_meta_data->>'nom', 'Utilisateur'),
        COALESCE(NEW.raw_user_meta_data->>'prenom', 'Nouveau'),
        NEW.email,
        'user'
    );
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_on_auth_user_created
    AFTER INSERT ON auth.users
    FOR EACH ROW EXECUTE FUNCTION public.handle_new_user();

-- ---------------------------------------------------------------------------
-- 2. TABLE : orchids
-- ---------------------------------------------------------------------------
-- Correspond a /assets/js/data/orchids.json (21 especes + seed)
-- Champs inferes : id (slug), name, vernacular, order, species, genre, family,
--                  subfamily, tribu, subtribu, behavior, discovered, origin, img,
--                  shortDesc, longDesc
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
    search_vector   TSVECTOR GENERATED ALWAYS AS (
        to_tsvector('french',
            COALESCE(name, '') || ' ' ||
            COALESCE(vernacular, '') || ' ' ||
            COALESCE(short_desc, '') || ' ' ||
            COALESCE(origin, '')
        )
    ) STORED,
    is_published    BOOLEAN NOT NULL DEFAULT TRUE,
    proposed_by     UUID    REFERENCES public.profiles(id) ON DELETE SET NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TRIGGER trg_orchids_updated_at
    BEFORE UPDATE ON public.orchids
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE INDEX IF NOT EXISTS idx_orchids_search    ON public.orchids USING GIN (search_vector);
CREATE INDEX IF NOT EXISTS idx_orchids_name_trgm ON public.orchids USING GIN (name gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_orchids_behavior  ON public.orchids (behavior);

COMMENT ON TABLE public.orchids IS 'Catalogue botanique des orchidees. Correspond a orchids.json. La colonne search_vector remplace searchOrchids() dans orchid-service.js.';
COMMENT ON COLUMN public.orchids.id IS 'Identifiant slug (ex: acacalis_cyanea). Conserve la convention du JSON pour compatibilite.';
COMMENT ON COLUMN public.orchids.search_vector IS 'Vecteur full-text genere automatiquement. Remplace le filtre includes() de searchOrchids() en JS.';

-- ---------------------------------------------------------------------------
-- 3. TABLE : collections
-- ---------------------------------------------------------------------------
-- Correspond a /assets/js/data/collections.json (vide au seed)
-- Champs inferes depuis buildCollectionItem() dans features/collection.js :
--   collectionId, orchidId, name, img, behavior, addedAt, location, notes,
--   careHistory (tableau -> table soins), temp, hygro, light, ventilation, user_id
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.collections (
    id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    legacy_id       TEXT,
    user_id         UUID        NOT NULL REFERENCES public.profiles(id) ON DELETE CASCADE,
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
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE INDEX IF NOT EXISTS idx_collections_user_id   ON public.collections (user_id);
CREATE INDEX IF NOT EXISTS idx_collections_orchid_id ON public.collections (orchid_id);
CREATE UNIQUE INDEX IF NOT EXISTS uq_collections_user_legacy ON public.collections (user_id, legacy_id);

COMMENT ON TABLE public.collections IS 'Collection personnelle d un utilisateur. Table de jointure enrichie entre profiles et orchids. Remplace collections.json et la logique de filtrage par user_id dans collection-service.js.';
COMMENT ON COLUMN public.collections.legacy_id IS 'Ancienne cle JS (col-xxxxx). Permet la migration des donnees localStorage sans perte.';

-- ---------------------------------------------------------------------------
-- 4. TABLE : soins
-- ---------------------------------------------------------------------------
-- Correspond a /assets/js/data/soins.json (vide au seed)
-- Champs inferes depuis addCareEntry() dans collection-service.js :
--   id (care-xxx), collectionId, date, type, notes
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.soins (
    id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    legacy_id       TEXT        UNIQUE,
    collection_id   UUID        NOT NULL REFERENCES public.collections(id) ON DELETE CASCADE,
    soin_date       DATE        NOT NULL DEFAULT CURRENT_DATE,
    type            VARCHAR(100) NOT NULL,
    notes           TEXT,
    engrais         BOOLEAN     DEFAULT FALSE,
    substrat        BOOLEAN     DEFAULT FALSE,
    ravageurs       BOOLEAN     DEFAULT FALSE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_soins_collection_id ON public.soins (collection_id);
CREATE INDEX IF NOT EXISTS idx_soins_date          ON public.soins (soin_date DESC);

COMMENT ON TABLE public.soins IS 'Historique des soins par plante. Extrait du tableau careHistory[] embarque dans collections.json. Correspond a soins.json et a addCareEntry() dans collection-service.js.';

-- ---------------------------------------------------------------------------
-- 5. TABLE : conseils
-- ---------------------------------------------------------------------------
-- Correspond a /assets/js/data/conseils.json (434 lignes, 2 types : category & species)
-- Champs inferes : id, type, name, img, category, content, careCards (JSONB)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.conseils (
    id              TEXT        PRIMARY KEY,
    type            VARCHAR(20)  NOT NULL CHECK (type IN ('category', 'species')),
    name            VARCHAR(200) NOT NULL,
    img             TEXT,
    category        VARCHAR(100),
    content         TEXT,
    care_cards      JSONB,
    orchid_id       TEXT REFERENCES public.orchids(id) ON DELETE SET NULL,
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
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE INDEX IF NOT EXISTS idx_conseils_search ON public.conseils USING GIN (search_vector);
CREATE INDEX IF NOT EXISTS idx_conseils_type   ON public.conseils (type);
CREATE INDEX IF NOT EXISTS idx_conseils_orchid ON public.conseils (orchid_id);

COMMENT ON TABLE public.conseils IS 'Fiches conseils et categories generales. Correspond a conseils.json. Deux types : category et species (fiche par orchidee, id = fiche-xxx). care_cards en JSONB preserve la structure flexible du JS.';
COMMENT ON COLUMN public.conseils.care_cards IS 'Carte de soins JSONB : {temperature, arrosage, hygrometrie, rempotage, engrais, substrats}. JSONB permet de requeter chaque champ.';

-- ---------------------------------------------------------------------------
-- 6. TABLE : notifications
-- ---------------------------------------------------------------------------
-- Correspond a /assets/js/data/notifications.json
-- Champs inferes : id, userId, collectionId, date, type, message, isRead
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.notifications (
    id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    legacy_id       INTEGER,
    user_id         UUID        NOT NULL REFERENCES public.profiles(id) ON DELETE CASCADE,
    collection_id   UUID        REFERENCES public.collections(id) ON DELETE SET NULL,
    type            VARCHAR(50)  NOT NULL DEFAULT 'rappel_soin',
    message         TEXT         NOT NULL,
    is_read         BOOLEAN      NOT NULL DEFAULT FALSE,
    notif_date      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON public.notifications (user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_unread  ON public.notifications (user_id) WHERE is_read = FALSE;

COMMENT ON TABLE public.notifications IS 'Notifications utilisateur (rappels de soins, alertes). Correspond a notifications.json. is_read remplace le champ isRead booleen du JSON.';

-- ===========================================================================
-- ROW LEVEL SECURITY (RLS)
-- ===========================================================================
-- Remplace le filtre JS simule dans collection-service.js :
--   const filtered = data.filter(item => String(item.user_id) === String(user.id));
-- ===========================================================================

ALTER TABLE public.profiles       ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.collections    ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.soins          ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.notifications  ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.orchids        ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.conseils       ENABLE ROW LEVEL SECURITY;

-- Politiques : profiles
CREATE POLICY "user_see_own_profile"
    ON public.profiles FOR SELECT
    USING (auth.uid() = auth_id);

CREATE POLICY "user_update_own_profile"
    ON public.profiles FOR UPDATE
    USING (auth.uid() = auth_id);

CREATE POLICY "admin_all_profiles"
    ON public.profiles FOR ALL
    USING (
        EXISTS (SELECT 1 FROM public.profiles p WHERE p.auth_id = auth.uid() AND p.role = 'admin')
    );

-- Politiques : collections
CREATE POLICY "user_see_own_collection"
    ON public.collections FOR SELECT
    USING (user_id = (SELECT id FROM public.profiles WHERE auth_id = auth.uid()));

CREATE POLICY "user_manage_own_collection"
    ON public.collections FOR ALL
    USING (user_id = (SELECT id FROM public.profiles WHERE auth_id = auth.uid()));

-- Politiques : soins
CREATE POLICY "user_manage_own_soins"
    ON public.soins FOR ALL
    USING (
        collection_id IN (
            SELECT id FROM public.collections
            WHERE user_id = (SELECT id FROM public.profiles WHERE auth_id = auth.uid())
        )
    );

-- Politiques : notifications
CREATE POLICY "user_see_own_notifications"
    ON public.notifications FOR SELECT
    USING (user_id = (SELECT id FROM public.profiles WHERE auth_id = auth.uid()));

CREATE POLICY "user_update_own_notifications"
    ON public.notifications FOR UPDATE
    USING (user_id = (SELECT id FROM public.profiles WHERE auth_id = auth.uid()));

CREATE POLICY "admin_all_notifications"
    ON public.notifications FOR ALL
    USING (
        EXISTS (SELECT 1 FROM public.profiles p WHERE p.auth_id = auth.uid() AND p.role = 'admin')
    );

-- Politiques : orchids (catalogue public)
CREATE POLICY "public_read_orchids"
    ON public.orchids FOR SELECT
    USING (is_published = TRUE);

CREATE POLICY "admin_manage_orchids"
    ON public.orchids FOR ALL
    USING (
        EXISTS (SELECT 1 FROM public.profiles p WHERE p.auth_id = auth.uid() AND p.role = 'admin')
    );

-- Politiques : conseils (lecture publique)
CREATE POLICY "public_read_conseils"
    ON public.conseils FOR SELECT TO anon, authenticated
    USING (TRUE);

CREATE POLICY "admin_manage_conseils"
    ON public.conseils FOR ALL
    USING (
        EXISTS (SELECT 1 FROM public.profiles p WHERE p.auth_id = auth.uid() AND p.role = 'admin')
    );

-- ===========================================================================
-- VUE : v_collection_complete
-- ===========================================================================
-- Remplace le pattern JS :
--   const orchid = orchidsDatabase.find(o => o.id === item.orchidId);
-- ===========================================================================

CREATE OR REPLACE VIEW public.v_collection_complete AS
SELECT
    c.id               AS collection_id,
    c.legacy_id,
    c.user_id,
    p.nom              AS user_nom,
    p.prenom           AS user_prenom,
    p.email            AS user_email,
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
JOIN public.profiles   p ON p.id = c.user_id
JOIN public.orchids    o ON o.id = c.orchid_id;

COMMENT ON VIEW public.v_collection_complete IS 'Vue enrichie : remplace les jointures manuelles dans features/collection.js entre collections et orchids.';

-- ===========================================================================
-- SEED : donnees initiales (depuis users.json — sans les mots de passe en clair)
-- ===========================================================================

INSERT INTO public.profiles (nom, prenom, email, role) VALUES
    ('Dupont',  'Jean-Marc', 'admin@mesorchidees.fr',       'user'),
    ('FERRIER', 'Jeremy',    'jeremy.ferrierpro@gmail.com', 'admin'),
    ('Martin',  'Jessica',   'jessica.amateur@gmail.com',   'user')
ON CONFLICT (email) DO NOTHING;

-- Seed orchids, conseils, notifications : voir fichiers seed_*.sql separement
