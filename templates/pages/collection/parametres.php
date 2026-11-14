<?php
/**
 * Vue : paramétrage des endroits de culture (/macollection/parametres, connecté uniquement).
 *
 * Rendue par CollectionController::parametres(). Permet au collectionneur de définir
 * « l'endroit où se situe sa collection » : un ou plusieurs lieux nommés, chacun porteur
 * de constantes climatiques structurées (température min/max, hygrométrie min/max, lumière,
 * ventilation). Ces endroits alimentent la carte « CLIMAT » du tableau de bord et auquel
 * chaque plante peut être rattachée (une plante = un seul endroit).
 *
 * Façade sans JavaScript : l'édition d'un endroit existant passe par ?edit={id} (la page
 * rejoue alors le formulaire pré-rempli sur la route /update/emplacement).
 *
 * Variables :
 *  - $emplacements : App\Entity\Emplacement[] (endroits de l'utilisateur — RG1)
 *  - $parEndroit   : array emplacement_id => int (nb de plantes rattachées)
 *  - $lumieres     : string[] ('faible','moyenne','forte')
 *  - $csrf         : string
 *
 * @package App\Templates\Pages\Collection
 * @var \App\Entity\Emplacement[] $emplacements
 * @var array $parEndroit
 * @var string[] $lumieres
 * @var string $csrf
 */

