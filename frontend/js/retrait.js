'use strict';
const formRetrait = document.getElementById('retrait');
const alerte = document.getElementById('alerte');
const succes = document.getElementById('succes');
const money = n => new Intl.NumberFormat('fr-FR').format(Number(n || 0)) + ' FCFA';
Layout.ready.then(async session => {
  if (!session.user) { location.href = '/connexion.html'; return; }
  if (session.user.role !== 'createur') { Layout.showErrors(alerte, ['Seuls les créateurs peuvent demander un retrait.']); return; }
  try {
    const dashboard = await Api.get('/dashboard');
    document.getElementById('solde-disponible').textContent = money(dashboard.solde_disponible);
    document.getElementById('montant').max = String(dashboard.solde_disponible);
    if (dashboard.solde_disponible < 1) { Layout.showErrors(alerte, ['Votre solde disponible est insuffisant pour demander un retrait.']); return; }
    formRetrait.hidden = false;
  } catch (error) { Layout.showErrors(alerte, error.erreurs || [error.message]); }
});
formRetrait.addEventListener('submit', async event => {
  event.preventDefault(); Layout.hideErrors(alerte); if (!formRetrait.reportValidity()) return;
  const montant = Number(formRetrait.elements.montant.value);
  const max = Number(formRetrait.elements.montant.max);
  if (!Number.isInteger(montant) || montant < 1 || montant > max) { Layout.showErrors(alerte, ['Le montant doit être un nombre entier compris dans votre solde disponible.']); return; }
  const button = formRetrait.querySelector('button[type="submit"]'); const original = button.textContent; button.disabled = true; button.textContent = 'Envoi en cours…';
  try {
    const result = await Api.post('/paiement/retrait', { montant: String(montant), moyen_paiement: formRetrait.elements.moyen_paiement.value, numero: formRetrait.elements.numero.value.trim() });
    formRetrait.hidden = true; succes.textContent = `Demande n°${result.retrait_id} envoyée. ${money(result.montant)} ont été réservés en attendant la confirmation de l’administration.`; succes.hidden = false;
  } catch (error) { Layout.showErrors(alerte, error.erreurs || [error.message]); }
  finally { button.disabled = false; button.textContent = original; }
});
