<?php
/**
 * Vue : file d'attente de modération (/moderation, admin + modérateur).
 *
 * Rendue par ModerationController::index(). Variables :
 *  - $pending     : \App\Entity\Orchid[] (propositions is_published = 0)
 *  - $nbEnAttente : int
 *  - $csrf        : string
 *
 * Chaque proposition offre : un PANNEAU DE VÉRIFICATION des caractéristiques (le
 * modérateur contrôle avant de trancher), ÉDITER (Correction des données — ouverte
 * aux modérateurs depuis la bascule GRANT_MODO de /update/orchid), APPROUVER (publie
 * + notifie l'auteur, RG5 — sous réserve de cocher « je certifie avoir vérifié ») et
 * REFUSER (rejet SOFT : la fiche est conservée pour son propriétaire, jamais publiée,
 * + notifie l'auteur, RG5).
 *
 * @package App\Templates\Pages\Moderation
 * @var \App\Entity\Orchid[] $pending
 * @var int $nbEnAttente
 * @var string $csrf
 */
?>
<section class="moderation-page">
    <h1 class="page-title">MODÉRATION</h1>
    <p class="collection-intro"><?= $nbEnAttente ?> proposition<?= $nbEnAttente > 1 ? 's' : '' ?> en attente de validation.</p>
    <p><a class="btn-secondary" href="<?= e(url('/administration')) ?>">← Retour à l'administration</a></p>

    <?php if ($pending === []): ?>
        <p class="no-results">Aucune proposition à modérer. Tout est à jour.</p>
    <?php else: ?>
        <div class="orchid-grid">
            <?php foreach ($pending as $o): $img = $o->imageUrl(); ?>
                <article class="orchid-card moderation-card">
                    <?php if ($img !== null): ?>
                        <img class="card-img" src="<?= e($img) ?>" alt="<?= e($o->scientificName()) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="card-img card-img--placeholder" aria-hidden="true"></span>
                    <?php endif; ?>
                    <div class="card-info">
                        <?php /* Le titre reprend le nom saisi par l'auteur (comme la carte du catalogue) :
                                  scientificName() ne rend que le genre ici, toutes les propositions
                                  Cattleya deviendraient indiscernables pour le modérateur. */ ?>
                        <h3><?= e($o->name) ?></h3>
                        <?php if ($o->vernacular): ?><p class="vernacular-name"><?= e($o->vernacular) ?></p><?php endif; ?>

                        <?php /* Panneau de vérification : le modérateur CONTROLLE les
                                  caractéristiques saisies par l'auteur AVANT d'approuver.
                                  Un champ absent de la proposition (non capturé au dépôt)
                                  s'affiche en « — » : c'est ce qu'il reste à compléter via
                                  « Éditer ». La description longue ne tient pas sur la carte,
                                  elle se révise dans l'éditeur. */ ?>
                        <dl class="moderation-review">
                            <div><dt>Genre</dt><dd><?= e($o->genre !== '' ? $o->genre : '—') ?></dd></div>
                            <div><dt>Famille</dt><dd><?= e($o->family !== '' ? $o->family : '—') ?></dd></div>
                            <div><dt>Comportement</dt><dd><?= e($o->behavior ?? '—') ?></dd></div>
                            <div><dt>Espèce</dt><dd><?= e($o->species ?? '—') ?></dd></div>
                            <div><dt>Ordre</dt><dd><?= e($o->botanicalOrder ?? '—') ?></dd></div>
                            <div><dt>Origine</dt><dd><?= e($o->origin ?? '—') ?></dd></div>
                        </dl>
                        <p class="short-desc"><?= e($o->shortDesc ?? '—') ?></p>

                        <div class="form-actions moderation-actions">
                            <a class="btn-secondary btn-mini" href="<?= e(url('/update/orchid/' . rawurlencode($o->id))) ?>">Éditer</a>
                            <form method="post" action="<?= e(url('/moderation/' . rawurlencode($o->id) . '/approve')) ?>" class="inline-form moderation-approve">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <?php /* Obligation de vérifier : la case est « required »,
                                          le navigateur empêche d'approuver sans l'avoir cochée.
                                          Le serveur reste tolérant sur ce champ (rétrocompatible) ;
                                          la contrainte est avant tout le parcours de revue ci-dessus. */ ?>
                                <label class="moderation-certify">
                                    <input type="checkbox" name="verified" required>
                                    Je certifie avoir vérifié les caractéristiques
                                </label>
                                <button type="submit" class="btn-primary btn-mini">Approuver</button>
                            </form>
                            <form method="post" action="<?= e(url('/moderation/' . rawurlencode($o->id) . '/reject')) ?>"
                                  class="inline-form js-confirm-delete" data-confirm="Refuser et supprimer cette proposition ?">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <button type="submit" class="btn-danger btn-mini">Refuser</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
