<!DOCTYPE html>
<!-- Page branchee sur /assets/js/data/*.json via db.js (simulation Supabase) - 100% dynamique -->
<html lang="fr">
<head>
    <?php require_once 'includes/head.php'; ?>
    <title>Conseils - Mes Orchidées</title>
</head>
<body>
    <a class="skip-link" href="#main-content">Aller au contenu</a>
        <?php require_once 'includes/sidebar.php'; ?>

    <header id="main-header" role="banner">
        <img src="./assets/images/site/logotransparent.png" alt="Logo de Mes Orchidées">
        <p class="header-tagline">Encyclopédie &amp; gestion de collections</p>
        <button type="button" class="menu-toggle" id="menu-toggle-btn" aria-label="Ouvrir ou fermer le menu" aria-controls="main-sidebar" aria-expanded="false">
            <span class="hamburger-bar"></span>
        </button>
    </header>

    <main class="main-content" id="main-content" role="main">
        <section class="advice-header" aria-label="Conseils de culture">
            <h1 class="page-title">CONSEILS</h1>
        </section>

        <section class="conseils-search" aria-label="Rechercher une fiche de culture">
            <form class="search-box" id="conseil-search-form" action="#" method="GET" role="search" novalidate>
                <label for="conseil-search-input" class="sr-only">Saisir le nom d'une orchidée ou d'une rubrique</label>
                <input type="text" id="conseil-search-input" name="search" placeholder="Saisir le nom d'une orchidée ..." autocomplete="off">
                <button type="submit" class="search-btn" id="conseil-search-btn">RECHERCHER</button>
            </form>
            <p id="conseil-search-help" class="conseil-search-help" aria-live="polite"></p>
        </section>

        <section class="conseils-grid" aria-label="Rubriques de conseils, cliquez pour consulter">
            <!-- Chaque carte a un data-conseil-id qui correspond à l'id dans conseils-data.js -->
            <article class="conseil-card" data-conseil-id="conseils-base" data-category="Conseils de base" tabindex="0" role="button" aria-label="Consulter les conseils de base">
                <h2>Conseils de base</h2>
                <p>Débutez votre collection d'Orchidées avec ces conseils de base. Selon vos affinités, vous évoluerez vers les épiphytes, terrestres ou hémi-épiphytes. Bonne culture !</p>
                <span class="conseil-card-cta">Voir le conseil</span>
            </article>

            <article class="conseil-card" data-conseil-id="conseils-epiphytes" data-category="Pour les épiphytes" tabindex="0" role="button" aria-label="Consulter les conseils pour les épiphytes">
                <h2>Pour les épiphytes</h2>
                <p>Vous aimez les Orchidées épiphytes. Cette rubrique est faîtes pour vous. Vous y trouverez tous les conseils de culture pour réussir vos épiphytes, comme un professionnel ! Bonne culture !</p>
                <span class="conseil-card-cta">Voir le conseil</span>
            </article>

            <article class="conseil-card" data-conseil-id="conseils-terrestres" data-category="Pour les terrestres" tabindex="0" role="button" aria-label="Consulter les conseils pour les terrestres">
                <h2>Pour les terrestres</h2>
                <p>Vous aimez les Orchidées terrestres. Cette rubrique est faîtes pour vous. Vous y trouverez tous les conseils de culture pour réussir vos terrestres, comme un professionnel ! Bonne culture !</p>
                <span class="conseil-card-cta">Voir le conseil</span>
            </article>

            <article class="conseil-card" data-conseil-id="conseils-hemi-epiphytes" data-category="Pour les hémi-épiphytes" tabindex="0" role="button" aria-label="Consulter les conseils pour les hémi-épiphytes">
                <h2>Pour les hémi-épiphytes</h2>
                <p>Vous aimez les Orchidées hémi-épiphytes. Cette rubrique est faîtes pour vous. Vous y trouverez tous les conseils de culture pour réussir vos hémi-épiphytes, comme un professionnel ! Bonne culture !</p>
                <span class="conseil-card-cta">Voir le conseil</span>
            </article>

            <article class="conseil-card" data-conseil-id="conseils-flask" data-category="Sortie de flask" tabindex="0" role="button" aria-label="Consulter les conseils sur la sortie de flask">
                <h2>Sortie de flask</h2>
                <p>Vous êtes un amateur orchidophile. Vous souhaitez cultiver des Orchidées sorties de flask ? Ici, vous trouverez tous nos conseils de pro pour réussir vos sorties de flask.</p>
                <span class="conseil-card-cta">Voir le conseil</span>
            </article>

            <article class="conseil-card" data-conseil-id="conseils-apres-achat" data-category="Après achat" tabindex="0" role="button" aria-label="Consulter les conseils d'après achat">
                <h2>Après achat</h2>
                <p>Vous venez d'acheter vos premières Orchidées ? Découvrez nos conseils d'après achat. Nous vous conseillons de consulter les conseils de base, par la suite, pour vous familiariser avec vos Orchidées.</p>
                <span class="conseil-card-cta">Voir le conseil</span>
            </article>
        </section>

        <section class="advice-catalog" id="advice-catalog" aria-label="Résultats de recherche">
            <h2 class="section-title">Fiches trouvées</h2>
            <div id="advice-results" class="advice-results" aria-live="polite" aria-label="Fiches de culture correspondantes"></div>
        </section>
    </main>

    <section class="modal-overlay" id="conseil-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="conseil-modal-title">
        <div class="modal-container conseil-modal" role="document">
            <button type="button" class="modal-close" id="conseil-modal-close" aria-label="Fermer la fiche">
                <span aria-hidden="true">&times;</span>
            </button>

            <div class="conseil-modal-header">
                <img id="conseil-modal-img" class="conseil-modal-img" src="../assets/images/site/orchidee_hero.webp" alt="Conseils">
                <div class="conseil-modal-titles">
                    <h2 class="conseil-modal-suptitle">FICHE CONSEILS</h2>
                    <h3 id="conseil-modal-title" class="conseil-modal-title"></h3>
                    <p id="conseil-modal-meta" class="conseil-modal-meta"></p>
                </div>
            </div>

            <div class="conseil-modal-body">
                <p id="conseil-modal-text" class="conseil-modal-text"></p>
            </div>

            <div class="conseil-care-grid" aria-label="Conseils de culture">
                <div class="care-card">
                    <i class="fa-solid fa-thermometer-half" aria-hidden="true"></i>
                    <span>Températures</span>
                    <strong id="care-temperature"></strong>
                </div>
                <div class="care-card">
                    <i class="fa-solid fa-droplet" aria-hidden="true"></i>
                    <span>Arrosage</span>
                    <strong id="care-arrosage"></strong>
                </div>
                <div class="care-card">
                    <i class="fa-solid fa-percent" aria-hidden="true"></i>
                    <span>Hygrométrie</span>
                    <strong id="care-hygrometrie"></strong>
                </div>
                <div class="care-card">
                    <i class="fa-solid fa-seedling" aria-hidden="true"></i>
                    <span>Rempotage</span>
                    <strong id="care-rempotage"></strong>
                </div>
                <div class="care-card">
                    <i class="fa-solid fa-flask" aria-hidden="true"></i>
                    <span>Engrais</span>
                    <strong id="care-engrais"></strong>
                </div>
                <div class="care-card">
                    <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                    <span>Substrats</span>
                    <strong id="care-substrats"></strong>
                </div>
            </div>
        </div>
    </section>

    <footer id="main-footer" role="contentinfo">
        <div class="footer-links-group"><a href="mentions.php" class="footer-link">Mentions légales</a><a href="accessibilite.php" class="footer-link">Accessibilité</a></div>
        <div class="footer-center">
            <img src="./assets/images/site/logotransparent.png" alt="Logo de Mes Orchidées">
            <p>&copy; Mes Orchidées - Projet Fil Rouge DWWM - Jeremy Ferrier - 2026/2027</p>
        </div>
        <a href="confidentialite.php" class="footer-link">Politiques de confidentialité</a>
    </footer>

    <script type="module" src="./assets/js/app.js"></script>

</body>
</html>


