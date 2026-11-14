<?php
/**
 * Vue : page Conseils (grille serveur des fiches de culture).
 *
 * Rendue par ConseilController::index(). Variables :
 *  - $bases          : App\Entity\Conseil[] conseils de base (type 'category') — toujours affichés, agrandis
 *  - $especes        : App\Entity\Conseil[] toutes les fiches par plante (type 'species') — masquées, révélées par le JS à la saisie
 *  - $especesActives : string[] ids des fiches par plante à révéler sans JS (résultat de la recherche serveur)
 *  - $query          : string terme de recherche courant ('' si aucun)
 *
 * La recherche est un formulaire GET (repli serveur, fonctionne sans JS) que le module
 * assets/js/features/conseils-live.js enrichit d'un filtrage instantané des fiches par
 * plante pendant la frappe. Les conseils généraux (type 'category') et les fiches par
 * espèce (type 'species') partagent la même carte ; le lien pointe /conseil/{id}.
 *
 * @package App\Templates\Pages\Conseils
 * @var \App\Entity\Conseil[] $bases
 * @var \App\Entity\Conseil[] $especes
 * @var string[] $especesActives
 * @var string $query
 */

/**
 * Extrait lisible d'un contenu riche : on retire les balises HTML, on normalise
 * les espaces et on tronque proprement sur des mots entiers (avec ellipsis).
 * Utilisé pour la description des cartes (le `content` brut n'était jamais affiché).
 */
$excerpt = static function (?string $html, int $max = 150): string {
    $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $html)));
    if ($text === '') {
        return '';
    }
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    $cut   = mb_substr($text, 0, $max);
    $space = mb_strrpos($cut, ' ');
    return rtrim($space !== false ? mb_substr($cut, 0, $space) : $cut, " ,.;:·-") . '…';
};

/** Mini-carte de soins (icône + valeur) issue des careCards d'une fiche espèce. */
$careIcon = static function (string $key): string {
    return [
        'temperature' => 'fa-temperature-half',
        'arrosage'    => 'fa-droplet',
        'hygrometrie' => 'fa-droplet',
        'rempotage'   => 'fa-seedling',
        'engrais'     => 'fa-flask',
        'substrats'   => 'fa-layer-group',
    ][$key] ?? 'fa-circle-info';
};
?>
<section class="conseils-header" aria-label="Recherche dans les conseils">
    <h1 class="page-title">CONSEILS DE CULTURE</h1>

    <div class="search-container">
        <form action="<?= e(url('/conseils')) ?>" method="GET" class="search-box" role="search">
            <label for="conseil-search" class="sr-only">Rechercher un conseil</label>
            <input type="search" id="conseil-search" name="search"
                   value="<?= e($query) ?>" placeholder="Rechercher un conseil (arrosage, épiphyte…)" autocomplete="off">
            <button type="submit" class="search-btn">RECHERCHER</button>
        </form>
    </div>

    <?php if ($query !== ''): ?>
        <?php $totalResultats = count($bases) + count($especesActives); ?>
        <p class="search-summary" aria-live="polite">
            <?= $totalResultats ?> résultat<?= $totalResultats > 1 ? 's' : '' ?>
            pour « <?= e($query) ?> » —
            <a href="<?= e(url('/conseils')) ?>">voir tous les conseils</a>
        </p>
    <?php endif; ?>

    <?php if (has_role(['admin'])): ?>
        <p class="conseils-admin-bar">
            <a class="btn-primary" href="<?= e(url('/conseil/new')) ?>">+ Nouveau conseil</a>
        </p>
    <?php endif; ?>
</section>

<?php
// Les deux jeux sont fournis par le contrôleur :
//  - $bases   : conseils de base (type 'category'), toujours affichés en cartes agrandies ;
//  - $especes : TOUTES les fiches par plante (type 'species'), rendues en DOM mais masquées ;
//               le module conseils-live.js les révèle au fil de la saisie. Sans JS, seules les
//               fiches dont l'id figure dans $especesActives (recherche serveur) sont visibles.
$isSearch = $query !== '';

