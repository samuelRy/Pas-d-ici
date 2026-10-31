'use strict';
const formulaire = document.getElementById('formulaire');
const alerte = document.getElementById('alerte');
const succes = document.getElementById('succes');
const blocage = document.getElementById('blocage');
const champPrix = document.getElementById('champ-prix');
const listeDanses = document.getElementById('danse_id');
const typeContenu = document.getElementById('type');
const fichier = document.getElementById('fichier');
const galerie = document.getElementById('galerie');
const zoneFichier = document.getElementById('zone-fichier');
const zoneGalerie = document.getElementById('zone-galerie');
const apercusPhotos = document.getElementById('apercus-photos');
const aideCouverture = document.getElementById('aide-couverture');
const zoneArticle = document.getElementById('zone-article');
const chapeau = document.getElementById('chapeau');
const corps = document.getElementById('corps');
const sources = document.getElementById('sources');
const contexte = document.getElementById('contexte_culturel');
let fichiersPhotos = [];
const el = Layout.el;

function afficherBlocage(message) { blocage.textContent = message; blocage.hidden = false; }
function majPrix() {
  const gratuit = formulaire.elements.mode_monetisation.value === 'gratuit';
  champPrix.hidden = gratuit || formulaire.elements.mode_monetisation.value !== 'unite';
  document.getElementById('prix').required = !champPrix.hidden;
}
function majTempsLecture() {
  const words = corps.value.trim().split(/\s+/).filter(Boolean).length;
  document.getElementById('temps-lecture').textContent = Math.max(1, Math.ceil(words / 200)) + ' min';
}
function majType() {
  const type = typeContenu.value;
  const video = type === 'video'; const photo = type === 'photo'; const article = type === 'texte';
  document.querySelectorAll('.segment').forEach(b => { const active = b.dataset.type === type; b.classList.toggle('actif', active); b.setAttribute('aria-pressed', String(active)); });
  document.getElementById('titre-page').textContent = video ? 'Publier une vidéo' : photo ? 'Publier des photos' : 'Publier un article';
  document.title = document.getElementById('titre-page').textContent + ' — Danses du Bénin';
  zoneFichier.hidden = !video; zoneGalerie.hidden = !photo; zoneArticle.hidden = !article;
  document.getElementById('zone-contexte').hidden = article;
  fichier.disabled = !video; fichier.required = video; fichier.name = 'fichier';
  galerie.disabled = !photo; galerie.required = photo;
  document.getElementById('label-fichier').textContent = 'Déposer le fichier vidéo';
  document.getElementById('aide-fichier').textContent = 'Vidéo MP4 ou WebM, maximum 200 Mo.';
  chapeau.required = article; corps.required = article;
  document.getElementById('note-article').hidden = !article;
  majPrix();
}
function renderPhotos() {
  apercusPhotos.replaceChildren();
  const files = Array.from(galerie.files || []);
  fichiersPhotos = files;
  if (!files.length) { apercusPhotos.hidden = true; aideCouverture.hidden = true; return; }
  apercusPhotos.hidden = false; aideCouverture.hidden = false;
  files.forEach((file, index) => {
    const url = URL.createObjectURL(file);
    const img = el('img', { src: url, alt: 'Aperçu de ' + file.name });
    img.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
    const cover = el('button', { type: 'button', class: 'photo-couverture' + (index === 0 ? ' selectionnee' : ''), title: index === 0 ? 'Couverture actuelle' : 'Définir comme couverture' }, index === 0 ? 'Couverture' : 'Choisir');
    cover.addEventListener('click', () => reorderPhoto(index, 0));
    const controls = el('div', { class: 'photo-ordre' });
    if (index > 0) { const up = el('button', { type: 'button', title: 'Déplacer vers la gauche' }, '←'); up.addEventListener('click', () => reorderPhoto(index, index - 1)); controls.append(up); }
    if (index < files.length - 1) { const down = el('button', { type: 'button', title: 'Déplacer vers la droite' }, '→'); down.addEventListener('click', () => reorderPhoto(index, index + 1)); controls.append(down); }
    apercusPhotos.append(el('div', { class: 'apercu-photo' }, img, cover, controls));
  });
}
function reorderPhoto(from, to) {
  const list = Array.from(galerie.files || []); const [item] = list.splice(from, 1); list.splice(to, 0, item);
  const transfer = new DataTransfer(); list.forEach(f => transfer.items.add(f)); galerie.files = transfer.files; renderPhotos();
}
function insererFormat(format) {
  const start = corps.selectionStart, end = corps.selectionEnd, value = corps.value;
  const selected = value.slice(start, end) || (format === 'link' ? 'texte du lien' : 'texte');
  let before = '', after = '';
  if (format === 'bold') { before = '**'; after = '**'; }
  if (format === 'italic') { before = '*'; after = '*'; }
  if (format === 'quote') { before = '> '; after = ''; }
  if (format === 'link') { before = '['; after = '](https://)'; }
  corps.setRangeText(before + selected + after, start, end, 'select'); corps.focus(); majTempsLecture();
}

