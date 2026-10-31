'use strict';

/*
 * Client de l'API : c'est le SEUL fichier qui sait comment parler au backend.
 * Les pages utilisent uniquement Api.get, Api.post et Api.session.
 * Contrat détaillé : backend/API.md
 */
const Api = (() => {
  let sessionPromise = null;

  function apiError(status, erreurs) {
    const error = new Error(erreurs[0]);
    error.status = status;
    error.erreurs = erreurs;
    return error;
  }

  async function request(method, path, body) {
    const options = { method, credentials: 'same-origin', headers: { Accept: 'application/json' } };

    if (method !== 'GET') {
      const { csrf } = await session();
      options.headers['X-CSRF-Token'] = csrf;
      if (body instanceof FormData) {
        // Envoi de fichiers : le navigateur fixe lui-même le Content-Type (avec la frontière du formulaire).
        options.body = body;
      } else {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body ?? {});
      }
    }

    let response;
    try {
      response = await fetch('/api' + path, options);
    } catch {
      throw apiError(0, ['Connexion impossible au serveur. Vérifiez votre connexion.']);
    }

    let json = null;
    try {
      json = await response.json();
    } catch {
      /* corps vide ou non JSON */
    }

    if (!response.ok || !json || json.ok !== true) {
      throw apiError(response.status, (json && json.erreurs) || ['Une erreur est surddvenue.']);
    }
    return json.data;
  }

  /** Utilisateur connecté (ou null) et jeton CSRF. Mis en mémoire pour éviter les appels répétés. */
  function session() {
    if (!sessionPromise) {
      sessionPromise = request('GET', '/session').catch((error) => {
        sessionPromise = null;
        throw error;
      });
    }
    return sessionPromise;
  }

  /** À appeler après une connexion ou une déconnexion. */
  function reset() {
    sessionPromise = null;
  }

  return {
    get: (path) => request('GET', path),
    post: (path, body) => request('POST', path, body),
    session,
    reset,
  };
})();
