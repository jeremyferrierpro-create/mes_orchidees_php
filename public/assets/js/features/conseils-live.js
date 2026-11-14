/**
 * @file features/conseils-live.js
 * @description Sur la page Conseils (serveur), je révèle les fiches "par plante" au fur
 *              et à mesure de la saisie, sans recharger la page.
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : features/conseils-live.js — Filtrage instantané des conseils par plante
// ===========================================================================
// La vue serveur rend TOUTES les fiches par plante (type 'species') dans le DOM, mais
// masquées (classe .is-hidden). Le visiteur tape dans la barre de recherche : je filtre
// ces cartes côté client, instantanément, et j'affiche seulement celles qui correspondent.
//
// C'est du "repli progressif" (progressive enhancement) : sans JavaScript, le formulaire
// GET ?search=... continue de fonctionner côté serveur. Ce module vient uniquement
// enrichir l'expérience, il n'en est jamais responsable.
//
// NB : ce module ne remplace PAS features/conseils.js (module historique du site statique,
// qui se met tout seul en no-op sur cette vue car il n'y trouve pas sa modale).

/**
 * J'initialise le filtrage live de la section "Conseils par plante".
 * @returns {void}
 * @example
 * initConseilsLive();
 */
export function initConseilsLive() {
    const input     = document.getElementById('conseil-search');
    const grid      = document.getElementById('conseils-plantes-grid');
    const section   = document.getElementById('conseils-plantes');
    const hint      = section ? section.querySelector('.conseils-plantes-hint') : null;
    const emptyNote = document.getElementById('conseils-plantes-empty');

    // Garde-fou : je ne suis pas sur la page Conseils MVC → je sors sans erreur.
    if (!input || !grid) {
        return;
    }

    // Je rassemble une seule fois les cartes "par plante" et leur texte normalisé.
    // Pourquoi normaliser en amont ? Pour ne pas refaire le travail à chaque frappe.
    const cards = Array.from(grid.querySelectorAll('.conseil-plante-card')).map((card) => ({
        el: card,
        // data-search porte "nom catégorie espèce" en minuscules sans accents.
        haystack: normalize(card.dataset.search || card.dataset.conseilName || ''),
    }));

    if (cards.length === 0) {
        return;
    }

    // Je ne déclenche le filtrage qu'à partir de 3 caractères : en dessous, le terme
    // est trop court pour être significatif (une lettre collimerait tout l'alphabet).
    const SEUIL = 3;

    /**
     * Je rends un texte comparable : minuscules + accents retirés (é -> e).
     * @param {string} value - Le texte brut.
     * @returns {string} Le texte normalisé.
     */
    function normalize(value) {
        return String(value)
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    /**
     * J'affiche ou masque les fiches par plante selon le terme saisi.
     * @param {string} raw - La valeur brute du champ de recherche.
     * @returns {void}
     */
    function applyFilter(raw) {
        const term = normalize(raw);

        // Repli à l'état initial : champ vide OU terme trop court (< 3 caractères).
        // Les fiches restent masquées et l'invite réapparaît ; aucun faux « 0 résultat ».
        if (term.length < SEUIL) {
            cards.forEach((c) => c.el.classList.add('is-hidden'));
            if (hint) hint.hidden = false;
            if (emptyNote) emptyNote.hidden = true;
            return;
        }

        // À partir de 3 caractères, l'invite n'a plus lieu d'être affichée.
        if (hint) hint.hidden = true;

        // Un terme est considéré trouvé si TOUS ses mots apparaissent dans la carte.
        const tokens = term.split(/\s+/).filter(Boolean);
        let visibles = 0;

        cards.forEach((c) => {
            const match = tokens.every((token) => c.haystack.includes(token));
            c.el.classList.toggle('is-hidden', !match);
            if (match) {
                visibles += 1;
            }
        });

        // Aucun résultat : je préviens le visiteur (aria-live côté vue).
        if (emptyNote) emptyNote.hidden = visibles > 0;
    }

    // J'écoute la frappe (et la croix "effacer" des champs <input type="search">).
    input.addEventListener('input', (event) => applyFilter(event.target.value));

    // Je synchronise l'état au chargement : si l'URL arrive avec ?search=... (>= seuil),
    // je filtre immédiatement à partir du jeu complet (et pas seulement du sous-ensemble serveur).
    if (normalize(input.value).length >= SEUIL) {
        applyFilter(input.value);
    }
}
