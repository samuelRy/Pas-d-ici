'use strict';
const el = Layout.el;
const alerte = document.getElementById('alerte');
const confirmation = document.getElementById('confirmation');
const blocage = document.getElementById('blocage');
const espace = document.getElementById('espace');
const panels = { contenus: document.getElementById('panneau-contenus'), createurs: document.getElementById('panneau-createurs'), retraits: document.getElementById('panneau-retraits') };
const bodies = { contenus: document.getElementById('liste-contenus'), createurs: document.getElementById('liste-createurs'), retraits: document.getElementById('liste-retraits') };
const empty = { contenus: document.getElementById('vide-contenus'), createurs: document.getElementById('vide-createurs'), retraits: document.getElementById('vide-retraits') };
let treated = 0;
function money(n) { return new Intl.NumberFormat('fr-FR').format(Number(n || 0)) + ' FCFA'; }
function notify(message) { confirmation.textContent = message; confirmation.hidden = false; confirmation.scrollIntoView({behavior:'smooth',block:'nearest'}); }
function setTab(name) {
  Object.entries(panels).forEach(([key, panel]) => { panel.hidden = key !== name; });
  document.querySelectorAll('[data-admin-tab]').forEach(b => b.classList.toggle('actif', b.dataset.adminTab === name));
}
function row(...cells) { const tr = el('tr'); cells.forEach(c => tr.append(el('td', {}, c))); return tr; }
function button(label, cls, fn) { const b = el('button', {type:'button', class:'bouton ' + (cls || '')}, label); b.addEventListener('click', fn); return b; }
async function action(buttons, url, data, message, onSuccess) {
  Layout.hideErrors(alerte); buttons.forEach(b => b.disabled = true);
  try { await Api.post(url, data); treated++; document.getElementById('traites-session').textContent = String(treated); notify(message); onSuccess(); }
  catch (e) { Layout.showErrors(alerte, e.erreurs || [e.message]); buttons.forEach(b => b.disabled = false); }
}
function contenuRow(c) {
  const type = {video:'Vidéo',photo:'Photos',texte:'Article'}[c.type] || c.type;
  const title = el('div', {class:'admin-title-cell'}, el('strong',{},c.titre), el('small',{},c.mode_monetisation === 'unite' ? money(c.prix) : c.mode_monetisation === 'abonnement' ? 'Abonnement' : 'Gratuit'));
  const actions = el('div',{class:'admin-actions'});
  const preview = button(c.type === 'video' ? 'Aperçu' : c.type === 'photo' ? 'Photo' : 'Lire', 'bouton-discret', () => {
    const existing = tr.nextElementSibling;
    if (existing && existing.classList.contains('preview-row')) { existing.remove(); return; }
    const pr = el('tr',{class:'preview-row'}); const td=el('td',{colspan:'5'});
    if(c.type==='video') td.append(el('video',{controls:'',preload:'metadata',src:'/api/admin/media?id='+encodeURIComponent(c.id)}));
    else if(c.type==='photo') td.append(el('img',{class:'image-contenu',alt:c.titre,src:'/api/admin/media?id='+encodeURIComponent(c.id)}));
    else { td.append(el('p',{class:'chapeau'},c.chapeau||'Résumé non renseigné.'),el('div',{class:'corps-article'},c.article_corps||'Article vide.')); if(c.sources) td.append(el('small',{},'Sources : '+c.sources)); }
    pr.append(td); tr.after(pr);
  });
  const approve = button('Approuver','',()=>action([approve, reject, preview],'/admin/contenus/decision',{id:c.id,decision:'approuver'},'« '+c.titre+' » a été approuvé.',()=>{if(tr.nextElementSibling && tr.nextElementSibling.classList.contains('preview-row')) tr.nextElementSibling.remove();tr.remove();refreshCounts('contenus');}));
  const reject = button('Refuser','bouton-contour-refus',()=>{const motif=window.prompt('Motif du refus (visible par le créateur) :');if(motif===null)return;if(!motif.trim()){Layout.showErrors(alerte,['Le motif du refus est obligatoire.']);return;}action([approve,reject,preview],'/admin/contenus/decision',{id:c.id,decision:'refuser',motif:motif.trim()},'« '+c.titre+' » a été refusé.',()=>{if(tr.nextElementSibling && tr.nextElementSibling.classList.contains('preview-row')) tr.nextElementSibling.remove();tr.remove();refreshCounts('contenus');});});
  actions.append(preview,approve,reject);
  const tr = row(type,title,c.createur,c.danse,actions); return tr;
}
function createurRow(c) {
  const details = el('div',{class:'ligne-texte'},el('strong',{},c.nom),el('small',{},c.email+' · inscrit le '+String(c.cree_le).slice(0,10)),c.region_origine?el('small',{},c.region_origine):'');
  const valider=button('Valider','',()=>action([valider,refuser],'/admin/createurs/decision',{id:c.id,decision:'valider'},'Le compte de '+c.nom+' a été validé.',()=>{tr.remove();refreshCounts('createurs');}));
  const refuser=button('Refuser','bouton-contour-refus',()=>{if(!confirm('Refuser le compte de '+c.nom+' ?'))return;action([valider,refuser],'/admin/createurs/decision',{id:c.id,decision:'refuser'},'Le compte de '+c.nom+' a été refusé.',()=>{tr.remove();refreshCounts('createurs');});});
  const tr=el('li',{class:'carte-admin'},details,el('div',{class:'rangee-actions'},valider,refuser)); return tr;
}
function retraitRow(r) {
  const who=el('div',{class:'ligne-texte'},el('strong',{},r.createur),el('small',{},r.email));
  const contact=el('div',{class:'ligne-texte'},r.operateur==='mtn_momo'?'MTN MoMo':'Moov Money',el('small',{},r.numero_telephone));
  const actions=el('div',{class:'admin-actions'});
  const approve=button('Confirmer le paiement','',()=>{if(!confirm('Confirmer que le retrait de '+money(r.montant)+' a bien été payé au créateur ?'))return;action([approve,reject],'/admin/retraits/decision',{id:r.id,decision:'approuver'},'Retrait confirmé comme payé. Effectuez le transfert réel séparément si vous quittez le mode simulation.',()=>{tr.remove();refreshCounts('retraits');});});
  const reject=button('Refuser','bouton-contour-refus',()=>{const motif=prompt('Motif du refus :');if(motif===null)return;if(!motif.trim()){Layout.showErrors(alerte,['Le motif du refus est obligatoire.']);return;}action([approve,reject],'/admin/retraits/decision',{id:r.id,decision:'refuser',motif:motif.trim()},'Retrait refusé : le solde a été recrédité.',()=>{tr.remove();refreshCounts('retraits');});});
  actions.append(approve,reject);
  const tr=row(who,money(r.montant),contact,String(r.demande_le).slice(0,16),actions); return tr;
}
function refreshCounts(name) {
  const count = name === 'contenus' || name === 'retraits' ? bodies[name].querySelectorAll('tr:not(.preview-row)').length : bodies[name].children.length;
  const nav=document.getElementById('nav-count-'+name); if(nav)nav.textContent=String(count);
  const big=document.getElementById('compte-contenus-grand'); if(name==='contenus'&&big)big.textContent=String(count);
  empty[name].hidden=count>0;
}
document.querySelectorAll('[data-admin-tab]').forEach(b=>b.addEventListener('click',()=>setTab(b.dataset.adminTab)));
Layout.ready.then(async session=>{
  if(!session.user){location.href='/connexion.html';return;}
  if(session.user.role!=='admin'){blocage.textContent='Cette page est réservée aux administrateurs.';blocage.hidden=false;return;}
  try {
    const [contenus,createurs,retraits,publies]=await Promise.all([Api.get('/admin/contenus'),Api.get('/admin/createurs'),Api.get('/admin/retraits'),Api.get('/contenus?limite=1')]);
    bodies.contenus.replaceChildren(...contenus.map(contenuRow));
    bodies.createurs.replaceChildren(...createurs.map(createurRow));
    bodies.retraits.replaceChildren(...retraits.map(retraitRow));
    document.getElementById('contenus-publies').textContent=String(publies.total ?? '—');
    Object.keys(bodies).forEach(refreshCounts);
    document.getElementById('espace').hidden=false;
  } catch(e) { Layout.showErrors(alerte,e.erreurs||[e.message]); }
});
