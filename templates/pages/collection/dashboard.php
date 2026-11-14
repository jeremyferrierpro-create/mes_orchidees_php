<?php
/**
 * Vue : tableau de bord « Ma Collection » (/macollection, connecté uniquement).
 *
 * Rendue par CollectionController::dashboard(). Structure conforme à la maquette :
 * bandeau de statistiques (PLANTES / CLIMAT / NOTIFICATIONS), puis espace de gestion
 * en deux colonnes (grille COLLECTION | panneaux SOINS + CONSEILS), puis formulaire
 * d'ajout. Variables :
 *  - $collection      : App\Entity\Collection[] (plantes de l'utilisateur — RG1)
 *  - $soins           : App\Entity\Soin[] (historique récent, toutes plantes)
 *  - $rappelsParPlante: array collection_id => App\Entity\Notification (prochain rappel)
 *  - $statsPlantes    : array{total,epiphytes,terrestres,hemi}
 *  - $climat          : array{temp,hygro,light} (moyennes, valeurs ou null)
 *  - $notifications   : array{soins,alertes,sante} (compteurs du bandeau)
 *  - $notificationsFeed : App\Entity\Notification[] (flux réel affiché dans la carte)
 *  - $nomsPlantes     : array collection_id => string (nom d'affichage, lien du tableau SOINS)
 *  - $emplacements    : App\Entity\Emplacement[] (endroits de culture de l'utilisateur)
 *  - $conseil         : App\Entity\Conseil|null (fiche « espèce » d'une plante possédée)
 *  - $genres/$families/$behaviors : string[] (menus du formulaire d'ajout)
 *  - $published       : App\Entity\Orchid[] (autocomplétion)
 *  - $nbPlantes       : int
 *  - $user            : array|null (session courante)
 *  - $csrf            : string (jeton du formulaire d'ajout)
 *
 * @package App\Templates\Pages\Collection
 * @var \App\Entity\Collection[] $collection
 * @var \App\Entity\Soin[] $soins
 * @var array $rappelsParPlante
 * @var array $statsPlantes
 * @var array $climat
 * @var array $notifications
 * @var \App\Entity\Notification[] $notificationsFeed
 * @var array $nomsPlantes
 * @var \App\Entity\Emplacement[] $emplacements
 * @var \App\Entity\Conseil|null $conseil
 * @var string[] $genres
 * @var string[] $families
 * @var string[] $behaviors
 * @var \App\Entity\Orchid[] $published
 * @var int $nbPlantes
 * @var array|null $user
 * @var string $csrf
 */

