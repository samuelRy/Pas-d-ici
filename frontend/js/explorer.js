'use strict';

const LIMITE = 12;
const TYPES = [
  { valeur: '', libelle: 'Tout' },
  { valeur: 'video', libelle: 'Vidéos' },
  { valeur: 'photo', libelle: 'Photos' },
  { valeur: 'texte', libelle: 'Articles' },
];

const alerte = document.getElementById('alerte');
const champRecherche = document.getElementById('recherche');
const filtresDanses = document.getElementById('filtres-danses');
const filtresTypes = document.getElementById('filtres-types');
const liste = document.getElementById('liste');
const compte = document.getElementById('compte');
const vide = document.getElementById('vide');
const plus = document.getElementById('plus');

const parametres = new URLSearchParams(window.location.search);
const etat = {
  danseId: parametres.get('danse_id') || '',
  type: TYPES.some((t) => t.valeur === parametres.get('type')) ? parametres.get('type') : '',
  q: parametres.get('q') || '',
  decalage: 0,
};
let numeroRequete = 0;
let minuterie = null;

/** Garde l'adresse à jour : le bouton « retour », l'actualisation et le partage conservent les filtres. */
function synchroniserAdresse() {
  const p = new URLSearchParams();
  if (etat.danseId) p.set('danse_id', etat.danseId);
  if (etat.type) p.set('type', etat.type);
  if (etat.q) p.set('q', etat.q);
  const texte = p.toString();
  history.replaceState(null, '', window.location.pathname + (texte ? '?' + texte : ''));
}

async function charger(suite) {
  const courant = ++numeroRequete;
  Layout.hideErrors(alerte);
  if (!suite) {
    // Changement de filtre : on estompe l'ancienne liste au lieu de la laisser comme si de rien n'était.
    liste.setAttribute('aria-busy', 'true');
    compte.textContent = 'Chargement…';
    vide.hidden = true;
    plus.hidden = true;
  }

  const params = new URLSearchParams({ limite: String(LIMITE), decalage: String(suite ? etat.decalage : 0) });
  if (etat.danseId) params.set('danse_id', etat.danseId);
  if (etat.type) params.set('type', etat.type);
  if (etat.q) params.set('q', etat.q);

  try {
    const resultat = await Api.get('/contenus?' + params.toString());
    if (courant !== numeroRequete) return; // une requête plus récente est partie entre-temps

    if (!suite) liste.replaceChildren();
    liste.removeAttribute('aria-busy');
    resultat.items.forEach((contenu) => liste.append(Layout.el('li', {}, Cartes.contenu(contenu))));

    etat.decalage = resultat.decalage + resultat.items.length;
    compte.textContent = resultat.total + (resultat.total > 1 ? ' contenus' : ' contenu');
    vide.hidden = resultat.total > 0;
    plus.hidden = etat.decalage >= resultat.total;
  } catch (error) {
    if (courant !== numeroRequete) return;
    liste.removeAttribute('aria-busy');
    compte.textContent = '';
    Layout.showErrors(alerte, error.erreurs);
  }
}

function recharger() {
  etat.decalage = 0;
  synchroniserAdresse();
  charger(false);
}

champRecherche.value = etat.q;
if (window.location.hash === '#recherche') champRecherche.focus();
champRecherche.addEventListener('input', () => {
  clearTimeout(minuterie);
  minuterie = setTimeout(() => {
    etat.q = champRecherche.value.trim();
    recharger();
  }, 300);
});
plus.addEventListener('click', () => charger(true));

Layout.filtres(filtresTypes, TYPES, etat.type, (valeur) => {
  etat.type = valeur;
  recharger();
});

Layout.filtresEnAttente(filtresDanses, 4);
Api.get('/danses')
  .then((danses) => {
    const options = [{ valeur: '', libelle: 'Toutes' }, ...danses.map((d) => ({ valeur: String(d.id), libelle: d.nom }))];
    const inconnue = etat.danseId && !options.some((o) => o.valeur === etat.danseId);
    Layout.filtres(filtresDanses, options, inconnue ? '' : etat.danseId, (valeur) => {
      etat.danseId = valeur;
      recharger();
    });
    if (inconnue) { etat.danseId = ''; recharger(); }
  })
  .catch((error) => Layout.showErrors(alerte, error.erreurs));

recharger();
