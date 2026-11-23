/* Utilitaires partagés des vues de l'espace web. */

export function vue() {
    return document.getElementById('vue');
}

export function echapperHtml(valeur) {
    return String(valeur === null || valeur === undefined ? '' : valeur)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

export function courteDate(valeur) {
    if (!valeur) {
        return '—';
    }
    const date = new Date(String(valeur).replace(/\./g, '-').replace('T', ' '));
    return Number.isNaN(date.getTime()) ? String(valeur).slice(0, 10) : date.toLocaleDateString('fr-FR');
}

export function libelleResultat(partie) {
    if (!partie.result) {
        return '—';
    }
    if (partie.result.indexOf('1/2') === 0 || partie.result.indexOf('\u00BD') === 0) {
        return 'nulle';
    }
    const blancGagne = partie.result === '1-0';
    if (partie.color === 'white') {
        return blancGagne ? 'victoire' : 'défaite';
    }
    return blancGagne ? 'défaite' : 'victoire';
}

export function telecharger(nom, texte) {
    const blob = new Blob([texte], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const lien = document.createElement('a');
    lien.href = url;
    lien.download = nom;
    document.body.appendChild(lien);
    lien.click();
    lien.remove();
    URL.revokeObjectURL(url);
}
