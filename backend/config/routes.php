<?php
declare(strict_types=1);

// [méthode, chemin (sans le préfixe /api), 'Contrôleur@action']
// Le contrat complet est décrit dans backend/API.md.
return [
    ['GET',  '/session',        'AuthController@session'],
    ['POST', '/auth/register',  'AuthController@register'],
    ['POST', '/auth/login',     'AuthController@login'],
    ['POST', '/auth/logout',    'AuthController@logout'],
    ['GET',  '/danses',         'DanseController@index'],
    ['GET',  '/contenus',         'ContenuController@liste'],
    ['GET',  '/contenus/detail',  'ContenuController@detail'],
    ['GET',  '/contenus/media',   'ContenuController@media'],
    ['POST', '/contenus',         'ContenuController@creer'],
    ['GET',  '/mes-contenus',   'ContenuController@mesContenus'],
    ['GET',  '/paiement/offres', 'PaiementController@offres'],
    ['POST', '/paiement/abonnement', 'PaiementController@abonner'],
    ['POST', '/paiement/contenu', 'PaiementController@unite'],
    ['POST', '/paiement/retrait', 'PaiementController@retrait'],

    // Administration (réservée aux comptes admin)
    ['GET',  '/admin/contenus',           'AdminController@contenus'],
    ['POST', '/admin/contenus/decision',  'AdminController@decisionContenu'],
    ['GET',  '/admin/createurs',          'AdminController@createurs'],
    ['POST', '/admin/createurs/decision', 'AdminController@decisionCreateur'],
    ['GET',  '/admin/media',              'AdminController@media'],
    ['GET',  '/admin/retraits',           'AdminController@retraits'],
    ['POST', '/admin/retraits/decision',  'AdminController@decisionRetrait'],
    ['GET',  '/dashboard',               'DashboardController@index'],
];
