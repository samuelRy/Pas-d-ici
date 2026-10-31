# Contrat de l'API

Le backend ne répond qu'en JSON. L'interface (dossier `frontend/`) ne dépend que de ce document :
tant que ce contrat ne change pas, le design peut être modifié librement.

## Forme des réponses
- Succès : `{ "ok": true, "data": ... }`
- Échec : `{ "ok": false, "erreurs": ["message", ...] }` (messages en français, affichables tels quels)

## Sécurité côté client
1. Appeler `GET /api/session` : la réponse contient `csrf`.
2. Pour chaque requête qui modifie quelque chose (POST), envoyer :
   - l'en-tête `X-CSRF-Token: <csrf>`
   - l'en-tête `Content-Type: application/json` et un corps JSON (`{}` s'il n'y a rien à envoyer)
3. La session est portée par un cookie `HttpOnly` : le navigateur l'envoie tout seul (même origine).

## Routes

| Méthode | Chemin | Corps | Réponse (`data`) |
|---|---|---|---|
| GET | `/api/session` | — | `{ user: Utilisateur \| null, csrf: string }` |
| POST | `/api/auth/register` | `{ nom, email, mot_de_passe, role }` (`role` : `spectateur` ou `createur`) | `{ user }` — statut 201 |
| POST | `/api/auth/login` | `{ email, mot_de_passe }` | `{ user }` |
| POST | `/api/auth/logout` | `{}` | `null` |
| GET | `/api/danses` | — | `[ { id, nom, region_origine } ]` |
| POST | `/api/contenus` | **multipart/form-data** (voir ci-dessous) | `{ id, statut }` — statut 201, `statut` vaut `en_validation` |
| GET | `/api/mes-contenus` | — | `[ Contenu ]` — contenus du créateur connecté, du plus récent au plus ancien |

### Routes publiques de consultation
Accessibles sans connexion.

| Méthode | Chemin | Réponse (`data`) |
|---|---|---|
| GET | `/api/contenus?danse_id=&type=&q=&limite=&decalage=` | `{ items: [ ContenuPublic ], total, limite, decalage }` — contenus `publie`, du plus récent au plus ancien |
| GET | `/api/contenus/detail?id=` | `Fiche` |
| GET | `/api/contenus/media?id=` | le fichier lui-même (vidéo), avec prise en charge de `Range` |

- Filtres facultatifs : `danse_id` (entier), `type` (`video`, `photo`, `texte`), `q` (texte cherché dans le titre et le nom du créateur,
  100 caractères au plus). `limite` : 1 à 50, 20 par défaut. `decalage` : à partir de 0, pour la pagination.
- `ContenuPublic` : `{ id, type, titre, danse, createur, mode_monetisation, prix, publie_le }`.
- `Fiche` : `{ id, createur_id, type, titre, danse, region_origine, createur, contexte_culturel, mode_monetisation, prix, statut, publie_le, peut_lire }`.
  `peut_lire` indique si l'utilisateur courant a le droit de lire le fichier.
- Un contenu non publié n'est visible que de son créateur et des administrateurs ; pour tout autre visiteur, la réponse est 404
  (l'existence du contenu n'est pas révélée).
- **Règle de lecture du fichier** : l'administrateur, le créateur du contenu, et tout le monde si le contenu est publié **et** gratuit.
  Un contenu payant renvoie 403 tant que l'achat ou l'abonnement n'existe pas. Cette règle est écrite à un seul endroit
  (`Contenu::peutLire`), où l'achat s'ajoutera.
- Les vues d'un contenu sont comptées une fois par session, jamais pour son créateur ni pour un administrateur.

### Routes d'administration
Réservées aux comptes `admin` (401 si non connecté, 403 sinon). Les POST suivent les mêmes règles que les autres (JSON + `X-CSRF-Token`).

| Méthode | Chemin | Corps | Réponse (`data`) |
|---|---|---|---|
| GET | `/api/admin/contenus` | — | `[ ContenuAValider ]` — contenus au statut `en_validation`, du plus ancien au plus récent |
| POST | `/api/admin/contenus/decision` | `{ id, decision, motif? }` | `null` |
| GET | `/api/admin/createurs` | — | `[ CreateurEnAttente ]` |
| POST | `/api/admin/createurs/decision` | `{ id, decision }` | `null` |
| GET | `/api/admin/media?id=<id du contenu>` | — | le fichier lui-même (vidéo), avec prise en charge de `Range` |

- `decision` pour un contenu : `approuver` (devient `publie`), `refuser` (devient `refuse`, **`motif` obligatoire**, 255 caractères au plus,
  visible par le créateur), `remettre_en_attente` (annule une décision, impossible si le contenu a déjà été acheté : 409).
