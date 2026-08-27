<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once 'includes/head.php'; ?>
    <title>Mentions légales - Mes Orchidées</title>
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

    <main class="main-content text-content" id="main-content">
        <section class="legal-page" aria-label="Mentions légales">
            <h1 class="page-title">MENTIONS LÉGALES</h1>

            <article class="legal-section">
                <h2>Édition du site</h2>
                <p>Le site <strong>Mes Orchidées</strong> est un projet fil rouge réalisé dans le cadre de la formation DWWM (Développeur Web et Web Mobile).</p>
                <p>Responsable de la publication : Jérémy Ferrier.</p>
            </article>

            <article class="legal-section">
                <h2>Hébergement</h2>
                <p>Hébergement en phase de test. L'application est prévue pour être déployée sur un VPS ou une solution cloud telle que Supabase / Vercel / OVH.</p>
            </article>

            <article class="legal-section">
                <h2>Crédits</h2>
                <p>Les photographies et les descriptions botaniques sont utilisées à des fins pédagogiques et illustratives.</p>
            </article>
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

    <script type="module" src="./assets/js/app.js"></script>

</body>
</html>

