/**
 * @file core/modal.js
 * @description Je gere les modales de facon accessible : j'ouvre, je ferme, je bloque le scroll et je piege le focus.
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : core/modal.js — Gestionnaire centralisé de fenêtres modales
// ===========================================================================
// J'ai centralisé toute la logique des modales ici pour garantir une
// accessibilité irréprochable (RGAA / WAI-ARIA) et éviter de dupliquer le même
// code dans chaque feature. Pourquoi un module dédié ? Parce que l'ouverture
// d'une modale implique 4 responsabilités critiques : l'ARIA, le scroll, le
// focus et le clavier, que je ne veux gérer qu'une seule fois.

// J'importe ma fonction de piégeage de focus. Elle m'est indispensable pour
// respecter le critère RGAA qui impose de ne pas laisser le focus s'échapper
// de la modale.
import { trapFocus } from './focus.js';

// Je garde en mémoire l'état global de la modale : une seule modale peut être
// ouverte à la fois, c'est un choix d'UX pour éviter la confusion.
let activeModal = null;
let lastFocusedElement = null;
let previousBodyOverflow = '';

/**
 * Je gere les touches Escape et Tab quand une modale est ouverte.
 * @param {Object} event - L'evenement clavier (keydown).
 * @returns {void}
 */
function handleKeyDown(event) {
    if (!activeModal) {
        return;
    }


    if (event.key === 'Escape') {
        close(activeModal);
    }


    if (event.key === 'Tab') {
        trapFocus(activeModal, event);
    }
}

/**
 * J'ouvre une modale et je mets en place l'accessibilite et le blocage du scroll.
 * @param {Object} modalElement - L'element HTML de la modale a ouvrir.
 * @param {Object|null} [triggerElement=null] - Le bouton qui a declenche l'ouverture (pour rendre le focus apres).
 * @returns {void}
 * @example
 * open(document.getElementById("orchid-modal"), monBouton);
 */
export function open(modalElement, triggerElement = null) {
    if (!modalElement) {
        return;
    }


    if (activeModal && activeModal !== modalElement) {
        close(activeModal);
    }

    activeModal = modalElement;

    lastFocusedElement = triggerElement || document.activeElement;
    previousBodyOverflow = document.body.style.overflow;

    modalElement.classList.add('active');

    modalElement.setAttribute('aria-hidden', 'false');

    document.body.style.overflow = 'hidden';


    document.removeEventListener('keydown', handleKeyDown);
    document.addEventListener('keydown', handleKeyDown);


    window.setTimeout(() => {
        const closeButton = modalElement.querySelector('.modal-close, .close-modal');

        if (closeButton) {
            closeButton.focus();
        } else if (typeof modalElement.focus === 'function') {
            modalElement.focus();
        }
    }, 50);
}

/**
 * Je ferme la modale et je restaure le scroll et le focus d'avant.
 * @param {Object} modalElement - L'element de la modale a fermer.
 * @returns {void}
 * @example
 * close(document.getElementById("orchid-modal"));
 */
export function close(modalElement) {
    if (!modalElement || modalElement !== activeModal) {
        return;
    }

    modalElement.classList.remove('active');
    // Je repasse aria-hidden à "true" pour que les lecteurs d'écran ignorent à
    // nouveau ce contenu caché. C'est le pendant indispensable de l'ouverture.
    modalElement.setAttribute('aria-hidden', 'true');
    // Je restaure la valeur d'overflow que j'avais sauvegardée pour rendre
    // le scroll à la page. Sans cela, la page resterait bloquée après fermeture.
    document.body.style.overflow = previousBodyOverflow;

    document.removeEventListener('keydown', handleKeyDown);
    activeModal = null;

    // Je restitue le focus sur l'élément qui avait ouvert la modale.
    // C'est crucial pour la continuité de navigation au clavier : l'utilisateur
    // retrouve exactement sa position, il n'est pas renvoyé en haut de page.
    // preventScroll : mesure navigateur (phase D) — sans cette option, focus()
    // FAIT défile la page jusqu'au déclencheur et la position de lecture était
    // perdue à chaque fermeture, ce que le commentaire ci-dessus refuse.
    // Le contrat de restitution du focus est inchangé, seul le saut de scroll
    // est supprimé (toutes les modales du site en bénéficient).
    if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
        try {
            lastFocusedElement.focus({ preventScroll: true });
        } catch (erreur) {
            // Navigateur sans support de l'option : comportement d'origine.
            lastFocusedElement.focus();
        }
    }

    lastFocusedElement = null;
}

// J'expose aussi une API globale window.ModalManager pour la compatibilité avec
// d'anciens fichiers qui l'appelaient encore directement. C'est une transition douce.
window.ModalManager = { open, close };