// Endroit en cours d'édition éventuel (?edit={id}) — ignoré s'il n'appartient pas à l'utilisateur
// (la liste $emplacements est déjà filtrée RG1 par le contrôleur).
$editing = null;
$editId  = (string) ($_GET['edit'] ?? '');
if ($editId !== '') {
    foreach ($emplacements as $e) {
        if ($e->id === $editId) {
            $editing = $e;
            break;
        }
    }
}
$action = url($editing !== null ? '/update/emplacement' : '/emplacement/new');
$nbPlantesTotal = array_sum($parEndroit);
?>
<section class="collection-page collection-params">
    <nav class="detail-breadcrumb" aria-label="Fil d'Ariane">
        <a href="<?= e(url('/macollection')) ?>">← Retour à ma collection</a>
    </nav>

    <h1 class="page-title">Paramètres de ma collection</h1>

    <p class="collection-params__intro">
        Définissez ici <strong>l'endroit où se situe chaque plante</strong> de votre collection et
        ses constantes de culture. La carte <strong>Climat</strong> de votre tableau de bord affiche
        la synthèse de ces endroits. Une plante ne peut être rattachée qu'à <em>un seul</em> endroit.
    </p>

    <!-- ============================================================= -->
    <!-- Liste des endroits existants                                  -->
    <!-- ============================================================= -->
    <section class="collection-params__list" aria-label="Mes endroits de culture">
        <h2 class="section-title">Mes endroits <span class="count-badge"><?= count($emplacements) ?></span></h2>

        <?php if ($emplacements === []): ?>
            <p class="no-results">
                Aucun endroit pour le moment. Ajoutez-en un ci-dessous pour renseigner le climat de votre collection.
            </p>
        <?php else: ?>
            <ul class="emplacement-list">
                <?php foreach ($emplacements as $e): ?>
                    <li class="emplacement-item">
                        <div class="emplacement-item__head">
                            <h3><?= e($e->nom) ?></h3>
                            <span class="emplacement-item__plants">
                                <?= (int) ($parEndroit[$e->id] ?? 0) ?> plante<?= (int) ($parEndroit[$e->id] ?? 0) > 1 ? 's' : '' ?>
                            </span>
                        </div>
                        <dl class="emplacement-item__values">
                            <div>
                                <dt><i class="fa-solid fa-temperature-half" aria-hidden="true"></i> Température</dt>
                                <dd><?= e($e->tempLabel() ?? '—') ?></dd>
                            </div>
                            <div>
                                <dt><i class="fa-solid fa-droplet" aria-hidden="true"></i> Hygrométrie</dt>
                                <dd><?= e($e->hygroLabel() ?? '—') ?></dd>
                            </div>
                            <div>
                                <dt><i class="fa-solid fa-sun" aria-hidden="true"></i> Lumière</dt>
                                <dd><?= e($e->lightLabel() ?? '—') ?></dd>
                            </div>
                            <?php if ($e->ventilation !== null): ?>
                                <div>
                                    <dt><i class="fa-solid fa-fan" aria-hidden="true"></i> Ventilation</dt>
                                    <dd><?= e($e->ventilation) ?></dd>
                                </div>
                            <?php endif; ?>
                        </dl>
                        <div class="emplacement-item__actions">
                            <a class="btn-secondary btn-mini" href="<?= e(url('/macollection/parametres')) ?>?edit=<?= e($e->id) ?>">Modifier</a>
                            <form method="post" action="<?= e(url('/emplacement/delete/' . rawurlencode($e->id))) ?>"
                                  class="inline-form js-confirm-delete"
                                  data-confirm="Supprimer cet emplacement ? Les plantes rattachées resteront dans votre collection, sans endroit.">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <button type="submit" class="btn-danger btn-mini">Supprimer</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- ============================================================= -->
    <!-- Formulaire d'ajout / d'édition                                -->
    <!-- ============================================================= -->
    <section class="collection-params__form" id="emplacement-form" aria-label="<?= $editing !== null ? 'Modifier un emplacement' : 'Ajouter un emplacement' ?>">
        <h2 class="section-title"><?= $editing !== null ? 'Modifier l\'emplacement' : 'Ajouter un emplacement' ?></h2>
        <?php if ($editing !== null): ?>
            <p class="field-help">Vous modifiez « <?= e($editing->nom) ?> ». Le retour à la liste se fait via le lien « Retour à ma collection ».</p>
        <?php endif; ?>
        <form method="post" action="<?= e($action) ?>" class="collection-form emplacement-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <?php if ($editing !== null): ?>
                <input type="hidden" name="id" value="<?= e($editing->id) ?>">
            <?php endif; ?>

            <fieldset class="form-section">
                <legend>Identification</legend>
                <div class="form-field">
                    <label for="nom">Nom de l'emplacement *</label>
                    <input type="text" id="nom" name="nom" required maxlength="200"
                           value="<?= e($editing->nom ?? '') ?>"
                           placeholder="Ex : Serre chaude, Fenêtre Est du salon">
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend>Conditions de culture</legend>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="temp_min">Température min (°C)</label>
                        <input type="number" id="temp_min" name="temp_min" step="0.5" min="-20" max="60"
                               value="<?= e($editing?->tempMin !== null ? (string) $editing->tempMin : '') ?>" placeholder="Ex : 18">
                    </div>
                    <div class="form-field">
                        <label for="temp_max">Température max (°C)</label>
                        <input type="number" id="temp_max" name="temp_max" step="0.5" min="-20" max="60"
                               value="<?= e($editing?->tempMax !== null ? (string) $editing->tempMax : '') ?>" placeholder="Ex : 25">
                    </div>
                    <div class="form-field">
                        <label for="hygro_min">Hygrométrie min (%)</label>
                        <input type="number" id="hygro_min" name="hygro_min" step="1" min="0" max="100"
                               value="<?= e($editing?->hygroMin !== null ? (string) $editing->hygroMin : '') ?>" placeholder="Ex : 60">
                    </div>
                    <div class="form-field">
                        <label for="hygro_max">Hygrométrie max (%)</label>
                        <input type="number" id="hygro_max" name="hygro_max" step="1" min="0" max="100"
                               value="<?= e($editing?->hygroMax !== null ? (string) $editing->hygroMax : '') ?>" placeholder="Ex : 75">
                    </div>
                    <div class="form-field">
                        <label for="lumiere">Lumière</label>
                        <select id="lumiere" name="lumiere">
                            <option value="">— Non réglé —</option>
                            <?php foreach ($lumieres as $l): ?>
                                <option value="<?= e($l) ?>"<?= ($editing->lumiere ?? '') === $l ? ' selected' : '' ?>><?= e(ucfirst($l)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="ventilation">Ventilation</label>
                        <input type="text" id="ventilation" name="ventilation" maxlength="100"
                               value="<?= e($editing->ventilation ?? '') ?>" placeholder="Ex : Bonne circulation d'air">
                    </div>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="btn-primary"><?= $editing !== null ? 'Enregistrer les modifications' : 'Ajouter l\'emplacement' ?></button>
                <?php if ($editing !== null): ?>
                    <a class="btn-secondary" href="<?= e(url('/macollection/parametres')) ?>">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <p class="collection-params__footnote">
        <?= $nbPlantesTotal ?> plante<?= $nbPlantesTotal > 1 ? 's' : '' ?> sur <?= count($emplacements) ?> endroit<?= count($emplacements) > 1 ? 's' : '' ?>.
        Rattachez chaque plante depuis sa fiche (« Conditions de culture » → Emplacement).
    </p>
</section>
