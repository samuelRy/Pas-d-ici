'use strict';

Layout.ready.then(async (session) => {
  const user = session.user;
  document.getElementById('note-attente').hidden = !(user && user.statut_compte === 'en_attente');

  const alerte = document.getElementById('alerte');
  const listeDanses = document.getElementById('liste-danses');
  const listeRecents = document.getElementById('liste-recents');
  const aucunRecent = document.getElementById('aucun-recent');

  try {
    const [danses, recents] = await Promise.all([Api.get('/danses'), Api.get('/contenus?limite=6')]);

    listeDanses.replaceChildren(
      ...danses.map((danse) =>
        Layout.el(
          'li',
          { class: 'carte' },
          Layout.el(
            'a',
            { class: 'carte-lien', href: '/explorer.html?danse_id=' + encodeURIComponent(danse.id) },
            Layout.el('span', { class: 'carte-titre' }, danse.nom),
            Layout.el('span', { class: 'carte-sous-titre' }, danse.region_origine || 'Région à préciser')
          )
        )
      )
    );

    aucunRecent.hidden = recents.items.length > 0;
    listeRecents.replaceChildren(...recents.items.map((contenu) => Layout.el('li', {}, Cartes.contenu(contenu))));
  } catch (error) {
    Layout.showErrors(alerte, error.erreurs);
  }
});