Layout.ready.then(async session => {
  const user = session.user;
  if (!user) { window.location.href = '/connexion.html'; return; }
  if (user.role !== 'createur') { afficherBlocage('Seuls les comptes créateurs peuvent publier.'); return; }
  if (user.statut_compte !== 'actif') { afficherBlocage('Votre compte créateur est en cours de vérification. Vous pourrez publier dès sa validation par l’équipe.'); return; }
  try {
    const danses = await Api.get('/danses');
    listeDanses.append(...danses.map(d => el('option', { value: String(d.id) }, d.nom)));
  } catch (error) { Layout.showErrors(alerte, error.erreurs || [error.message]); return; }
  formulaire.hidden = false; majType(); majTempsLecture();
});
document.querySelectorAll('.segment').forEach(b => b.addEventListener('click', () => { typeContenu.value = b.dataset.type; typeContenu.dispatchEvent(new Event('change')); }));
typeContenu.addEventListener('change', majType);
formulaire.elements.mode_monetisation.forEach(r => r.addEventListener('change', majPrix));
galerie.addEventListener('change', () => {
  if (galerie.files.length > 20) { Layout.showErrors(alerte, ['Choisissez 20 photos maximum.']); galerie.value = ''; }
  else renderPhotos();
});
corps.addEventListener('input', majTempsLecture);
document.querySelectorAll('[data-format]').forEach(b => b.addEventListener('click', () => insererFormat(b.dataset.format)));

formulaire.addEventListener('submit', async event => {
  event.preventDefault(); Layout.hideErrors(alerte); succes.hidden = true;
  if (typeContenu.value === 'texte' && corps.value.trim().length < 50) { Layout.showErrors(alerte, ['L’article doit contenir au moins 50 caractères.']); return; }
  if (typeContenu.value === 'photo' && (!galerie.files.length || galerie.files.length > 20)) { Layout.showErrors(alerte, ['Choisissez entre 1 et 20 photos.']); return; }
  if (typeContenu.value === 'photo' && Number(document.getElementById('photos_gratuites').value) > galerie.files.length) { Layout.showErrors(alerte, ['Le nombre de photos visibles gratuitement ne peut pas dépasser le nombre de photos déposées.']); return; }
  const bouton = formulaire.querySelector('button[type="submit"]'); const original = bouton.textContent; bouton.disabled = true; bouton.textContent = 'Envoi en cours…';
  const data = new FormData(formulaire);
  if (typeContenu.value === 'photo') { data.delete('fichier'); data.delete('fichiers[]'); Array.from(galerie.files).forEach(file => data.append('fichiers[]', file)); }
  if (typeContenu.value === 'video') { data.delete('fichiers[]'); }
  if (typeContenu.value !== 'texte') { data.delete('chapeau'); data.delete('corps'); data.delete('sources'); }
  if (typeContenu.value === 'texte') { data.delete('fichier'); data.delete('fichiers[]'); data.delete('contexte_culturel'); }
  if (formulaire.elements.mode_monetisation.value !== 'unite') data.delete('prix');
  try {
    await Api.post('/contenus', data); formulaire.reset(); fichiersPhotos = []; apercusPhotos.replaceChildren(); apercusPhotos.hidden = true; aideCouverture.hidden = true; majType(); majTempsLecture(); succes.hidden = false; succes.scrollIntoView({ behavior: 'smooth', block: 'start' });
  } catch (error) { Layout.showErrors(alerte, error.erreurs || [error.message]); alerte.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  finally { bouton.disabled = false; bouton.textContent = original; }
});
