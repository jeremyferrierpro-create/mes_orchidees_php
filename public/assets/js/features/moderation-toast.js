/**
 * @file features/moderation-toast.js
 * @description Je pose sur l'écran d'un admin/modérateur un toast persistant pour
 *              chaque « nouvelle proposition d'orchidée » non lue : il est alerté
 *              qu'une plante hors encyclopédie attend sa vérification. Le toast
 *              reste affiché jusqu'à ce qu'il consulte la file (« Modérer ») ou
 *              choisisse explicitement de ne plus l'afficher (« Ne plus montrer ») ;
 *              le fermer (×) ne change AUCUN état côté serveur.
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : features/moderation-toast.js — Toast temps réel du modérateur
// ===========================================================================
// Je reproduis à l'identique la discipline du toast des rappels (features/
// rappels-toast.js), car le contrat d'infrastructure est le même :
//  1. core/notifications.js : conteneur #toast-container rendu par le footer ;
//     les classes .toast / .toast-rappel / .toast-close sont déjà stylées. Je ne
//     touche donc à aucun module partagé et je n'impose aucune nouvelle feuille de
//     style : le visuel « verre + liseré doré » est exactement celui des rappels.
//  2. GET /api/notifications/propositions : réservé admin/modérateur (GRANT_MODO),
//     lit le destinataire UNIQUEMENT en session (RG1) et ne renvoie QUE les
//     notifications 'nouvelle_proposition' non lues. Réponse :
//     { status, count, moderationUrl, readUrlBase, csrf, propositions[] }.
//  3. POST /notification/{id}/read : CSRF + isolation ; négocié en JSON par
//     l'en-tête Accept. Un échec (400/404/réseau) ne doit JAMAIS faire croire
//     que la notification est lue : je ne retire le toast qu'après un 200 ok.
//
// Repli progressif : le footer n'amorce (#propositions-toast-bootstrap) que pour
// un admin/modérateur connecté. Absent → aucun appel réseau, aucun toast (un
// utilisateur ordinaire ou un visiteur anonyme ne sollicitent pas la route 403).

import { showToast } from '../core/notifications.js';

// Trois toasts simultanés maximum : au-delà l'écran serait saturé ; le lien
// « Modérer » ouvre la file complète où tout est traitable.
const MAX_TOASTS = 3;

// Durée de l'animation de sortie (identique à core/notifications.js : 400 ms).
const LEAVE_MS = 400;

// Un seul appel réseau par chargement de page (pas de rafraîchissement périodique
// inutile — même choix volontaire que rappels-toast.js).
let dejaLance = false;

/**
 * Je retire un élément du DOM avec l'animation de sortie du design system.
 * @param {Object} toastElement - L'élément à retirer.
 * @returns {void}
 */
function removeToastElement(toastElement) {
    if (toastElement.classList.contains('toast-leaving')) return;
    toastElement.classList.add('toast-leaving');
    setTimeout(() => {
        if (toastElement.parentNode) {
            toastElement.parentNode.removeChild(toastElement);
        }
    }, LEAVE_MS);
}

/**
 * Je fabrique le bouton de fermeture purement visuelle (×) : fermer ≠ marquer lu.
 * @param {Object} toastElement - Le toast à fermer.
 * @returns {Object} Le bouton prêt à insérer.
 */
function makeCloseButton(toastElement) {
    const closeBtn = document.createElement('button');
    closeBtn.className = 'toast-close';
    closeBtn.setAttribute('aria-label', 'Fermer la notification (sans la marquer comme lue)');

    const closeIcon = document.createElement('i');
    closeIcon.className = 'fa-solid fa-times';
    closeIcon.setAttribute('aria-hidden', 'true');
    closeBtn.appendChild(closeIcon);

    closeBtn.addEventListener('click', () => removeToastElement(toastElement));
    return closeBtn;
}

/**
 * Je construis un span de métadonnée — texte simple, XSS-safe (jamais innerHTML).
 * @param {string} className - Classe CSS de la métadonnée.
 * @param {string} text - Contenu textuel.
 * @returns {Object} L'élément construit.
 */
function makeMeta(className, text) {
    const el = document.createElement('span');
    el.className = className;
    el.textContent = text;
    return el;
}

