<!DOCTYPE html>
<!-- Page branchee sur /assets/js/data/*.json via db.js (simulation Supabase) - 100% dynamique -->
<html lang="fr">
<head>
    <?php require_once 'includes/head.php'; ?>
    <title>Encyclopédie - Mes Orchidées</title>
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

    <main class="main-content" id="main-content">
        <section class="encyclopedia-header" aria-label="Recherche dans l'Encyclopédie">
            <h1 class="page-title">ENCYCLOPÉDIE</h1>
            <div class="search-container">
                <form id="encyclopedia-search-form" action="encyclopedie.php" method="GET" class="search-box" role="search">
                    <input type="hidden" name="csrf_token" id="csrf-token" value="">
                    <label for="search-input" class="sr-only">Rechercher une orchidée</label>
                    <input type="text" id="search-input" name="search" placeholder="Rechercher une orchidée..." autocomplete="off">
                    <button type="submit" class="search-btn">RECHERCHER</button>
                </form>
            </div>
        </section>

        <section class="encyclopedia-grid-container" aria-label="Résultats de la recherche">
            <div class="orchid-grid" id="orchid-grid-container"></div>
        </section>
    </main>

    <footer id="main-footer" role="contentinfo">
        <div class="footer-links-group"><a href="mentions.php" class="footer-link">Mentions légales</a><a href="accessibilite.php" class="footer-link">Accessibilité</a></div>
        <div class="footer-center">
            <img src="./assets/images/site/logotransparent.png" alt="Logo de Mes Orchidées">
            <p>&copy; Mes Orchidées - Projet Fil Rouge DWWM - Jeremy Ferrier - 2026/2027</p>
        </div>
        <a href="confidentialite.php" class="footer-link">Politiques de confidentialité</a>
    </footer>

    <?php require_once 'includes/orchid-modal.php'; ?>


    <script type="module" src="./assets/js/app.js"></script>

</body>
</html>



