'use strict';

function destinationApresConnexion(user) {
  if (user && user.role === 'admin') return '/admin.html';
  if (user && user.role === 'createur') return '/dashboard.html';
  return '/';
}

Layout.ready.then((session) => {
  if (session.user) {
    window.location.replace(destinationApresConnexion(session.user));
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
    await Api.post('/auth/login', {
      email: formulaire.elements.email.value,
      mot_de_passe: formulaire.elements.mot_de_passe.value,
    });
    Api.reset();
    const session = await Api.session();
    window.location.href = destinationApresConnexion(session.user);
  } catch (error) {
    Layout.showErrors(alerte, error.erreurs);
    bouton.disabled = false;
  }
});
