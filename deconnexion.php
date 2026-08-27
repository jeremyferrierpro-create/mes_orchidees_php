<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta name="theme-color" content="#0e2018">
    <meta name="description" content="D�connexion de Mes Orchid�es.">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="./assets/images/site/logotransparent.png">
    <link rel="icon" type="image/png" href="./assets/images/site/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600&family=Inter:wght@300;400;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/style.css">
    <title>D�connexion - Mes Orchid�es</title>
</head>

<body>
    <a class="skip-link" href="#main-content">Aller au contenu</a>

    <nav id="main-sidebar" class="sidebar-container" aria-label="Menu principal" role="navigation">
        <div class="sidebar-brand">
            <img src="./assets/images/site/logotransparent.png" alt="Logo de Mes Orchid�es">
            <h2>Mes Orchid�es</h2>
        </div>
        <div class="sidebar-menu">
            <ul class="menu-group-main">
                <li><a href="index.php"><i class="fa-solid fa-home" aria-hidden="true"></i> Accueil</a></li>
                <li><a href="encyclopedie.php"><i class="fa-solid fa-book" aria-hidden="true"></i> Encyclop�die</a></li>
                <li><a href="macollection.php"><i class="fa-solid fa-seedling" aria-hidden="true"></i> Ma collection</a>
                </li>
                <li><a href="conseils.php"><i class="fa-solid fa-heart" aria-hidden="true"></i> Conseils</a></li>
            </ul>
            <ul class="menu-group-user">
                <li><a href="administration.php"><i class="fa-solid fa-gear" aria-hidden="true"></i> Administration</a>
                </li>
                <li><a href="authentification.php"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                        Authentification</a></li>
                <li><a href="deconnexion.php" aria-current="page"><i class="fa-solid fa-right-from-bracket"
                            aria-hidden="true"></i> D�connexion</a></li>
            </ul>
        </div>
    </nav>

    <header id="main-header" role="banner">
        <img src="./assets/images/site/logotransparent.png" alt="Logo de Mes Orchid�es">
        <p class="header-tagline">Encyclop�die &amp; gestion de collections</p>
        <button type="button" class="menu-toggle" id="menu-toggle-btn" aria-label="Ouvrir ou fermer le menu"
            aria-controls="main-sidebar" aria-expanded="false">
            <span class="hamburger-bar"></span>
        </button>
    </header>

    <main class="main-content" id="main-content">
        <section class="logout-page" aria-label="D�connexion">
            <h1 class="page-title">DÉCONNEXION</h1>
            <p class="page-intro">Vous avez �t� d�connect� de l'application.</p>
            <p class="logout-note">Vos donn�es locales restent disponibles sur cet appareil. Pour les supprimer
                d�finitivement, contactez l'administrateur.</p>
            <a href="index.php" class="btn btn-primary">Retour à l'accueil</a>
        </section>
    </main>

    <footer id="main-footer" role="contentinfo">
        <div class="footer-links-group"><a href="mentions.php" class="footer-link">Mentions l�gales</a><a
                href="accessibilite.php" class="footer-link">Accessibilit�</a></div>
        <div class="footer-center">
            <img src="./assets/images/site/logotransparent.png" alt="Logo de Mes Orchid�es">
            <p>&copy; Mes Orchid�es - Projet Fil Rouge DWWM - Jeremy Ferrier - 2026/2027</p>
        </div>
        <a href="confidentialite.php" class="footer-link">Politiques de confidentialit�</a>
    </footer>

    <script type="module" src="./assets/js/app.js"></script>
    <script type="module">
        // D�connexion propre : on vide la session locale, puis on revient � l'accueil
        import('./assets/js/services/auth-service.js').then(mod => {
            mod.logout();
            // Petite pause pour que la notification de succ�s soit visible, puis retour � l'accueil
            setTimeout(() => { window.location.href = 'index.php'; }, 1200);
        });
    </script>

</body>

</html>