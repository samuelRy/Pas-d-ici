'use strict';
const formUnite = document.getElementById('unite');
const alerte = document.getElementById('alerte');
const chargement = document.getElementById('chargement');
const fiche = document.getElementById('fiche-achat');
const numero = document.getElementById('numero');
const succes = document.getElementById('succes');
let createurId = null;
let contenuId = null;
let prix = null;
const money = n => new Intl.NumberFormat('fr-FR').format(Number(n || 0)) + ' FCFA';
function showError(messages) { Layout.showErrors(alerte, messages); }
Layout.ready.then(async session => {
  try {
    const params = new URLSearchParams(location.search); contenuId = params.get('id');
    if (!contenuId || !/^\d+$/.test(contenuId)) throw new Error('Contenu introuvable.');
    document.getElementById('retour-contenu').href = '/contenu.html?id=' + encodeURIComponent(contenuId);
    if (!session.user) { location.href = '/connexion.html'; return; }
    const content = await Api.get('/contenus/detail?id=' + encodeURIComponent(contenuId));
    if (content.mode_monetisation !== 'unite') throw new Error('Ce contenu n’est pas proposé à l’achat à l’unité.');
    createurId = Number(content.createur_id); prix = Number(content.prix);
    document.getElementById('titre-contenu').textContent = content.titre;
    document.getElementById('createur-contenu').textContent = 'Par ' + content.createur + ' · ' + content.danse;
    document.getElementById('prix-contenu').textContent = money(prix);
    fiche.hidden = false; chargement.hidden = true;
    if (content.peut_lire) { document.getElementById('deja-achete').hidden = false; return; }
    if (Number(session.user.id) === createurId || session.user.role === 'admin') { document.getElementById('deja-achete').hidden = false; return; }
    formUnite.hidden = false;
  } catch (error) { chargement.hidden = true; showError(error.erreurs || [error.message || 'Impossible de charger ce contenu.']); }
});
formUnite.addEventListener('submit', async event => {
  event.preventDefault(); Layout.hideErrors(alerte); succes.hidden = true;
  if (!formUnite.reportValidity()) return;
  const button = formUnite.querySelector('button[type="submit"]'); const original = button.textContent; button.disabled = true; button.textContent = 'Confirmation en cours…';
  try {
    const result = await Api.post('/paiement/contenu', { createur_id: createurId, id: Number(contenuId), prix: String(prix), moyen_paiement: formUnite.elements.moyen_paiement.value, numero: numero.value.trim() });
    formUnite.hidden = true; succes.textContent = `Achat confirmé. Vous avez désormais accès à ce contenu. Référence : ${result.reference}. Aucun argent réel n’a été débité.`; succes.hidden = false;
    succes.append(document.createTextNode(' '));
    const link = Layout.el('a',{href:'/contenu.html?id='+encodeURIComponent(contenuId)},'Ouvrir le contenu'); succes.append(link);
  } catch (error) { showError(error.erreurs || [error.message || 'Le paiement simulé a échoué.']); }
  finally { button.disabled = false; button.textContent = original; }
});
