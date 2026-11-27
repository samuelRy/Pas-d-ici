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
    // La page d'administration n'affiche pas l'en-tête public, mais elle a
    // tout de même besoin de la vraie session pour vérifier le rôle admin.
    let session = { user: null };
    try { session = await Api.session(); } catch { /* mode visiteur */ }
    if (!host || window.location.pathname.endsWith('/admin.html')) return session;
    const user = session.user;
    const path = window.location.pathname;
    const nav = el('nav', { class: 'navigation-basse', 'aria-label': 'Navigation principale' });
    const items = [
      { label: 'Accueil', href: '/', icon: '⌂', active: path === '/' || path.endsWith('/index.html') },
      { label: 'Explorer', href: '/explorer.html', icon: '◈', active: path.endsWith('/explorer.html') || path.endsWith('/contenu.html') },
      { label: 'Publier', href: user && user.role === 'createur' ? '/publier-video.html' : '/connexion.html', icon: '↥', active: path.includes('publier-') },
      { label: 'Mon espace', href: user && user.role === 'createur' ? '/dashboard.html' : (user && user.role === 'admin' ? '/admin.html' : '/connexion.html'), icon: '◎', active: path.endsWith('/dashboard.html') || path.endsWith('/mes-contenus.html') || path.endsWith('/admin.html') }
    ];
    nav.replaceChildren(...items.map((item) => {
      const link = el('a', { href: item.href, class: 'navigation-item' + (item.active ? ' actif' : ''), ...(item.active ? {'aria-current':'page'} : {}) },
        el('span', { class: 'navigation-icone', 'aria-hidden': 'true' }, item.icon),
        el('span', { class: 'navigation-libelle' }, item.label));
      return link;
    }));
    const brand = el('a', { class: 'marque', href: '/' }, 'Danses du Bénin');
    const actions = el('div', { class: 'entete-actions' });
    if (user) {
      actions.append(el('span', { class: 'salutation' }, 'Bonjour, ' + user.nom));
      const logout = el('button', { type: 'button', class: 'bouton bouton-discret' }, 'Déconnexion');
      logout.addEventListener('click', async () => { try { await Api.post('/auth/logout'); } finally { Api.reset(); window.location.href = '/'; } });
      actions.append(logout);
    } else {
      actions.append(el('a', { href: '/connexion.html', class: 'connexion-lien' }, 'Se connecter'));
    }
    host.replaceChildren(brand, actions);
    document.body.append(nav);
    document.body.classList.add('avec-navigation-basse');
    return session;
  }

  return { ready: renderHeader(), el, showErrors, hideErrors };
})();
