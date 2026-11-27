'use strict';

/*
 * Éléments communs à toutes les pages : en-tête, navigation, filtres, affichage des erreurs.
 * Les textes venant du serveur sont toujours insérés avec append() / textContent,
 * jamais avec innerHTML, pour éviter les failles d'injection de code.
 */
const Layout = (() => {
  const SVG_NS = 'http://www.w3.org/2000/svg';

  function el(tag, attributes, ...children) {
    const node = document.createElement(tag);
    for (const [name, value] of Object.entries(attributes || {})) {
      node.setAttribute(name, value);
    }
    node.append(...children);
    return node;
  }

  /* Icônes de la maquette (couleur = currentColor, donc grises au repos, vertes quand l'onglet est actif). */
  const ICONES = {
    accueil: [
      ['path', { d: 'M3.5 11.6 12 4l8.5 7.6', fill: 'none' }],
      ['path', { d: 'M6 10.2V20h4.2v-5.2h3.6V20H18v-9.8', fill: 'none' }],
    ],
    explorer: [
      ['circle', { cx: '12', cy: '12', r: '9.2', fill: 'none' }],
      ['path', { d: 'm15.9 8.1-2 5.8-5.8 2 2-5.8z', fill: 'none' }],
    ],
    publier: [
      ['path', { d: 'M5 4.2h14', fill: 'none', 'stroke-width': '2.6', 'stroke-linecap': 'round' }],
      ['path', { d: 'M12 7.6 5.6 14.4h4.1V20h4.6v-5.6h4.1z', fill: 'currentColor' }],
    ],
    espace: [
      ['circle', { cx: '12', cy: '12', r: '9.2', fill: 'none' }],
      ['circle', { cx: '12', cy: '9.6', r: '3', fill: 'none' }],
      ['path', { d: 'M6.3 18.2c1-2.3 3.1-3.5 5.7-3.5s4.7 1.2 5.7 3.5', fill: 'none' }],
    ],
    recherche: [
      ['circle', { cx: '10.5', cy: '10.5', r: '6.2', fill: 'none' }],
      ['path', { d: 'm15.2 15.2 5 5', fill: 'none', 'stroke-linecap': 'round' }],
    ],
  };

  function icone(nom, taille) {
    const svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('width', String(taille));
    svg.setAttribute('height', String(taille));
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.9');
    svg.setAttribute('stroke-linejoin', 'round');
    for (const [tag, attributes] of ICONES[nom]) {
      const forme = document.createElementNS(SVG_NS, tag);
      for (const [cle, valeur] of Object.entries(attributes)) forme.setAttribute(cle, valeur);
      svg.append(forme);
    }
    return svg;
  }

  function showErrors(container, messages) {
    container.replaceChildren(el('ul', {}, ...messages.map((message) => el('li', {}, message))));
    container.hidden = false;
  }

  function hideErrors(container) {
    container.replaceChildren();
    container.hidden = true;
  }

  /**
   * Rangée de filtres en pastilles (un seul choix actif, défilement horizontal).
   * auChoix(valeur) n'est appelé que si le choix change ; l'état actif est mis à jour tout de suite.
   */
  function filtres(conteneur, options, valeurActive, auChoix) {
    let actif = valeurActive;
    conteneur.classList.add('filtres-defilant');
    conteneur.setAttribute('role', 'group');

    const boutons = options.map((option) => {
      const bouton = el('button', { type: 'button', class: 'filtre', 'aria-pressed': String(option.valeur === actif) }, option.libelle);
      bouton.addEventListener('click', () => {
        if (option.valeur === actif) return;
        actif = option.valeur;
        majActifs();
        centrer(bouton, true);
        auChoix(option.valeur);
      });
      return { option, bouton };
    });

    function majActifs() {
      boutons.forEach(({ option, bouton }) => bouton.setAttribute('aria-pressed', String(option.valeur === actif)));
    }

    /* Fait défiler la rangée (et non la page) pour que la pastille choisie reste visible. */
    function centrer(bouton, doux) {
      const cible = bouton.offsetLeft - (conteneur.clientWidth - bouton.offsetWidth) / 2;
      conteneur.scrollTo({ left: Math.max(0, cible), behavior: doux ? 'smooth' : 'auto' });
    }

    conteneur.replaceChildren(...boutons.map((b) => b.bouton));
    const courant = boutons.find((b) => b.option.valeur === actif);
    if (courant) requestAnimationFrame(() => centrer(courant.bouton, false));
  }

  /* Pastilles grises affichées pendant que la liste des danses se charge (évite que la rangée « saute »). */
  function filtresEnAttente(conteneur, nombre) {
    conteneur.classList.add('filtres-defilant');
    conteneur.replaceChildren(...Array.from({ length: nombre }, () => el('span', { class: 'filtre squelette', 'aria-hidden': 'true' })));
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
      logout.addEventListener('click', async () => {
        try { await Api.post('/auth/logout'); }
        finally { Api.reset(); window.location.href = '/'; }
      });
      actions.append(logout);
    } else {
      actions.append(el('a', { href: '/connexion.html', class: 'connexion-lien' }, 'Se connecter'));
    }
    host.replaceChildren(brand, actions);
    document.body.append(nav);
    document.body.classList.add('avec-navigation-basse');
    return session;
  }

  return { ready: renderHeader(), el, showErrors, hideErrors, filtres, filtresEnAttente };
})();
