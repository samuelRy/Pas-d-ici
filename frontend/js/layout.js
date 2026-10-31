'use strict';

/*
 * Éléments communs à toutes les pages : en-tête, navigation, affichage des erreurs.
 * Les textes venant du serveur sont toujours insérés avec append() / textContent,
 * jamais avec innerHTML, pour éviter les failles d'injection de code.
 */
const Layout = (() => {
  function el(tag, attributes, ...children) {
    const node = document.createElement(tag);
    for (const [name, value] of Object.entries(attributes || {})) {
      node.setAttribute(name, value);
    }
    node.append(...children);
    return node;
  }

  function showErrors(container, messages) {
    container.replaceChildren(el('ul', {}, ...messages.map((message) => el('li', {}, message))));
    container.hidden = false;
  }

  function hideErrors(container) {
    container.replaceChildren();
    container.hidden = true;
  }

  async function renderHeader() {
    const host = document.getElementById('entete');
    const nav = el('nav', { class: 'navigation', 'aria-label': 'Navigation principale' });
    host.replaceChildren(el('a', { class: 'marque', href: '/' }, 'Danses du Bénin'), nav);

    let session = { user: null };
    try {
      session = await Api.session();
    } catch {
      /* l'en-tête s'affiche comme pour un visiteur */
    }

    const user = session.user;
    nav.append(el('a', { href: '/explorer.html' }, 'Explorer'));
    if (user) {
      const logout = el('button', { type: 'button', class: 'bouton bouton-discret' }, 'Se déconnecter');
      logout.addEventListener('click', async () => {
        try {
          await Api.post('/auth/logout');
        } finally {
          Api.reset();
          window.location.href = '/';
        }
      });
      if (user.role === 'createur') {
        nav.append(
          el('a', { href: '/publier-video.html' }, 'Publier'),
          el('a', { href: '/dashboard.html' }, 'Mon espace')
        );
      }
      if (user.role === 'admin') {
        nav.append(el('a', { href: '/admin.html' }, 'Administration'));
      }
      nav.append(el('span', { class: 'salutation' }, 'Bonjour, ' + user.nom), logout);
    } else {
      nav.append(
        el('a', { href: '/connexion.html' }, 'Se connecter'),
        el('a', { class: 'bouton', href: '/inscription.html' }, 'Créer un compte')
      );
    }
    return session;
  }

  return { ready: renderHeader(), el, showErrors, hideErrors };
})();
