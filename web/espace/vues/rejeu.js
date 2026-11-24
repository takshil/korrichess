/* Vue rejeu (W4) avec analyse locale Stockfish dans le navigateur (ADR-006). */

import { api } from '../api.js';
import { creerEchiquierRejeu } from '../echiquier.js';
import { classerCoup, evalBlancs, formaterEval, MoteurLocal, perteCoup, pourcentageBlancs } from '../moteur.js';
import { construireRejeu, uciDepuisSan } from '../partie.js';
import { courteDate, echapperHtml, libelleResultat, vue } from '../ui.js';

const GLYPHES_AVIS = { meilleur: '!!', bon: '!', imprecision: '?', erreur: '??', gaffe: '???' };

let ecouteurClavier = null;
let plateauRejeu = null;
let moteur = null;

export function detacherClavierRejeu() {
    if (ecouteurClavier) {
        document.removeEventListener('keydown', ecouteurClavier);
        ecouteurClavier = null;
    }
    if (plateauRejeu) {
        plateauRejeu.detruire();
        plateauRejeu = null;
    }
    if (moteur) {
        moteur.detruire();
        moteur = null;
    }
}

export function vueRejeu(id) {
    vue().innerHTML = '<p>Chargement de la partie…</p>';
    api('/games/' + id).then(async (detail) => {
        if (detail.status !== 200) {
            vue().innerHTML = '<p>Partie introuvable. <a href="#/bibliotheque">Retour à la bibliothèque</a>.</p>';
            return;
        }
        let donneesRejeu = construireRejeu(detail.body);
        if (!donneesRejeu) {
            const rejeu = await api('/games/' + id + '/replay');
            if (rejeu.status === 200 && rejeu.body.positions) {
                donneesRejeu = {
                    positions: rejeu.body.positions,
                    moves_san: rejeu.body.moves_san || [],
                    moves_uci: uciDepuisSan(rejeu.body.moves_san || []),
                };
            }
        }
        const positions = donneesRejeu ? donneesRejeu.positions : null;
        const sans = donneesRejeu ? donneesRejeu.moves_san : [];
        const movesUci = donneesRejeu && Array.isArray(donneesRejeu.moves_uci) && donneesRejeu.moves_uci.length > 0
            ? donneesRejeu.moves_uci
            : uciDepuisSan(sans);
        let analyses = null;
        let inverser = detail.body.color === 'black';
        let courant = 0;

        let listeCoups = '';
        for (let i = 0; i < sans.length; i += 2) {
            const numero = (i / 2) + 1;
            listeCoups += '<li class="numero">' + numero + '.</li>' +
                '<li><button type="button" data-ply="' + i + '" class="coup">' + echapperHtml(sans[i]) + '</button></li>' +
                (sans[i + 1] !== undefined
                    ? '<li><button type="button" data-ply="' + (i + 1) + '" class="coup">' + echapperHtml(sans[i + 1]) + '</button></li>'
                    : '<li></li>');
        }

        vue().innerHTML =
            '<p><a href="#/bibliotheque">← Bibliothèque</a></p>' +
            '<h1>' + echapperHtml(detail.body.opponent || 'Partie') +
            (detail.body.color === 'white' ? ' — avec les blancs' : ' — avec les noirs') + '</h1>' +
            '<p>' + echapperHtml(courteDate(detail.body.played_at)) + ' · ' +
                echapperHtml(libelleResultat(detail.body)) + (detail.body.opening ? ' · ' + echapperHtml(detail.body.opening) : '') + '</p>' +
            (positions
                ? '<div class="rejeu-disposition">' +
                  '  <div>' +
                  '    <div class="rejeu-ensemble">' +
                  '      <div class="barre-eval" aria-hidden="true"><div class="barre-eval-blancs" id="barre-eval-blancs"></div></div>' +
                  '      <div class="echiquier-cadre" id="echiquier" role="img" aria-label="Échiquier"></div>' +
                  '    </div>' +
                  '    <div class="rejeu-controles">' +
                  '      <button type="button" id="cp-debut" aria-label="Début">⏮</button>' +
                  '      <button type="button" id="cp-avant" aria-label="Coup précédent">◀</button>' +
                  '      <button type="button" id="cp-apres" aria-label="Coup suivant">▶</button>' +
                  '      <button type="button" id="cp-fin" aria-label="Fin">⏭</button>' +
                  '      <button type="button" id="cp-inverser" aria-label="Retourner l\'échiquier" title="Retourner l\'échiquier">⇅</button>' +
                  '    </div>' +
                  '    <p class="rejeu-position" id="rejeu-position"></p>' +
                  '    <div class="analyse-panneau">' +
                  '      <div class="analyse-actions">' +
                  '        <button type="button" id="cp-analyser" class="bouton">Analyser la partie</button>' +
                  '        <label class="analyse-profondeur" for="analyse-profondeur">Profondeur' +
                  '          <select id="analyse-profondeur">' +
                  '            <option value="10">10</option>' +
                  '            <option value="12" selected>12</option>' +
                  '            <option value="14">14</option>' +
                  '            <option value="16">16</option>' +
                  '          </select>' +
                  '        </label>' +
                  '      </div>' +
                  '      <p class="analyse-etat" id="analyse-etat"></p>' +
                  '      <p class="analyse-eval" id="analyse-eval" aria-live="polite"></p>' +
                  '      <p class="analyse-moteur">Analyse exécutée sur ton appareil par Stockfish 19 Lite (WASM, GPLv3) — ' +
                  '        <a href="https://github.com/official-stockfish/Stockfish" target="_blank" rel="noopener noreferrer">sources du moteur</a></p>' +
                  '    </div>' +
                  '  </div>' +
                  '  <div class="coups" id="liste-coups"><ol>' + listeCoups + '</ol></div>' +
                  '</div>'
                : '<p>Le rejeu n\'est pas disponible pour cette partie. PGN :</p>' +
                  '<pre style="white-space:pre-wrap">' + echapperHtml(detail.body.pgn || '') + '</pre>');

        if (!positions) {
            return;
        }

        detacherClavierRejeu();
        plateauRejeu = creerEchiquierRejeu(document.getElementById('echiquier'), {
            fen: positions[0],
            inverser,
        });

        function afficher(ply) {
            courant = Math.max(0, Math.min(ply, positions.length - 1));
            plateauRejeu.afficher(positions[courant]);
            document.getElementById('rejeu-position').textContent = courant === 0
                ? 'Position de départ'
                : 'Coup ' + Math.ceil(courant / 2) + (courant % 2 === 1 ? '… ' : '. ') + (sans[courant - 1] || '');
            document.querySelectorAll('#liste-coups button.coup').forEach((bouton) => {
                bouton.classList.toggle('courant', Number(bouton.getAttribute('data-ply')) === courant - 1);
            });
            const info = analyses ? analyses[courant] : null;
            const barre = document.getElementById('barre-eval-blancs');
            const evalTexte = document.getElementById('analyse-eval');
            if (info) {
                const cp = evalBlancs(positions[courant], info);
                barre.style.height = pourcentageBlancs(cp) + '%';
                evalTexte.textContent = 'Évaluation : ' + formaterEval(cp) + ' (point de vue des blancs)';
                plateauRejeu.marquerCoup(info.pv[0]);
            } else {
                barre.style.height = '50%';
                evalTexte.textContent = '';
                plateauRejeu.effacerMarques();
            }
        }

        document.getElementById('cp-debut').addEventListener('click', () => afficher(0));
        document.getElementById('cp-avant').addEventListener('click', () => afficher(courant - 1));
        document.getElementById('cp-apres').addEventListener('click', () => afficher(courant + 1));
        document.getElementById('cp-fin').addEventListener('click', () => afficher(positions.length - 1));
        document.getElementById('cp-inverser').addEventListener('click', () => {
            inverser = !inverser;
            plateauRejeu.inverser(inverser);
        });
        document.querySelectorAll('#liste-coups button.coup').forEach((bouton) => {
            bouton.addEventListener('click', () => afficher(Number(bouton.getAttribute('data-ply')) + 1));
        });

        ecouteurClavier = (evenement) => {
            if (evenement.key === 'ArrowLeft') { afficher(courant - 1); }
            if (evenement.key === 'ArrowRight') { afficher(courant + 1); }
            if (evenement.key === 'Home') { afficher(0); }
            if (evenement.key === 'End') { afficher(positions.length - 1); }
        };
        document.addEventListener('keydown', ecouteurClavier);

        let analyseEnCours = false;
        let annulee = false;
        const boutonAnalyser = document.getElementById('cp-analyser');
        const etatAnalyse = document.getElementById('analyse-etat');

        function appliquerAvis() {
            document.querySelectorAll('#liste-coups button.coup').forEach((bouton) => {
                const ply = Number(bouton.getAttribute('data-ply'));
                const perte = perteCoup(analyses[ply], analyses[ply + 1]);
                const estMeilleur = Boolean(analyses[ply] && analyses[ply].pv.length > 0 && movesUci[ply] === analyses[ply].pv[0]);
                const avis = classerCoup(perte, estMeilleur);
                if (!avis) {
                    return;
                }
                bouton.classList.add('avis-' + avis);
                bouton.title = estMeilleur
                    ? 'Meilleur coup selon Stockfish'
                    : 'Perte d\'environ ' + (perte / 100).toFixed(1) + ' pion(s) selon Stockfish';
                bouton.innerHTML = echapperHtml(sans[ply]) + ' <span class="avis">' + GLYPHES_AVIS[avis] + '</span>';
            });
        }

        boutonAnalyser.addEventListener('click', () => {
            if (analyseEnCours) {
                annulee = true;
                if (moteur) {
                    moteur.annuler();
                }
                return;
            }
            if (typeof Worker === 'undefined' || typeof WebAssembly === 'undefined') {
                etatAnalyse.textContent = 'L\'analyse locale n\'est pas disponible sur ce navigateur.';
                return;
            }
            const profondeur = Number(document.getElementById('analyse-profondeur').value) || 12;
            analyseEnCours = true;
            annulee = false;
            boutonAnalyser.textContent = 'Arrêter l\'analyse';
            etatAnalyse.textContent = 'Chargement du moteur local…';
            moteur = new MoteurLocal();
            moteur.analyserSequence(positions, {
                profondeur,
                onProgression: (fait, total) => {
                    etatAnalyse.textContent = 'Analyse en cours : position ' + fait + '/' + total + '…';
                },
            }).then((resultats) => {
                if (!plateauRejeu) {
                    return;
                }
                analyses = resultats;
                appliquerAvis();
                afficher(courant);
                etatAnalyse.textContent = annulee
                    ? 'Analyse interrompue : ' + resultats.length + ' position(s) évaluées.'
                    : 'Analyse terminée : ' + resultats.length + ' position(s) évaluées.';
            }).catch(() => {
                etatAnalyse.textContent = 'Le moteur local a rencontré une erreur.';
            }).finally(() => {
                analyseEnCours = false;
                boutonAnalyser.textContent = 'Analyser la partie';
            });
        });

        afficher(0);
    });
}
