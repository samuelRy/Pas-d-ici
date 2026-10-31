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

/** Boutons de filtre : un seul est actif à la fois (aria-pressed), le choix est transmis à auChoix(). */
function construireFiltres(conteneur, options, valeurActive, auChoix) {
  const boutons = options.map((option) => {
    const bouton = Layout.el('button', { type: 'button', class: 'filtre' }, option.libelle);
    bouton.addEventListener('click', () => {
      auChoix(option.valeur);
      majActifs();
    });
    return { option, bouton };
  });
  function majActifs() {
    const active = conteneur.dataset.actif;
    boutons.forEach(({ option, bouton }) => bouton.setAttribute('aria-pressed', String(option.valeur === active)));
  }
  conteneur.dataset.actif = valeurActive;
  conteneur.replaceChildren(...boutons.map((b) => b.bouton));
  return {
    choisir(valeur) {
      conteneur.dataset.actif = valeur;
      majActifs();
    },
    init: majActifs,
  };
}

async function charger(suite) {
  const courant = ++numeroRequete;
  Layout.hideErrors(alerte);

  const params = new URLSearchParams({ limite: String(LIMITE), decalage: String(suite ? etat.decalage : 0) });
  if (etat.danseId) params.set('danse_id', etat.danseId);
  if (etat.type) params.set('type', etat.type);
  if (etat.q) params.set('q', etat.q);

  try {
    const resultat = await Api.get('/contenus?' + params.toString());
    if (courant !== numeroRequete) return; // une requête plus récente est partie entre-temps

    if (!suite) liste.replaceChildren();
    resultat.items.forEach((contenu) => liste.append(Layout.el('li', {}, Cartes.contenu(contenu))));

    etat.decalage = resultat.decalage + resultat.items.length;
    compte.textContent = resultat.total + (resultat.total > 1 ? ' contenus' : ' contenu');
    vide.hidden = resultat.total > 0;
    plus.hidden = etat.decalage >= resultat.total;
  } catch (error) {
    Layout.showErrors(alerte, error.erreurs);
  }
}

function recharger() {
  etat.decalage = 0;
  charger(false);
}

champRecherche.value = etat.q;
champRecherche.addEventListener('input', () => {
  clearTimeout(minuterie);
  minuterie = setTimeout(() => {
    etat.q = champRecherche.value.trim();
    recharger();
  }, 300);
});
plus.addEventListener('click', () => charger(true));

const filtreTypes = construireFiltres(filtresTypes, TYPES, etat.type, (valeur) => {
  etat.type = valeur;
  recharger();
});
filtreTypes.init();

Api.get('/danses')
  .then((danses) => {
    const options = [{ valeur: '', libelle: 'Toutes' }, ...danses.map((d) => ({ valeur: String(d.id), libelle: d.nom }))];
    const filtreDanses = construireFiltres(filtresDanses, options, etat.danseId, (valeur) => {
      etat.danseId = valeur;
      recharger();
    });
    filtreDanses.init();
  })
  .catch((error) => Layout.showErrors(alerte, error.erreurs));

recharger();
