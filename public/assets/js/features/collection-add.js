/**
 * @file features/collection-add.js
 * @description Sur « Ma Collection », j'enrichis la modale d'ajout d'orchidée :
 *              autocomplétion des espèces publiées (à partir de 3 caractères),
 *              auto-remplissage des champs botaniques à la sélection, et bascule
 *              explicite entre « lier une espèce existante » et « proposer une
 *              nouvelle espèce ». Je réutilise ModalManager (core/modal.js) sans le
 *              réécrire : Échap, piège de focus, verrou du scroll et retour du focus.
 * @author Jeremy Ferrier
 * @version 1.0
 */

// ===========================================================================
// FICHIER : features/collection-add.js — Modale « Ajouter une orchidée »
// ===========================================================================
// Repli progressif (progressive enhancement) : sans JavaScript, le champ <input list>
// (datalist natif) suggère déjà les noms, et le serveur relie une espèce publiée dont
// le nom correspond exactement, sinon crée une proposition. Ce module vient uniquement
// rendre l'expérience claire : recherche instantanée, auto-remplissage, mode explicite.
//
// Aucune donnée n'est reconstruite en HTML dynamique : les libellés des suggestions
// sont injectés via textContent (anti-XSS), et le jeu d'espèces arrive par un
// <script type="application/json"> (non exécuté — conforme à CSP script-src 'self').

import { open as ouvrirModale, close as fermerModale } from '../core/modal.js';

// Nombre minimal de caractères avant d'afficher des suggestions (évite le bruit).
const SEUIL = 3;
// Nombre maximal de suggestions affichées (le reste serait illisible).
const MAX_SUGGESTIONS = 8;

/**
 * Normalise un texte pour une comparaison tolérante : minuscules + accents retirés.
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
 * J'initialise la modale d'ajout d'orchidée. No-op si son squelette est absent
 * de la page courante (le module tourne aussi bien sur le dashboard que sur toute vue
 * qui inclurait la modale).
 * @returns {void}
 */
