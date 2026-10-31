'use strict';

/*
 * Cartes de contenu, présentées différemment selon le type (voir section 6.3 du cahier de charges) :
 * vidéo = vignette sombre avec bouton de lecture, photos = galerie, article = bloc éditorial.
 */
const Cartes = (() => {
  const TYPES = {
    video: { libelle: 'Vidéo', classe: 'carte-video' },
    photo: { libelle: 'Photos', classe: 'carte-photo' },
    texte: { libelle: 'Article', classe: 'carte-texte' },
  };

  function prix(contenu) {
    if (contenu.mode_monetisation === 'unite') return contenu.prix + ' FCFA';
    return contenu.mode_monetisation === 'abonnement' ? 'Abonnement' : 'Gratuit';
  }

  function contenu(c) {
    const type = TYPES[c.type] || TYPES.video;
    const vignette = Layout.el('div', { class: 'vignette' });
    if (c.type === 'video') {
      vignette.append(Layout.el('span', { class: 'lecture', 'aria-hidden': 'true' }, '▶'));
    }
    return Layout.el(
      'a',
      { class: 'carte-contenu ' + type.classe, href: '/contenu.html?id=' + encodeURIComponent(c.id) },
      vignette,
      Layout.el(
        'div',
        { class: 'carte-corps' },
        Layout.el('span', { class: 'etiquette' }, type.libelle),
        Layout.el('span', { class: 'carte-titre' }, c.titre),
        Layout.el('span', { class: 'carte-sous-titre' }, c.danse + ' · ' + prix(c) + ' · par ' + c.createur)
      )
    );
  }

  return { contenu, prix };
})();
