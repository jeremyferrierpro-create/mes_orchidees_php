<?php
/**
 * Composant : en-tête de page (repris de includes/header.php).
 *
 * @package App\Templates\Components
 */
?>
    <header id="main-header">
        <img src="<?= e(asset('assets/images/site/logotransparent.webp')) ?>" alt="Logo de Mes Orchidées" width="50" height="50">
        <p class="header-tagline">Encyclopédie &amp; gestion de collections</p>
        <?php if (is_logged_in()): ?>
            <?php
            $currentUser = current_user();
            // Libellés de rôle lisibles pour l'affichage (valeur brute → texte jury).
            $roleLabels = ['admin' => 'Admin', 'user' => 'Collectionneur', 'moderateur' => 'Modérateur'];
            $roleRaw    = (string) ($currentUser['role'] ?? '');
            $roleLabel  = $roleLabels[$roleRaw] ?? ucfirst($roleRaw);
            ?>
            <p class="header-user-greeting" role="status">
                Bonjour <?= e($currentUser['prenom'] ?? '') ?>, vous êtes connecté comme <?= e($roleLabel) ?>.
            </p>
        <?php endif; ?>
        <button type="button" class="menu-toggle" id="menu-toggle-btn" aria-label="Ouvrir ou fermer le menu"
            aria-controls="main-sidebar" aria-expanded="false">
            <span class="hamburger-bar"></span>
        </button>
    </header>