export function initCollectionAdd() {
    const modale = document.getElementById('collection-add');
    if (!modale) {
        return;
    }

    // Garde anti-double-lier : app.js peut relancer l'initialiseur sans rechargement.
    if (modale.dataset.liee === '1') {
        return;
    }
    modale.dataset.liee = '1';

    const nameInput   = modale.querySelector('#name');
    const suggest     = modale.querySelector('#orchid-suggest');
    const orchidField = modale.querySelector('#add_orchid_id');
    const status      = modale.querySelector('#add-mode-help');
    const proposalSet = modale.querySelector('[data-proposal-fields]');

    if (!nameInput || !suggest || !orchidField) {
        return;
    }

    // --- Jeu de données : espèces publiées (id, name, vernacular, genre, family, behavior, origin). ---
    let published = [];
    const dataNode = document.getElementById('published-orchid-data');
    if (dataNode) {
        try {
            const parsed = JSON.parse(dataNode.textContent || '[]');
            if (Array.isArray(parsed)) {
                published = parsed;
            }
        } catch (error) {
            // JSON illisible : je reste en saisie libre, aucune suggestion (pas de crash).
            published = [];
        }
    }
    // Index normalisé calculé une seule fois pour la recherche instantanée.
    const index = published.map((o) => ({
        o,
        hay: normalize([o.name, o.vernacular, o.genre].filter(Boolean).join(' ')),
    }));

    /**
     * Je remplis un champ texte s'il est vide ou si l'on lie une espèce (auto-remplissage).
     * S'applique aussi aux champs botaniques (genre / famille / comportement) : ce sont
     * désormais des <input list> en saisie libre, donc la valeur d'une espèce publiée se
     * reporte telle quelle — aucun risque de rejet silencieux comme avec un <select> fermé.
     * @param {string} selector - Sélecteur CSS du champ.
     * @param {string|null} value - Texte à écrire.
     * @returns {void}
     */
    function setText(selector, value) {
        const el = modale.querySelector(selector);
        if (el) {
            el.value = value == null ? '' : String(value);
        }
    }

    /**
     * Je passe le formulaire en mode « lié » : espèce de l'encyclopédie rattachée.
     * @param {Object} orchid - L'espèce publiée sélectionnée.
     * @returns {void}
     */
    function linkSpecies(orchid) {
        orchidField.value = orchid.id;
        modale.classList.add('is-linked');
        setText('#name', orchid.name);
        setText('#vernacular', orchid.vernacular);
        setText('#origin', orchid.origin);
        setText('#genre', orchid.genre);
        setText('#family', orchid.family);
        setText('#behavior', orchid.behavior);
        if (status) {
            status.textContent = 'Espèce de l\'encyclopédie reliée : « ' + orchid.name
                + ' ». Caractéristiques auto-remplies — vous pouvez compléter l\'emplacement et la photo.';
            status.classList.add('add-mode--linked');
        }
    }

    /**
     * Je repasse en mode « proposition » : aucune espèce connue, la botanique est requise.
     * @returns {void}
     */
    function proposalMode() {
        orchidField.value = '';
        modale.classList.remove('is-linked');
        if (status) {
            status.textContent = 'Nouvelle espèce : complétez la botanique (genre, famille, comportement) '
                + 'pour proposer cette orchidée à la modération. Elle est ajoutée immédiatement à votre collection.';
            status.classList.remove('add-mode--linked');
        }
    }

    // --- Ouverture : je détourne le lien « + » (délégation sur document). ---
    document.addEventListener('click', function (event) {
        const declencheur = event.target.closest('[data-collection-add-trigger]');
        if (!declencheur) {
            return;
        }
        // Clic modifié (Ctrl/Maj/clic milieu) = navigation volontaire : je ne l'intercepte pas.
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }
        event.preventDefault();
        ouvrirModale(modale, declencheur);
    });

    // --- Fermeture par les boutons × / Annuler. ---
    modale.querySelectorAll('[data-collection-add-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fermerModale(modale);
        });
    });

    /**
     * J'efface les suggestions et leur sélection clavier.
     * @returns {void}
     */
    function fermerSuggestions() {
        suggest.hidden = true;
        suggest.innerHTML = '';
        suggest.removeAttribute('data-active');
    }

    /**
     * Je dessine une ligne de suggestion (textContent uniquement → anti-XSS).
     * @param {Object} orchid - L'espèce publiée.
     * @returns {Object} L'élément <li> prêt à insérer.
     */
    function creerSuggestion(orchid) {
        const li = document.createElement('li');
        li.setAttribute('role', 'option');
        li.tabIndex = -1;
        li.dataset.id = orchid.id;

        const nom = document.createElement('span');
        nom.className = 'suggest-name';
        nom.textContent = orchid.name;

        li.appendChild(nom);

        const sous = orchid.vernacular || orchid.genre;
        if (sous) {
            const sub = document.createElement('span');
            sub.className = 'suggest-sub';
            sub.textContent = sous;
            li.appendChild(sub);
        }
        return li;
    }

    /**
     * Je recherche une espèce par son id dans le jeu publié.
     * @param {string} id - Identifiant (slug) de l'espèce.
     * @returns {Object|null} L'espèce ou null.
     */
    function parId(id) {
        return published.find((o) => o.id === id) || null;
    }

    // --- Frappe : je suggère les espèces publiées dès 3 caractères. ---
    nameInput.addEventListener('input', function () {
        const term = normalize(nameInput.value);

        // L'utilisateur modifie le nom : on n'est plus sur une sélection fiable → proposition.
        proposalMode();

        if (term.length < SEUIL) {
            fermerSuggestions();
            return;
        }

        const tokens = term.split(/\s+/).filter(Boolean);
        const matches = index
            .filter((entry) => tokens.every((t) => entry.hay.includes(t)))
            .slice(0, MAX_SUGGESTIONS);

        suggest.innerHTML = '';
        if (matches.length === 0) {
            fermerSuggestions();
            return;
        }
        matches.forEach((entry) => suggest.appendChild(creerSuggestion(entry.o)));
        suggest.hidden = false;
    });

    // --- Sélection d'une suggestion (clic). ---
    suggest.addEventListener('mousedown', function (event) {
        const li = event.target.closest('li[data-id]');
        if (!li) {
            return;
        }
        // mousedown avant le blur du champ : je laisse faire puis j'applique.
        event.preventDefault();
        const orchid = parId(li.dataset.id);
        if (orchid) {
            linkSpecies(orchid);
        }
        fermerSuggestions();
    });

    // --- Clavier dans le champ : navigation ↑/↓, Entrée = sélection, Échap = fermer le menu. ---
    nameInput.addEventListener('keydown', function (event) {
        const options = Array.from(suggest.querySelectorAll('li[data-id]'));
        if (options.length === 0) {
            return;
        }
        const current = suggest.getAttribute('data-active');
        let pos = current ? options.findIndex((el) => el.dataset.id === current) : -1;

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            pos = event.key === 'ArrowDown'
                ? (pos + 1) % options.length
                : (pos - 1 + options.length) % options.length;
            options.forEach((el, i) => el.classList.toggle('is-active', i === pos));
            suggest.setAttribute('data-active', options[pos].dataset.id);
        } else if (event.key === 'Enter') {
            // Entrée sur une suggestion : je ne soumets pas le formulaire, je sélectionne.
            if (pos >= 0) {
                event.preventDefault();
                const orchid = parId(options[pos].dataset.id);
                if (orchid) {
                    linkSpecies(orchid);
                }
                fermerSuggestions();
            }
        } else if (event.key === 'Escape') {
            // Échap ferme d'abord le menu de suggestions (et non la modale).
            fermerSuggestions();
            event.stopPropagation();
        }
    });

    // Le champ perd le focus : je referme le menu (la sélection a déjà été appliquée).
    nameInput.addEventListener('blur', function () {
        window.setTimeout(fermerSuggestions, 120);
    });

    // --- État initial : nom saisi (repli serveur) correspondant exactement à une espèce
    // publiée → on la lie d'emblée pour cohérence du mode affiché. ---
    if (nameInput.value.trim() !== '') {
        const exact = published.find((o) => normalize(o.name) === normalize(nameInput.value));
        if (exact) {
            linkSpecies(exact);
        } else {
            proposalMode();
        }
    } else if (proposalSet) {
        // Rien de saisi : on reste neutre, la botanique est visible pour permettre la proposition.
        proposalMode();
    }
}