- `decision` pour un créateur : `valider` (le compte devient `actif`) ou `refuser` (le compte devient `suspendu` et ne peut plus se connecter).
- Décider sur un élément déjà traité renvoie 409.
- `ContenuAValider` : `{ id, type, titre, danse, createur, contexte_culturel, mode_monetisation, prix, cree_le }`.
- `CreateurEnAttente` : `{ id, nom, email, region_origine, justificatif, cree_le }`.

### POST /api/contenus (publier une vidéo)
Réservé aux comptes `createur` dont le `statut_compte` est `actif` (403 sinon). Corps en `multipart/form-data`
(et non en JSON), avec l'en-tête `X-CSRF-Token` comme pour les autres POST :

| Champ | Obligatoire | Règle |
|---|---|---|
| `fichier` | oui | vidéo MP4 ou WebM, taille maximale 200 Mo par défaut (réglable) |
| `titre` | oui | 3 à 200 caractères |
| `danse_id` | oui | identifiant d'une danse de `GET /api/danses` |
| `mode_monetisation` | oui | `gratuit`, `unite` ou `abonnement` |
| `prix` | si `unite` | entier positif en FCFA |
| `contexte_culturel` | non | 5000 caractères au plus |
| `type` | non | `video` (seul type disponible pour le moment) |

La vidéo est créée avec le statut `en_validation` : elle n'est visible de personne d'autre tant qu'un
administrateur ne l'a pas approuvée. Le fichier est stocké hors du web. Seul un administrateur peut le lire pour l'instant (`/api/admin/media`) ;
la lecture par les spectateurs, soumise au paiement, n'existe pas encore.

`Utilisateur` : `{ id, nom, email, role, statut_compte }` — `role` : `spectateur`, `createur`, `admin` ;
`statut_compte` : `actif`, `en_attente` (créateur pas encore validé), `suspendu`.

`Contenu` : `{ id, type, titre, danse, mode_monetisation, prix, statut, motif_refus, nb_vues, cree_le }` — `type` : `video`, `photo`, `texte` ;
`prix` : entier en FCFA ou `null` ; `statut` : `brouillon`, `en_validation`, `publie`, `refuse`.

## Abonnements et paiement simulé

| Méthode | Chemin | Corps | Réponse (`data`) |
|---|---|---|---|
| GET | `/api/paiement/offres?createur_id=<id>` | — | `{ createur, formules, moyens_paiement, abonne_jusqua }` (connexion requise) |
| POST | `/api/paiement/abonnement` | `{ createur_id, formule, moyen_paiement, numero }` | `{ abonnement_id, reference, formule, montant, commission, part_createur, fin }` — statut 201 |

Le paiement est **simulé** : aucun prestataire n'est appelé et aucun argent réel n'est débité. Le numéro saisi sert uniquement à la validation et n'est ni enregistré ni journalisé. Les tarifs et le taux de commission sont lus depuis `parametres`. Une souscription crée l'abonnement et la transaction, puis crédite le solde disponible du créateur dans une transaction SQL atomique. Une souscription active existante est refusée (409). Les achats de contenus à l'unité ne sont pas encore implémentés.

## Codes de statut
200/201 succès · 400 requête invalide · 401 identifiants incorrects ou connexion requise · 403 jeton CSRF invalide ·
404 route inconnue · 405 méthode non autorisée · 409 adresse e-mail déjà utilisée · 413 corps ou fichier trop gros ·
415 type de contenu ou de fichier non accepté · 422 données invalides · 500 erreur du serveur.
(401 : connexion requise · 403 : compte non autorisé à faire cette action.)

## Règles à respecter pour faire évoluer l'API
- Ajouter un champ ou une route ne casse rien. Renommer ou supprimer un champ casse l'interface : à éviter,
  ou à faire en même temps des deux côtés.
- Aucune route ne renvoie de HTML ni le mot de passe haché.

## Tableau de bord et retraits

- `GET /api/dashboard` — créateur connecté : solde disponible, vues, contenus, et historique des retraits.
- `GET /api/admin/retraits` — administrateur : demandes de retrait en attente.
- `POST /api/admin/retraits/decision` — administrateur, JSON `{ "id": 1, "decision": "approuver" }` ou `{ "id": 1, "decision": "refuser", "motif": "..." }`.

Une demande réserve le montant sur le solde dès sa création. L'approbation marque la demande comme payée après confirmation de l'administrateur ; le refus exige un motif et recrédite automatiquement le montant réservé. Le système reste simulé et n'effectue aucun transfert Mobile Money réel.

## Publications

`POST /api/contenus` accepte les types `video`, `photo` et `texte`. Les galeries photo acceptent 1 à 20 images dans `fichiers[]` et un champ `photos_gratuites` qui indique combien des premières images peuvent être consultées gratuitement. Les articles utilisent `chapeau`, `corps` et `sources`.