/**
 * Je construis le toast persistant d'une nouvelle proposition à modérer.
 * @param {Object} proposition - Ligne du flux (Notification::toArray).
 * @param {Object} flux        - { moderationUrl, readUrlBase, csrf } renvoyés par le serveur.
 * @returns {Object} L'élément toast inséré dans #toast-container.
 */
function buildPropositionToast(proposition, flux) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    // Je réutilise la charte visuelle du rappel (colonne + liseré doré) : pas de nouvelle CSS.
    toast.className = 'toast toast-rappel toast-proposition';
    toast.setAttribute('aria-label', 'Nouvelle proposition d\'orchidée à modérer');

    const icon = document.createElement('i');
    icon.className = 'fa-solid fa-inbox toast-rappel__icon';
    icon.setAttribute('aria-hidden', 'true');
    toast.appendChild(icon);

    const body = document.createElement('div');
    body.className = 'toast-rappel__body';

    const titre = document.createElement('strong');
    titre.className = 'toast-rappel__plant';
    titre.textContent = 'Nouvelle proposition à modérer';
    body.appendChild(titre);

    if (proposition.dateFr) {
        body.appendChild(makeMeta('toast-rappel__date', proposition.dateFr));
    }
    body.appendChild(makeMeta('toast-rappel__message', proposition.message));

    // « Modérer » : navigation GET sans effet de bord vers la file de modération.
    const link = document.createElement('a');
    link.className = 'toast-rappel__action';
    link.href = flux.moderationUrl;
    link.textContent = 'Vérifier et modérer';
    body.appendChild(link);

    toast.appendChild(body);

    // « Ne plus montrer » : la SEULE action qui change l'état (marque lu).
    const readBtn = document.createElement('button');
    readBtn.className = 'toast-rappel__read';
    readBtn.type = 'button';
    readBtn.textContent = 'Ne plus montrer';
    readBtn.addEventListener('click', async () => {
        readBtn.disabled = true; // pas de double-frappe pendant l'appel
        try {
            const res = await fetch(flux.readUrlBase + '/' + encodeURIComponent(proposition.id) + '/read', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'csrf_token=' + encodeURIComponent(flux.csrf)
            });
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            const json = await res.json();
            if (json.status !== 'ok') {
                throw new Error(json.status || 'erreur');
            }
            // Succès prouvé : seulement maintenant je retire le toast.
            removeToastElement(toast);
            showToast('Alerte marquée comme lue.', 'info');
        } catch (error) {
            // Échec réseau/HTTP : l'alerte RESTE non lue et le toast persiste.
            console.warn('Marquage « lu » de la proposition impossible :', error);
            showToast('Marquage impossible. L\'alerte reste non lue.', 'error');
            readBtn.disabled = false;
        }
    });
    toast.appendChild(readBtn);

    // « × » distinct de la lecture : fermeture visuelle seule, état serveur inchangé.
    toast.appendChild(makeCloseButton(toast));

    container.appendChild(toast);
    return toast;
}

/**
 * J'initialise le toast temps réel des nouvelles propositions.
 * Appelé sur toutes les pages par app.js ; je ne fais de réseau que si le footer a
 * rendu l'amorce réservée aux modérateurs (utilisateur admin/modérateur connecté).
 * @returns {Promise<void>}
 */
export async function initModerationToast() {
    if (dejaLance) return;
    const bootstrap = document.getElementById('propositions-toast-bootstrap');
    if (!bootstrap) return; // non modérateur / anonyme : aucune requête, aucun toast
    dejaLance = true;

    const endpoint = bootstrap.dataset.endpoint;
    if (!endpoint) return;

    let flux;
    try {
        const res = await fetch(endpoint, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        // 403 (droit manquant), 500 ou réseau coupé : j'abandonne SILENCIEUSEMENT.
        // Une alerte non lue n'est jamais détruite ni marquée lue ici.
        if (!res.ok) return;
        flux = await res.json();
    } catch (error) {
        console.warn('Chargement des nouvelles propositions impossible :', error);
        return;
    }

    if (!flux || flux.status !== 'ok' || !Array.isArray(flux.propositions)) return;

    flux.propositions.slice(0, MAX_TOASTS).forEach((proposition) => {
        buildPropositionToast(proposition, {
            moderationUrl: flux.moderationUrl,
            readUrlBase: flux.readUrlBase,
            csrf: flux.csrf
        });
    });
}
