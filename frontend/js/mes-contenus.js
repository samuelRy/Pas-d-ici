'use strict';

const STATUTS = {
  brouillon: { libelle: 'Brouillon', classe: 'pastille-neutre' },
  en_validation: { libelle: 'En validation', classe: 'pastille-attente' },
  publie: { libelle: 'Publié', classe: 'pastille-ok' },
  refuse: { libelle: 'Refusé', classe: 'pastille-refus' },
};
const TYPES = { video: 'Vidéo', photo: 'Photos', texte: 'Article' };

function libellePrix(contenu) {
  if (contenu.mode_monetisation === 'unite') {
    return contenu.prix + ' FCFA';
  }
  return contenu.mode_monetisation === 'abonnement' ? 'Abonnement' : 'Gratuit';
}

Layout.ready.then(async (session) => {
  const user = session.user;
  const liste = document.getElementById('liste');
  const vide = document.getElementById('vide');
  const alerte = document.getElementById('alerte');

  if (!user) {
    window.location.href = '/connexion.html';
    return;
  }
  if (user.role !== 'createur') {
    Layout.showErrors(alerte, ['Cette page est réservée aux comptes créateurs.']);
    return;
  }

  try {
    const contenus = await Api.get('/mes-contenus');
    vide.hidden = contenus.length > 0;
    liste.replaceChildren(
      ...contenus.map((contenu) => {
        const statut = STATUTS[contenu.statut] || STATUTS.brouillon;
        return Layout.el(
          'li',
          { class: 'ligne-contenu' },
          Layout.el(
            'div',
            { class: 'ligne-texte' },
            Layout.el('span', { class: 'ligne-titre' }, contenu.titre),
            Layout.el(
              'span',
              { class: 'ligne-detail' },
              (TYPES[contenu.type] || contenu.type) + ' · ' + contenu.danse + ' · ' + libellePrix(contenu)
            ),
            contenu.statut === 'refuse' && contenu.motif_refus
              ? Layout.el('span', { class: 'ligne-detail ligne-motif' }, 'Motif du refus : ' + contenu.motif_refus)
              : ''
          ),
          Layout.el('span', { class: 'pastille ' + statut.classe }, statut.libelle)
        );
      })
    );
  } catch (error) {
    Layout.showErrors(alerte, error.erreurs);
  }
});
