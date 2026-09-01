/**
 * @file services/orchid-service.js
 * @description Je gere les orchidees via l'API PHP.
 * @author Jeremy Ferrier
 * @version 2.0 (Migration PHP)
 */

export async function getAllOrchids() {
    const res = await fetch('api/orchids/index.php');
    if (!res.ok) throw new Error('Erreur de chargement du catalogue');
    return res.json();
}

export async function getOrchidById(id) {
    const all = await getAllOrchids();
    return all.find(o => o.id === id) || null;
}

export async function searchOrchids(query) {
    if (!query || query.length < 2) return await getAllOrchids();
    const res = await fetch('api/orchids/search.php?q=' + encodeURIComponent(query));
    if (!res.ok) throw new Error('Erreur de recherche');
    return res.json();
}


export async function deleteOrchid(id) { console.warn('deleteOrchid not implemented'); }

