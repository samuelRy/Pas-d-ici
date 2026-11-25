'use strict';

Layout.ready.then(async (session) => {
  const user = session.user;
  document.getElementById('note-attente').hidden = !(user && user.statut_compte === 'en_attente');
  const alerte = document.getElementById('alerte');
  const listeRecents = document.getElementById('liste-recents');
  const aucunRecent = document.getElementById('aucun-recent');
  const filtres = document.getElementById('filtres-accueil');
  const toutExplorer = document.getElementById('tout-explorer');
  let danseId = '';
  let numeroRequete = 0;

  Layout.filtresEnAttente(filtres, 4);

  /* « Récemment publié » suit la danse choisie, sans quitter la page. */
  async function chargerRecents() {
    const courant = ++numeroRequete;
    Layout.hideErrors(alerte);
    listeRecents.setAttribute('aria-busy', 'true');
    toutExplorer.href = '/explorer.html' + (danseId ? '?danse_id=' + encodeURIComponent(danseId) : '');
    const params = new URLSearchParams({ limite: '6' });
    if (danseId) params.set('danse_id', danseId);
    try {
      const recents = await Api.get('/contenus?' + params.toString());
      if (courant !== numeroRequete) return; // un choix plus récent est déjà parti
      const items = recents.items || [];
      aucunRecent.hidden = items.length > 0;
      listeRecents.replaceChildren(...items.map((contenu) => Layout.el('li', {}, Cartes.contenu(contenu))));
      listeRecents.removeAttribute('aria-busy');
      return items;
    } catch (error) {
      if (courant !== numeroRequete) return;
      listeRecents.removeAttribute('aria-busy');
      Layout.showErrors(alerte, error.erreurs);
    }
  }

  try {
    const [danses, items] = await Promise.all([Api.get('/danses'), chargerRecents()]);
    const options = [{ valeur: '', libelle: 'Tout' }, ...danses.map((d) => ({ valeur: String(d.id), libelle: d.nom }))];
    Layout.filtres(filtres, options, danseId, (valeur) => {
      danseId = valeur;
      chargerRecents();
    });

    const vedette = (items || []).find((c) => c.type === 'video') || (items || [])[0];
    if (vedette) {
      const link = document.getElementById('vedette');
      link.href = '/contenu.html?id=' + encodeURIComponent(vedette.id);
      link.querySelector('.vedette-texte').replaceChildren(Layout.el('strong', {}, vedette.titre), Layout.el('span', {}, (vedette.danse || 'Danse') + ' · ' + (vedette.createur || 'Créateur')));
    }
  } catch (error) { Layout.showErrors(alerte, error.erreurs); }
});
