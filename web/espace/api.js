/* Client HTTP léger vers /api/v1 : jeton Sanctum, redirection 401 et suivi de sync. */

const API = (typeof window.KORRICHESS_API === 'string' && window.KORRICHESS_API) || '/api/v1';
const CLE_JETON = 'korrichess_jeton';

export function jeton() {
    try {
        return localStorage.getItem(CLE_JETON);
    } catch {
        return null;
    }
}

export function poserJeton(t) {
    try {
        localStorage.setItem(CLE_JETON, t);
    } catch { /* stockage indisponible */ }
}

export function purgerJeton() {
    try {
        localStorage.removeItem(CLE_JETON);
    } catch { /* idem */ }
}

export function api(chemin, options = {}) {
    const entetes = { Accept: 'application/json' };
    if (options.body !== undefined) {
        entetes['Content-Type'] = 'application/json';
    }
    if (jeton() !== null) {
        entetes.Authorization = 'Bearer ' + jeton();
    }

    return fetch(API + chemin, {
        method: options.methode || 'GET',
        headers: entetes,
        body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    }).then((reponse) => reponse.json().catch(() => ({})).then((corps) => {
        if (reponse.status === 401 && chemin.indexOf('/auth/') !== 0) {
            purgerJeton();
            location.hash = '#/connexion';
        }
        return { status: reponse.status, body: corps };
    }));
}

/**
 * Interroge GET /sync/statut toutes les 5 s jusqu'à ce que le curseur du
 * compte avance, ou que le plafond d'essais soit atteint.
 */
export function suivreSync({ plateforme, pseudo, depart, maxEssais, onTermine, onTimeout, essai = 0 }) {
    if (essai >= maxEssais) {
        onTimeout();
        return;
    }

    api('/sync/statut').then((res) => {
        if (res.status !== 200) {
            setTimeout(() => suivreSync({ plateforme, pseudo, depart, maxEssais, onTermine, onTimeout, essai: essai + 1 }), 5000);
            return;
        }
        const ligne = (res.body.synchronisations || []).find(
            (l) => l.platform === plateforme && l.username === pseudo,
        );
        const terminee = ligne && ligne.last_sync_at && Date.parse(ligne.last_sync_at) > depart - 60000;
        if (terminee) {
            onTermine();
        } else {
            setTimeout(() => suivreSync({ plateforme, pseudo, depart, maxEssais, onTermine, onTimeout, essai: essai + 1 }), 5000);
        }
    });
}