/** Formate une date SQL (Y-m-d[ H:i:s]) en jj/mm/aaaa ; renvoie « — » si absente. */
$fmtDate = static function (?string $date): string {
    if ($date === null || $date === '' || strtotime($date) === false) {
        return '—';
    }
    return date('d/m/Y', strtotime($date));
};
?>
<section class="collection-page">
    <h1 class="page-title">MA COLLECTION</h1>

    <!-- ============================================================= -->
    <!-- Bandeau de statistiques (PLANTES / CLIMAT / NOTIFICATIONS)    -->
    <!-- ============================================================= -->
    <section class="collection-dashboard" aria-label="Statistiques de la collection">
        <article class="dash-card dash-plants" aria-label="Statistiques des plantes">
            <h3>Plantes</h3>
            <ul id="dash-plants-stats">
                <li><span>Total</span><strong><?= (int) $statsPlantes['total'] ?></strong></li>
                <li><span>Épiphytes</span><strong><?= (int) $statsPlantes['epiphytes'] ?></strong></li>
                <li><span>Terrestres</span><strong><?= (int) $statsPlantes['terrestres'] ?></strong></li>
                <li><span>Hémi-épiphytes</span><strong><?= (int) $statsPlantes['hemi'] ?></strong></li>
            </ul>
        </article>

        <article class="dash-card dash-climate" aria-label="Climat moyen de la collection">
            <div class="dash-card__head">
                <h3>Climat</h3>
                <a class="dash-card__settings" href="<?= e(url('/macollection/parametres')) ?>"
                   aria-label="Paramétrer les endroits de culture" title="Paramétrer les endroits de culture">
                    <i class="fa-solid fa-gear" aria-hidden="true"></i><span class="sr-only">Paramètres</span>
                </a>
            </div>
            <div class="dash-climate-values">
                <div class="climate-item">
                    <i class="fa-solid fa-temperature-half" aria-hidden="true"></i>
                    <strong><?= e($climat['temp'] ?? '—') ?></strong>
                </div>
                <div class="climate-item">
                    <i class="fa-solid fa-droplet" aria-hidden="true"></i>
                    <strong><?= e($climat['hygro'] ?? '—') ?></strong>
                </div>
                <div class="climate-item">
                    <i class="fa-solid fa-sun" aria-hidden="true"></i>
                    <strong><?= e($climat['light'] ?? '—') ?></strong>
                </div>
            </div>
            <p class="dash-climate-where">
                <?php if (!empty($climat['label'])): ?>
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    <?= e($climat['label']) ?>
                <?php else: ?>
                    <a href="<?= e(url('/macollection/parametres')) ?>">Paramétrer un endroit de culture</a>
                <?php endif; ?>
            </p>
        </article>

        <article class="dash-card dash-notifications" aria-label="Notifications">
            <div class="dash-card__head">
                <h3>Notifications</h3>
                <a class="dash-card__more" href="<?= e(url('/notifications')) ?>"
                   aria-label="Voir toutes mes notifications">Tout voir<span class="sr-only"> mes notifications</span> →</a>
            </div>

            <?php $nbNonLues = (int) ($notifications['sante'] ?? 0); ?>
            <?php if ($nbNonLues > 0): ?>
                <p class="notif-badge" role="status"><strong><?= $nbNonLues ?></strong> non lue<?= $nbNonLues > 1 ? 's' : '' ?></p>
            <?php endif; ?>

            <?php
            // Icône Font Awesome par type de notification (vocabulary Notification::TYPES).
            $iconesNotif = [
                'rappel_soin'          => 'fa-bell',
                'validation'           => 'fa-circle-check',
                'rejet'                => 'fa-circle-xmark',
                'nouvelle_proposition' => 'fa-inbox',
                'info'                 => 'fa-circle-info',
            ];
            ?>
            <?php if ($notificationsFeed === []): ?>
                <p class="notif-empty">Aucune notification pour le moment.</p>
            <?php else: ?>
                <ul class="notif-feed">
                    <?php foreach ($notificationsFeed as $notif): ?>
                        <li>
                            <a class="notif-row<?= $notif->isUnread() ? ' notif-row--unread' : '' ?>"
                               href="<?= e(url('/notification/' . rawurlencode($notif->id))) ?>">
                                <i class="fa-solid <?= e($iconesNotif[$notif->type] ?? 'fa-circle-info') ?>" aria-hidden="true"></i>
                                <span class="notif-row__text"><?= e($notif->message) ?></span>
                                <span class="notif-row__date"><?= e($notif->dateFr()) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </section>

    <!-- ============================================================= -->
    <!-- Espace de gestion : COLLECTION (2/3) | SOINS + CONSEILS (1/3) -->
    <!-- ============================================================= -->
    <section class="collection-workspace" aria-label="Espace de gestion">
        <div class="collection-panel">
            <h2 class="panel-title">Collection</h2>
            <div class="collection-grid">
                <?php foreach ($collection as $item): $img = $item->displayImageUrl(); ?>
                    <a class="collection-thumb" href="<?= e(url('/collection/' . rawurlencode($item->id))) ?>">
                        <?php if ($img !== null): ?>
                            <img src="<?= e($img) ?>" alt="<?= e($item->displayName()) ?>" loading="lazy">
                        <?php else: ?>
                            <span class="collection-thumb--noimg" aria-hidden="true"><i class="fa-solid fa-seedling"></i></span>
                        <?php endif; ?>
                        <h3><?= e($item->displayName()) ?></h3>
                        <span class="sr-only"> : ouvrir le suivi de cette plante</span>
                    </a>
                <?php endforeach; ?>
                <a class="collection-thumb collection-thumb-add" href="#collection-add"
                   data-collection-add-trigger aria-controls="collection-add" aria-haspopup="dialog"
                   aria-label="Ajouter une orchidée à ma collection">
                    <span class="add-plus" aria-hidden="true">+</span>
                </a>
            </div>
        </div>

        <div class="side-panels">
            <div class="care-panel">
                <div class="care-panel-header">
                    <h2 class="panel-title">Soins</h2>
                    <div class="care-panel-actions">
                        <a class="btn-link btn-mini" href="<?= e(url('/rappels')) ?>"
                           data-rappels-modal-trigger aria-controls="rappels-modal" aria-haspopup="dialog">Rappels</a>
                        <?php if ($collection !== []): ?>
                            <a class="btn-new-care" href="<?= e(url('/collection/' . rawurlencode($collection[0]->id))) ?>"
                               aria-label="Enregistrer un soin (ouvrir une plante)">+</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="care-table-wrap">
                    <table class="care-table" aria-label="Historique des soins">
                        <thead>
                            <tr>
                                <th scope="col">Plante</th>
                                <th scope="col">Date</th>
                                <th scope="col">Soin</th>
                                <th scope="col">Prochain rappel</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($soins === []): ?>
                                <tr><td colspan="4" class="care-empty">Aucun soin enregistré pour le moment.</td></tr>
                            <?php else: ?>
                                <?php foreach ($soins as $soin):
                                    $rappel = $rappelsParPlante[$soin->collectionId] ?? null;
                                    $lienPlante = url('/collection/' . rawurlencode($soin->collectionId));
                                    $nomPlante = $nomsPlantes[$soin->collectionId] ?? ($soin->orchidName ?? 'Ma plante'); ?>
                                    <tr>
                                        <td>
                                            <a class="care-plant-link" href="<?= e($lienPlante) ?>">
                                                <?= e($nomPlante) ?><span class="sr-only"> : ouvrir le suivi</span>
                                            </a>
                                        </td>
                                        <td><?= e($fmtDate($soin->soinDate)) ?></td>
                                        <td><?= e($soin->typeLabel()) ?></td>
                                        <td>
                                            <?php if ($rappel !== null): ?>
                                                <a class="care-rappel care-rappel--<?= e($rappel->isLate() ? 'late' : $rappel->statut) ?>"
                                                   href="<?= e(url('/rappels')) ?>"
                                                   data-rappels-modal-trigger aria-controls="rappels-modal" aria-haspopup="dialog"
                                                   title="Gérer ce rappel">
                                                    <i class="fa-solid fa-bell" aria-hidden="true"></i>
                                                    <?= e($fmtDate($rappel->notifDate)) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="care-rappel care-rappel--none">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="conseil-panel">
                <h2 class="panel-title">Conseils</h2>
                <?php if ($conseil !== null): ?>
                    <?php $care = $conseil->careCards; ?>
                    <div class="conseil-preview-card">
                        <h3><?= e($conseil->name) ?></h3>
                        <p class="conseil-preview-text"><?= e($conseil->content ?? '') ?></p>
                        <div class="conseil-care-mini">
                            <div><i class="fa-solid fa-temperature-half" aria-hidden="true"></i><span><?= e($care['temperature'] ?? '—') ?></span></div>
                            <div><i class="fa-solid fa-droplet" aria-hidden="true"></i><span><?= e($care['hygrometrie'] ?? '—') ?></span></div>
                            <div><i class="fa-solid fa-clock" aria-hidden="true"></i><span><?= e($care['arrosage'] ?? '—') ?></span></div>
                            <div><i class="fa-solid fa-seedling" aria-hidden="true"></i><span><?= e($care['rempotage'] ?? '—') ?></span></div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="conseil-empty">Ajoutez une orchidée à votre collection pour afficher ici ses conseils de culture.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Entrée « Gérer mes rappels » (Phase D) : lien vers /rappels qui devient
         ouverture de la modale quand features/rappels-modal.js est actif
         (progressive enhancement — sans JS, la page autonome prend le relais). -->
    <p class="collection-rappels-entry">
        <a class="btn-secondary" href="<?= e(url('/rappels')) ?>"
           data-rappels-modal-trigger aria-controls="rappels-modal" aria-haspopup="dialog">Gérer mes rappels</a>
    </p>

    <!-- Ajout d'une orchidée (modale). Deux chemins, un seul formulaire :
         1) RECHERCHE : l'utilisateur tape (>= 3 caractères) ; les espèces publiées de
            l'encyclopédie sont suggérées ; en sélectionner une AUTO-REMPLIT les champs
            botaniques et LIE directement la plante (champ caché orchid_id).
         2) SAISIE LIBRE : si aucune espèce ne correspond, l'utilisateur complète la
            botanique et valide → PROPOSITION transmise à la modération.
         Sans JS : le <datalist> + la validation serveur (nom → slug publié) assurent le
         repli ; la proposition reste possible en remplissant la botanique. -->
    <section class="modal-overlay collection-add-modal" id="collection-add"
             aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="collection-add-title">
        <div class="modal-container collection-modal">
            <button type="button" class="modal-close" data-collection-add-close
                    aria-label="Fermer l'ajout d'orchidée">&times;</button>
            <h2 id="collection-add-title" class="section-title" tabindex="-1">Ajouter une orchidée</h2>

            <?php
            // Jeu des espèces publiées, exposé au JS (autocomplete + auto-remplissage).
            // <script type="application/json"> n'est PAS exécuté : CSP script-src 'self' OK.
            $publishedData = array_map(static function ($o): array {
                return [
                    'id'         => $o->id,
                    'name'       => $o->name,
                    'vernacular' => $o->vernacular,
                    'genre'      => $o->genre,
                    'family'     => $o->family,
                    'behavior'   => $o->behavior,
                    'origin'     => $o->origin,
                ];
            }, $published);
            ?>
            <script type="application/json" id="published-orchid-data"><?= json_encode($publishedData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

            <form method="post" action="<?= e(url('/collection/new')) ?>" class="collection-form"
                  enctype="multipart/form-data" data-collection-add-form>
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="orchid_id" id="add_orchid_id" value="">

                <fieldset class="form-section">
                    <legend>Mon orchidée</legend>
                    <div class="form-field orchid-search-field">
                        <label for="name">Nom (latin ou vernaculaire) *</label>
                        <input type="text" id="name" name="name" required autocomplete="off"
                               placeholder="Tapez au moins 3 lettres, ex : Phalaenopsis…"
                               list="liste-especes" aria-describedby="add-mode-help">
                        <datalist id="liste-especes">
                            <?php foreach ($published as $o): ?>
                                <option value="<?= e($o->name) ?>"><?= e($o->vernacular ?: $o->genre) ?></option>
                            <?php endforeach; ?>
                        </datalist>
                        <ul class="orchid-suggest" id="orchid-suggest" role="listbox" aria-label="Espèces publiées" hidden></ul>
                        <p class="add-mode" id="add-mode-help" role="status">
                            Sélectionnez une espèce de l'encyclopédie pour la lier, ou saisissez librement pour en proposer une nouvelle.
                        </p>
                    </div>
                    <div class="form-field">
                        <label for="vernacular">Nom vernaculaire</label>
                        <input type="text" id="vernacular" name="vernacular" placeholder="Ex : Orchidée lune bleue">
                    </div>
                </fieldset>

                <fieldset class="form-section" data-proposal-fields>
                    <legend>Botanique <small>(requis uniquement pour proposer une nouvelle espèce)</small></legend>
                    <div class="form-field">
                        <label for="genre">Genre</label>
                        <!-- Saisie libre + <datalist> de suggestion : permet de proposer un
                             genre ENCORE ABSENT du catalogue (ex : Phalaenopsis). Un <select>
                             fermé, peuplé par SELECT DISTINCT, rendrait toute nouvelle espèce
                             d'un genre inconnu impossible à proposer côté navigateur. -->
                        <input type="text" id="genre" name="genre" list="liste-genres"
                               autocomplete="off" placeholder="Ex : Phalaenopsis">
                        <datalist id="liste-genres">
                            <?php foreach ($genres as $g): ?>
                                <option value="<?= e($g) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-field">
                        <label for="family">Famille</label>
                        <input type="text" id="family" name="family" list="liste-familles"
                               autocomplete="off" value="Orchidaceae" placeholder="Ex : Orchidaceae">
                        <datalist id="liste-familles">
                            <?php foreach ($families as $f): ?>
                                <option value="<?= e($f) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-field">
                        <label for="behavior">Comportement</label>
                        <input type="text" id="behavior" name="behavior" list="liste-comportements"
                               autocomplete="off" placeholder="Ex : Epiphyte">
                        <datalist id="liste-comportements">
                            <?php foreach ($behaviors as $b): ?>
                                <option value="<?= e($b) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-field">
                        <label for="origin">Origine géographique</label>
                        <input type="text" id="origin" name="origin" placeholder="Ex : Bornéo">
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>Photo</legend>
                    <div class="form-field">
                        <label for="photo">Photo de votre plante</label>
                        <input type="file" id="photo" name="photo" class="form-file"
                               accept="image/jpeg,image/png,image/webp,image/gif">
                        <small class="field-help">JPEG, PNG, WEBP ou GIF — 5 Mo max. Enregistrée dans votre collection ; visible en encyclopédie seulement si la proposition est validée.</small>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>Conditions de culture</legend>
                    <div class="form-field">
                        <label for="add_emplacement">Emplacement</label>
                        <select id="add_emplacement" name="emplacement_id">
                            <option value="">— Aucun endroit —</option>
                            <?php foreach ($emplacements as $emp): ?>
                                <option value="<?= e($emp->id) ?>"><?= e($emp->nom) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="field-help">Rattachez la plante à un endroit de culture pour alimenter la carte Climat. <a href="<?= e(url('/macollection/parametres')) ?>">Gérer mes endroits</a>.</small>
                    </div>
                    <div class="form-field">
                        <label for="location">Précision d'emplacement (libre)</label>
                        <input type="text" id="location" name="location" placeholder="Ex : Fenêtre Est du salon">
                    </div>
                    <div class="form-field">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Observations, date d'achat…"></textarea>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" data-collection-add-close>Annuler</button>
                    <button type="submit" class="btn-primary">Ajouter à ma collection</button>
                </div>
            </form>
        </div>
    </section>

    <?php // Modale « Gérer mes rappels » (Phase D) — squelette + fragment chargé à l'ouverture. ?>
    <?php include dirname(__DIR__, 2) . '/components/rappels_modal.php'; ?>
</section>
