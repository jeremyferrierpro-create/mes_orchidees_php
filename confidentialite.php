<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once 'includes/head.php'; ?>
    <title>Politique de confidentialité - Mes Orchidées</title>
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
        <section class="legal-page" aria-label="Politique de confidentialité">
            <h1 class="page-title">POLITIQUE DE CONFIDENTIALITÉ</h1>

            <article class="legal-section">
                <h2>Données collectées</h2>
                <p>Lors de l'inscription, nous collectons votre nom, prénom, adresse email et un mot de passe haché. Ces données sont nécessaires à la gestion de votre collection personnelle.</p>
            </article>

            <article class="legal-section">
                <h2>Protection des données</h2>
                <p>Conformément au RGPD, vos données ne sont pas revendues. Le mot de passe est haché avec Bcrypt et les échanges seront chiffrés via HTTPS à terme.</p>
            </article>

            <article class="legal-section">
                <h2>Droit à l'effacement</h2>
                <p>Vous pouvez demander la suppression de votre compte et de l'ensemble des données qui y sont associées (collection, soins, sites de culture, rappels).</p>
            </article>

            <article class="legal-section">
                <h2>Cookies et localStorage</h2>
                <p>Ce site utilise le <strong>localStorage</strong> du navigateur pour mémoriser temporairement votre collection locale. Aucun cookie publicitaire n'est déposé.</p>
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