// Carte "conseil de base" : image pleine, titre, extrait du contenu, action.
// L'étiquette de catégorie n'est affichée que si elle APPORTE une information
// (elle est identique au titre pour les conseils de base → on l'omet, sinon on
// duplique un texte et la carte paraît « bâclée »).
$renderBaseCard = static function ($conseil) use ($excerpt): void {
    $imgUrl = $conseil->imageUrl();
    $categorie = trim((string) ($conseil->category ?? ''));
    $montreCategorie = $categorie !== ''
        && mb_strtolower($categorie) !== mb_strtolower(trim((string) $conseil->name));
    ?>
    <article class="orchid-card orchid-card--advice-base" data-conseil-name="<?= e($conseil->name) ?>">
        <?php if ($imgUrl !== null): ?>
            <img class="card-img" src="<?= e($imgUrl) ?>" alt="Illustration : <?= e($conseil->name) ?>" loading="lazy">
        <?php else: ?>
            <span class="card-img card-img--placeholder" aria-hidden="true"><i class="fa-solid fa-lightbulb"></i></span>
        <?php endif; ?>
        <div class="card-info">
            <?php if ($montreCategorie): ?>
                <p class="conseil-eyebrow"><?= e($categorie) ?></p>
            <?php endif; ?>
            <h3><?= e($conseil->name) ?></h3>
            <p class="short-desc"><?= e($excerpt($conseil->content) ?: 'Consultez la fiche complète pour les détails de culture.') ?></p>
            <a class="card-btn" href="<?= e(url('/conseil/' . rawurlencode($conseil->id))) ?>">
                LIRE<span class="sr-only"> : <?= e($conseil->name) ?></span>
            </a>
        </div>
    </article>
    <?php
};

// Carte "fiche par plante" : porte un texte de recherche (data-search) pour le filtrage
// JS, et un état masqué ($estActif=false) pour le repli sans JavaScript.
$renderSpeciesCard = static function ($conseil, bool $estActif) use ($excerpt, $careIcon): void {
    $imgUrl = $conseil->imageUrl();
    $search = mb_strtolower(trim($conseil->name . ' ' . ($conseil->category ?? '') . ' ' . ($conseil->orchidId ?? '')));
    $care   = array_filter($conseil->careCards, static fn($v) => is_string($v) && trim($v) !== '');
    ?>
    <article class="orchid-card conseil-plante-card<?= $estActif ? '' : ' is-hidden' ?>"
             data-conseil-name="<?= e($conseil->name) ?>"
             data-search="<?= e($search) ?>">
        <?php if ($imgUrl !== null): ?>
            <img class="card-img" src="<?= e($imgUrl) ?>" alt="Illustration : <?= e($conseil->name) ?>" loading="lazy">
        <?php else: ?>
            <span class="card-img card-img--placeholder" aria-hidden="true"><i class="fa-solid fa-seedling"></i></span>
        <?php endif; ?>
        <div class="card-info">
            <p class="conseil-eyebrow">Fiche espèce<?= $conseil->category ? ' · ' . e($conseil->category) : '' ?></p>
            <h3><?= e($conseil->name) ?></h3>
            <p class="short-desc"><?= e($excerpt($conseil->content, 110) ?: 'Fiche de culture complète de cette orchidée.') ?></p>
            <?php if ($care !== []): ?>
                <ul class="conseil-care-chips" aria-label="Repères de culture">
                    <?php foreach (array_slice($care, 0, 3, true) as $key => $val): ?>
                        <li><i class="fa-solid <?= e($careIcon((string) $key)) ?>" aria-hidden="true"></i><?= e($excerpt((string) $val, 28)) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <a class="card-btn" href="<?= e(url('/conseil/' . rawurlencode($conseil->id))) ?>">
                LIRE<span class="sr-only"> : <?= e($conseil->name) ?></span>
            </a>
        </div>
    </article>
    <?php
};
?>

<section class="conseils-grid-container" aria-label="Conseils de base">
    <h2 class="conseils-section-title">Conseils de base</h2>
    <?php if ($bases === []): ?>
        <p class="no-results">Aucun conseil de base ne correspond à votre recherche.</p>
    <?php else: ?>
        <div class="orchid-grid conseils-grid--base" id="conseils-base-grid">
            <?php foreach ($bases as $conseil): ?>
                <?php $renderBaseCard($conseil); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="conseils-grid-container conseils-plantes" aria-label="Conseils par plante" id="conseils-plantes">
    <h2 class="conseils-section-title">Conseils par plante</h2>

    <p class="conseils-plantes-hint"<?= $isSearch ? ' hidden' : '' ?>>
        Les conseils par plante s'affichent lorsque vous recherchez une orchidée
        (tapez son nom dans la barre de recherche ci-dessus).
    </p>
    <p class="conseils-plantes-empty" id="conseils-plantes-empty" hidden>
        Aucune fiche par plante ne correspond à votre saisie.
    </p>

    <div class="orchid-grid" id="conseils-plantes-grid">
        <?php foreach ($especes as $conseil): ?>
            <?php $renderSpeciesCard($conseil, $isSearch && in_array($conseil->id, $especesActives, true)); ?>
        <?php endforeach; ?>
    </div>
</section>
