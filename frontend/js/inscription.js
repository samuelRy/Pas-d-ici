'use strict';

Layout.ready.then((session) => {
  if (session.user) {
    window.location.href = '/';
  }
});

const formulaire = document.getElementById('formulaire');
const alerte = document.getElementById('alerte');

formulaire.addEventListener('submit', async (event) => {
  event.preventDefault();
  Layout.hideErrors(alerte);

  const bouton = formulaire.querySelector('button[type="submit"]');
  bouton.disabled = true;

  try {
    await Api.post('/auth/register', {
      nom: formulaire.elements.nom.value,
      email: formulaire.elements.email.value,
      mot_de_passe: formulaire.elements.mot_de_passe.value,
      role: formulaire.elements.role.value,
    });
    Api.reset();
    window.location.href = '/';
  } catch (error) {
    Layout.showErrors(alerte, error.erreurs);
    bouton.disabled = false;
  }
});
