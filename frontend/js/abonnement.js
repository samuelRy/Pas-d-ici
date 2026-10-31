'use strict';

const formAbonnement = document.getElementById('abonnement');
const alerte = document.getElementById('alerte');
const succes = document.getElementById('succes');
const chargement = document.getElementById('chargement');
const ficheCreateur = document.getElementById('fiche-createur');
const numero = document.getElementById('numero');

let createurId = null;
let contenuId = null;
let tarifs = null;

function afficherErreur(messages) {
  alerte.replaceChildren(...messages.map((message) => {
    const p = document.createElement('p');
    p.textContent = message;
    return p;
  }));
  alerte.hidden = false;
}

function formaterPrix(prix) {
  return new Intl.NumberFormat('fr-FR').format(prix) + ' FCFA';
}

// La page est appelée depuis la fiche de contenu : ?id=<contenu_id>.
Layout.ready.then(async (session) => {
  try {
    const params = new URLSearchParams(window.location.search);
    contenuId = params.get('id');
    if (!contenuId || !/^\d+$/.test(contenuId)) {
      throw new Error('Contenu introuvable. Revenez à la page du contenu et réessayez.');
    }
    document.getElementById('retour-contenu').href = '/contenu.html?id=' + encodeURIComponent(contenuId);

    if (!session.user) {
      throw new Error('Connectez-vous pour pouvoir vous abonner à un créateur.');
    }

    const contenu = await Api.get('/contenus/detail?id=' + encodeURIComponent(contenuId));
    createurId = Number(contenu.createur_id);
    document.getElementById('nom-createur').textContent = 'Abonnement à ' + contenu.createur;
    document.getElementById('titre-contenu').textContent = 'Depuis le contenu : ' + contenu.titre;
    document.getElementById('details-createur').textContent = (contenu.region_origine || 'Danse traditionnelle béninoise') + ' · Créateur sur Danses du Bénin';

    const offres = await Api.get('/paiement/offres?createur_id=' + encodeURIComponent(createurId));
    tarifs = offres.formules;
    for (const formule of tarifs) {
      const cible = document.getElementById(formule.formule === 'mensuel' ? 'prix-mensuel' : 'prix-annuel');
      cible.textContent = formaterPrix(formule.prix);
    }

    chargement.hidden = true;
    ficheCreateur.hidden = false;
    if (offres.abonne_jusqua) {
      afficherErreur(['Vous êtes déjà abonné à ce créateur jusqu’au ' + offres.abonne_jusqua + '.']);
      return;
    }
    formAbonnement.hidden = false;
  } catch (error) {
    chargement.hidden = true;
    afficherErreur(error.erreurs || [error.message || 'Impossible de charger les offres.']);
  }
});

formAbonnement.addEventListener('submit', async (event) => {
  event.preventDefault();
  alerte.hidden = true;
  succes.hidden = true;
  if (!formAbonnement.reportValidity()) return;

  const bouton = formAbonnement.querySelector('button[type="submit"]');
  const texteInitial = bouton.textContent;
  bouton.disabled = true;
  bouton.textContent = 'Confirmation en cours…';

  try {
    const resultat = await Api.post('/paiement/abonnement', {
      createur_id: createurId,
      formule: formAbonnement.elements.formule.value,
      moyen_paiement: formAbonnement.elements.moyen_paiement.value,
      numero: numero.value.trim(),
    });
    formAbonnement.hidden = true;
    succes.textContent = `Paiement simulé confirmé : abonnement actif jusqu’au ${resultat.fin}. Référence : ${resultat.reference}. Aucun argent réel n’a été débité.`;
    succes.hidden = false;
    succes.scrollIntoView({ behavior: 'smooth', block: 'start' });
  } catch (error) {
    afficherErreur(error.erreurs || [error.message || 'Le paiement simulé a échoué.']);
    alerte.scrollIntoView({ behavior: 'smooth', block: 'start' });
  } finally {
    bouton.disabled = false;
    bouton.textContent = texteInitial;
  }
});
