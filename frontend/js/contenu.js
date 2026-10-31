'use strict';

const el = Layout.el;
const alerte = document.getElementById('alerte');

const STATUTS = {
  brouillon: "Ce contenu est un brouillon : il n'est pas visible du public.",
  en_validation: "Ce contenu est en attente de validation : il n'est pas encore visible du public.",
  refuse: "Ce contenu a été refusé : il n'est pas visible du public.",
};
const TYPES = { video: 'Vidéo', photo: 'Photos', texte: 'Article' };

function lecteur(contenu) {
  const zone = document.getElementById('lecteur');
  if (contenu.type === 'photo') {
    const medias = (contenu.medias || []).filter(m => contenu.peut_lire || m.apercu_gratuit);
    if (!medias.length) { zone.replaceChildren(el('div', { class: 'lecteur lecteur-verrouille' }, el('span', {}, 'Galerie réservée · débloquez l’accès pour voir les photos'))); return; }
    const gallery = el('div', { class: 'galerie-photos' });
    medias.forEach((m, index) => gallery.append(el('img', { class: 'image-galerie', alt: contenu.titre + ' — photo ' + (index + 1), src: '/api/contenus/media?id=' + encodeURIComponent(contenu.id) + '&media_id=' + encodeURIComponent(m.id) })));
    if (!contenu.peut_lire && contenu.mode_monetisation !== 'gratuit') gallery.append(el('p', { class: 'note' }, 'Aperçu gratuit. Débloquez la galerie pour accéder à toutes les photos.'));
    zone.replaceChildren(gallery); return;
  }
  if (!contenu.peut_lire && contenu.mode_monetisation !== 'gratuit') {
    zone.replaceChildren(el('div', { class: 'lecteur lecteur-verrouille' }, el('span', {}, 'Contenu réservé · débloquez l’accès pour le consulter')));
    return;
  }
  if (contenu.type === 'video') {
    zone.replaceChildren(el('div', { class: 'lecteur' }, el('video', { controls: '', preload: 'metadata', src: '/api/contenus/media?id=' + encodeURIComponent(contenu.id) })));
  } else {
    zone.replaceChildren(el('div', { class: 'article-couverture' }, el('span', {}, 'ARTICLE CULTUREL'), el('span', {}, contenu.danse)));
  }
}

function afficherArticle(contenu) {
  if (contenu.type !== 'texte') return;
  const section = document.getElementById('section-article');
  document.getElementById('chapeau-article').textContent = contenu.chapeau || '';
  const body = document.getElementById('corps-article');
  body.replaceChildren();
  if (contenu.corps) {
    // Rendu limité du balisage de mise en forme saisi dans l’éditeur ; le texte est échappé avant les balises autorisées.
    const safe = contenu.corps.split('\n').map(line => {
      let text = line.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
      text = text.replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>').replace(/\*(.+?)\*/g,'<em>$1</em>');
      text = text.replace(/\[([^\]]+)\]\((https?:\/\/[^ )]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
      if (text.startsWith('&gt; ')) return '<blockquote>' + text.slice(5) + '</blockquote>';
      return '<p>' + (text || '&nbsp;') + '</p>';
    }).join('');
    body.innerHTML = safe;
  } else if (contenu.mode_monetisation !== 'gratuit') {
    body.append(el('p', { class: 'note' }, 'Achetez ce contenu ou abonnez-vous à ce créateur pour lire l’article complet.'));
  }
  document.getElementById('sources-article').textContent = contenu.sources ? 'Sources citées : ' + contenu.sources : '';
  document.getElementById('temps-lecture').textContent = contenu.temps_lecture_min ? 'Temps de lecture estimé : ' + contenu.temps_lecture_min + ' min' : '';
  section.hidden = false;
}

function boiteAcces(contenu, user) {
  const boite = document.getElementById('acces');
  boite.replaceChildren();

  if (contenu.mode_monetisation === 'gratuit') {
    boite.append(el('p', { class: 'acces-titre' }, 'Accès libre'));
    return;
  }

  const unite = contenu.mode_monetisation === 'unite';
  const paiement = el('button', { type: 'button', class: 'bouton bouton-large',}, unite ? 'Achat à l’unité' : "S'abonner");
  if (unite) {
    paiement.addEventListener('click', () => {
      const params = new URLSearchParams(window.location.search);
      window.location.href = '/unite.html?id=' + encodeURIComponent(params.get('id') || '');
    });

  }
  else {
    paiement.addEventListener('click', () => {
      const params = new URLSearchParams(window.location.search);
      window.location.href = '/abonnement.html?id=' + encodeURIComponent(params.get('id') || '');
    });
  }
  boite.append(
    el(
      'div',
      { class: 'acces-ligne' },
      el('span', {}, unite ? 'Accès à la vidéo complète' : 'Réservé aux abonnés de ce créateur'),
      el('strong', { class: 'acces-prix' }, unite ? contenu.prix + ' FCFA' : '')
    ),
    paiement,
  );

  if (contenu.peut_lire && user) {
    boite.hidden=true;
    let messageAcces = unite ? 'La vidéo est disponible pour vous.' : 'Votre abonnement est actif.';
    if (user.role === 'admin') messageAcces = 'Vous voyez ce contenu en tant qu’administrateur.';
    else if (Number(user.id) === Number(contenu.createur_id)) messageAcces = 'Vous voyez votre propre contenu.';
  }
}

Layout.ready.then(async (session) => {
  const id = new URLSearchParams(window.location.search).get('id');
  if (!id) {
    Layout.showErrors(alerte, ['Contenu introuvable.']);
    return;
  }

  try {
    const contenu = await Api.get('/contenus/detail?id=' + encodeURIComponent(id));

    document.title = contenu.titre + ' — Danses du Bénin';
    document.getElementById('kicker').textContent = (TYPES[contenu.type] || contenu.type) + ' · ' + contenu.danse;
    document.getElementById('titre').textContent = contenu.titre;
    document.getElementById('createur').textContent = 'Par ' + contenu.createur;

    const note = document.getElementById('note-statut');
    if (contenu.statut !== 'publie') {
      note.textContent = STATUTS[contenu.statut] || '';
      note.hidden = false;
    }

    lecteur(contenu);
    afficherArticle(contenu);
    boiteAcces(contenu, session.user);

    if (contenu.contexte_culturel) {
      document.getElementById('contexte').textContent = contenu.contexte_culturel;
      document.getElementById('section-contexte').hidden = false;
    }
    document.getElementById('fiche').hidden = false;
  } catch (error) {
    Layout.showErrors(alerte, error.erreurs);
  }
});
