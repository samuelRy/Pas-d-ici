'use strict';

Layout.ready.then(async (session) => {
  const user = session.user;
  document.getElementById('note-attente').hidden = !(user && user.statut_compte === 'en_attente');
  const alerte = document.getElementById('alerte');
  const listeRecents = document.getElementById('liste-recents');
  const aucunRecent = document.getElementById('aucun-recent');
  const filtres = document.getElementById('filtres-accueil');
  try {
    const [danses, recents] = await Promise.all([Api.get('/danses'), Api.get('/contenus?limite=6')]);
    const options = [{id:'', nom:'Tout'}, ...danses];
    filtres.replaceChildren(...options.map((danse, i) => {
      const b = Layout.el('button', {type:'button', class:'filtre' + (i===0?' actif':''), 'aria-pressed':String(i===0)}, danse.nom);
      b.addEventListener('click', () => { filtres.querySelectorAll('button').forEach(x => {x.classList.remove('actif');x.setAttribute('aria-pressed','false');}); b.classList.add('actif');b.setAttribute('aria-pressed','true'); window.location.href = '/explorer.html' + (danse.id ? '?danse_id='+encodeURIComponent(danse.id) : ''); });
      return b;
    }));
    const items = recents.items || [];
    const vedette = items.find(c => c.type === 'video') || items[0];
    if (vedette) {
      const link = document.getElementById('vedette');
      link.href = '/contenu.html?id=' + encodeURIComponent(vedette.id);
      link.querySelector('.vedette-texte').replaceChildren(Layout.el('strong', {}, vedette.titre), Layout.el('span', {}, (vedette.danse || 'Danse') + ' · ' + (vedette.createur || 'Créateur')));
    }
    aucunRecent.hidden = items.length > 0;
    listeRecents.replaceChildren(...items.map((contenu) => Layout.el('li', {}, Cartes.contenu(contenu))));
  } catch (error) { Layout.showErrors(alerte, error.erreurs); }
});
