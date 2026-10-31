# Danses du Bénin — MVP (première tranche)

Plateforme de valorisation et de monétisation des danses traditionnelles béninoises.
Cette version comprend l'inscription et la connexion, les vidéos, les galeries photo, les articles culturels, la monétisation simulée (achat à l'unité et abonnement), le tableau de bord créateur, la modération des contenus et créateurs, et la validation administrative des demandes de retrait.

## Deux parties indépendantes
- `backend/` : toute la logique PHP (comptes, base de données, sécurité). Il ne répond qu'en JSON.
  Le contrat est décrit dans `backend/API.md`.
- `frontend/` : les pages HTML, le CSS et le JavaScript natif. Il ne fait qu'appeler l'API.
  Seul `frontend/js/api.js` sait comment parler au backend.

**Modifier le design** (HTML, CSS, JavaScript) ne demande aucune modification du backend tant que le
contrat de l'API ne change pas. Inversement, le backend peut évoluer sans toucher aux pages.

## Prérequis
- PHP 8.1 ou plus récent, avec l'extension `pdo_mysql`
- MySQL 8.0.16+ ou MariaDB 10.5+

## Installation locale
1. Créer la base : `mysql -u root -p < database/schema.sql`
2. Variables d'environnement si besoin (valeurs par défaut entre parenthèses) :
   `DB_HOST` (127.0.0.1), `DB_PORT` (3306), `DB_NAME` (danses_benin), `DB_USER` (root), `DB_PASS` (vide),
   `APP_DEBUG` (false ; `true` uniquement en développement).
3. Depuis ce dossier :
   `php -d upload_max_filesize=210M -d post_max_size=215M -S localhost:8000 dev-server.php`
   (les deux réglages `-d` autorisent l'envoi de vidéos jusqu'à 200 Mo ; par défaut PHP s'arrête à 2 Mo)
4. Ouvrir http://localhost:8000

`dev-server.php` ne sert qu'en local : il envoie `/api/...` vers le backend et le reste vers le frontend.

## Mise en ligne (Apache, exemple)
- La racine du site (`DocumentRoot`) pointe vers `frontend/`.
- L'adresse `/api` pointe vers `backend/public/` :
  `Alias /api /chemin/vers/danses-benin/backend/public`
  puis, pour ce dossier, `AllowOverride All` et `Require all granted`.
- Le reste de `backend/` (code, configuration, `storage/`) ne doit pas être accessible depuis le web.
- Activer HTTPS (le cookie de session passe alors automatiquement en `Secure`).

## Tester le parcours complet : publication puis modération
1. Créer deux comptes par la page d'inscription : un « Créateur » et un « Spectateur » (qui servira d'administrateur).
2. Donner le rôle administrateur au second, en ligne de commande :
   `php backend/bin/promouvoir-admin.php adresse-du-second-compte@exemple.com`
3. Se connecter avec ce compte : le lien « Administration » apparaît. Onglet « Créateurs à valider » : valider le créateur.
4. Se connecter avec le compte créateur : ouvrir « Publier » pour envoyer une vidéo MP4/WebM, une galerie de 1 à 20 photos JPG/PNG/WebP, ou un article culturel. Les contenus apparaissent en attente de validation dans le tableau de bord.
5. Côté administrateur, onglet « Contenus à valider » : regarder la vidéo, puis l'approuver, ou la refuser avec un motif.
6. Côté créateur, le statut change dans « Mon tableau de bord » ; en cas de refus, le motif s'affiche dans « Mes contenus ».
7. Les contenus approuvés apparaissent dans Explorer. Les paiements sont simulés : l'achat à l'unité déverrouille le contenu concerné et l'abonnement déverrouille les contenus payants du créateur pendant la durée choisie.

En production, régler `upload_max_filesize` et `post_max_size` dans `php.ini`, et la taille maximale
acceptée par l'application avec la variable d'environnement `UPLOAD_VIDEO_MAX_MO` (200 par défaut).
Les fichiers sont stockés hors du web dans `backend/storage/uploads/videos/` et `backend/storage/uploads/images/`, et sont servis par des routes qui vérifient les droits d'accès.

## Sécurité déjà en place
Fichiers déposés renommés et vérifiés sur leur contenu (pas sur l'extension), stockés hors du web,
Mots de passe hachés, requêtes préparées, jeton CSRF sur chaque requête qui modifie des données,
identifiant de session renouvelé à la connexion, cookie `HttpOnly` + `SameSite`, données du serveur
insérées dans les pages sans `innerHTML`, en-têtes de sécurité (dont une politique CSP stricte).

## Limites actuelles et prochaines étapes
Intégration d'un véritable prestataire de paiement et transfert réel des retraits. Les paiements et retraits restent simulés dans cette version.


## Paiement simulé et migration

Le fichier `database/schema.sql` contient désormais les tables et paramètres nécessaires au paiement simulé. Pour une base neuve, réimportez ce schéma. Pour une base déjà installée avec l’ancien schéma, sauvegardez d’abord la base puis exécutez **une seule fois** `database/migrations/2026-10-27_paiement_simule.sql` :

```sh
mysql -u root -p danses_benin < database/migrations/2026-10-27_paiement_simule.sql
```

La page `abonnement.html?id=<id_du_contenu>` est ouverte depuis une fiche de contenu en mode abonnement. Le parcours exige une session spectateur active. Les montants et la commission sont configurables dans la table `parametres`.


## Tableau de bord, publications et retraits

Le tableau de bord créateur est accessible à `/dashboard.html`. Il affiche le solde disponible, les vues, les contenus publiés/en attente et l'historique des demandes de retrait. Les galeries photo acceptent 1 à 20 images JPG/PNG/WebP de 10 Mo maximum chacune ; les articles acceptent un chapeau, un corps, des sources et une estimation du temps de lecture.

Pour une base déjà installée, sauvegardez-la puis exécutez une seule fois la migration des retraits :

```sh
mysql -u root -p danses_benin < database/migrations/2026-10-29_retraits_validation_admin.sql
```

Une demande de retrait réserve le montant sur le solde. L'administrateur peut confirmer qu'il a effectué le paiement ou refuser avec un motif ; un refus recrédite automatiquement le solde réservé. En mode simulation, aucun transfert Mobile Money réel n'est effectué.
