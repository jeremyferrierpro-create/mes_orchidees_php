-- Migration de securite : un identifiant historique n'est unique que dans la collection de son proprietaire.
-- A executer une seule fois sur la base Neon existante avant de deployer api/collections/upsert.php.
BEGIN;

ALTER TABLE public.collections
    DROP CONSTRAINT IF EXISTS collections_legacy_id_key;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'uq_collections_user_legacy'
          AND conrelid = 'public.collections'::regclass
    ) THEN
        ALTER TABLE public.collections
            ADD CONSTRAINT uq_collections_user_legacy UNIQUE (user_id, legacy_id);
    END IF;
END $$;

COMMIT;
