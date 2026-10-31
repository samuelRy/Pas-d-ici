'use strict';
const el = Layout.el;
const money = n => new Intl.NumberFormat('fr-FR').format(Number(n || 0)) + ' FCFA';
const types = { video: 'Vidéo', photo: 'Photos', texte: 'Article' };
const statuts = {
  publie: ['Publié', 'pastille-ok'], en_validation: ['En validation', 'pastille-attente'],
  refuse: ['Refusé', 'pastille-refus'], brouillon: ['Brouillon', 'pastille-neutre']
};
Layout.ready.then(async session => {
  const alerte = document.getElementById('alerte');
  if (!session.user) { location.href = '/connexion.html'; return; }
  if (session.user.role !== 'createur') { Layout.showErrors(alerte, ['Le tableau de bord est réservé aux créateurs.']); return; }
  try {
    const data = await Api.get('/dashboard');
    document.getElementById('solde').textContent = money(data.solde_disponible);
    document.getElementById('vues').textContent = new Intl.NumberFormat('fr-FR').format(data.vues_totales);
    document.getElementById('publies').textContent = data.contenus_publies;
    document.getElementById('attente').textContent = data.en_validation;
    const liste = document.getElementById('liste-contenus');
    liste.replaceChildren(...data.contenus.slice(0, 6).map(c => {
      const status = statuts[c.statut] || statuts.brouillon;
      const media = el('div', { class: 'miniature miniature-' + c.type, 'aria-hidden': 'true' }, c.type === 'video' ? '▶' : c.type === 'photo' ? '▧' : 'Aa');
      const info = el('div', { class: 'ligne-texte' }, el('span', { class: 'ligne-titre' }, c.titre), el('span', { class: 'ligne-detail' }, `${types[c.type] || c.type} · ${c.nb_vues} vues · ${c.danse}`));
      const right = el('div', { class: 'dashboard-ligne-droite' }, el('strong', {}, c.mode_monetisation === 'unite' ? money(c.prix) : c.mode_monetisation === 'abonnement' ? 'Abonnement' : 'Gratuit'), el('span', { class: 'pastille ' + status[1] }, status[0]));
      return el('li', { class: 'ligne-contenu dashboard-ligne' }, media, info, right);
    }));
    document.getElementById('vide-contenus').hidden = data.contenus.length > 0;
    const retraits = document.getElementById('liste-retraits');
    const retraitLabels = { demande: ['En attente de confirmation', 'pastille-attente'], paye: ['Payé par l’administration', 'pastille-ok'], refuse: ['Refusé · solde recrédité', 'pastille-refus'] };
    retraits.replaceChildren(...data.retraits.map(r => {
      const status = retraitLabels[r.statut] || [r.statut, 'pastille-neutre'];
      const info = el('div', { class: 'ligne-texte' }, el('span', { class: 'ligne-titre' }, money(r.montant)), el('span', { class: 'ligne-detail' }, `${r.operateur === 'mtn_momo' ? 'MTN MoMo' : 'Moov Money'} · ${String(r.demande_le).slice(0, 10)}`), r.motif_refus ? el('span', { class: 'ligne-motif' }, 'Motif : ' + r.motif_refus) : '');
      return el('li', { class: 'ligne-contenu' }, info, el('span', { class: 'pastille ' + status[1] }, status[0]));
    }));
    document.getElementById('vide-retraits').hidden = data.retraits.length > 0;
  } catch (error) { Layout.showErrors(alerte, error.erreurs || [error.message]); }
});
