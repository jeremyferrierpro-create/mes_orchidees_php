<?php
/**
 * Composant : pied de page + toasts + script applicatif (repris de includes/footer.php).
 *
 * Les liens pointent désormais vers les routes MVC. Le script app.js gère l'UI
 * (menu, focus, toasts) ; son peuplement de données sera réorienté vers les routes
 * API MVC (JSON) au fil des incréments.
 *
 * @package App\Templates\Components
 */
?>
    <footer id="main-footer">
        <div class="footer-links-group">
            <a href="<?= e(url('/mentions')) ?>" class="footer-link">Mentions légales</a>
            <a href="<?= e(url('/accessibilite')) ?>" class="footer-link">Accessibilité</a>
        </div>
        <div class="footer-center">
            <img src="<?= e(asset('assets/images/site/logotransparent.webp')) ?>" alt="Logo de Mes Orchidées" width="30" height="30">
            <p>&copy; Mes Orchidées - Projet Fil Rouge DWWM - Jeremy Ferrier - 2026/2027</p>
        </div>
        <a href="<?= e(url('/confidentialite')) ?>" class="footer-link">Politiques de confidentialité</a>
    </footer>

    <!-- Conteneur global pour les notifications "Toasts" -->
    <div id="toast-container" class="toast-container" aria-live="polite"></div>

    <?php if (is_logged_in()): ?>
    <!-- Phase C1 : amorce du toast persistant des rappels dus (jour J). Uniquement
         les URLs de routes réelles (fonction url() : préfixe d'installation respecté) ;
         aucun jeton CSRF exposé ici — il voyage dans la réponse JSON authentifiée
         de /api/rappels/dus. Absent = visiteur anonyme = module en no-op. -->
    <div id="rappels-toast-bootstrap" data-endpoint="<?= e(url('/api/rappels/dus')) ?>" hidden></div>
    <?php endif; ?>

    <?php if (has_role(['admin', 'moderateur'])): ?>
    <!-- Toast temps réel du modérateur : amorce du flux « nouvelles propositions à
         modérer » (GET /api/notifications/propositions, GRANT_MODO). Rendu UNIQUEMENT
         pour un admin/modérateur : un utilisateur ordinaire ne voit jamais cette amorce
         (et la route lui répondrait 403). Absent = module features/moderation-toast.js
         en no-op. Aucun CSRF ici : il voyage dans la réponse JSON authentifiée. -->
    <div id="propositions-toast-bootstrap" data-endpoint="<?= e(url('/api/notifications/propositions')) ?>" hidden></div>
    <?php endif; ?>

    <!-- Script principal de l'application -->
    <script type="module" src="<?= e(asset('assets/js/app.js')) ?>"></script>
