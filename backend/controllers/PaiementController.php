<?php
declare(strict_types=1);

/** Abonnement à un créateur, avec paiement simulé (voir models/Paiement.php). */
final class PaiementController
{
    private const LIBELLES_FORMULES = ['mensuel' => 'Mensuel', 'annuel' => 'Annuel'];
    private const LIBELLES_MOYENS = ['mtn_momo' => 'MTN MoMo', 'moov_money' => 'Moov Money', 'carte' => 'Carte'];

    /** GET /api/paiement/offres?createur_id= : ce qu'il faut pour afficher la page d'abonnement. */
    public function offres(): void
    {
        $user = Auth::requireUser();

        $createurId = filter_var($_GET['createur_id'] ?? null, FILTER_VALIDATE_INT);
        $createur = $createurId === false ? null : Createur::profilPublic($createurId);
        if ($createur === null) {
            throw new ValidationException(['Créateur introuvable.'], 404);
        }

        $tarifs = Paiement::tarifs();
        $formules = [];
        foreach (array_keys(Paiement::FORMULES) as $formule) {
            $formules[] = ['formule' => $formule, 'libelle' => self::LIBELLES_FORMULES[$formule], 'prix' => $tarifs[$formule]];
        }
        $moyens = [];
        foreach (Paiement::MOYENS as $moyen) {
            $moyens[] = ['valeur' => $moyen, 'libelle' => self::LIBELLES_MOYENS[$moyen]];
        }

        $actif = Paiement::abonnementActif((int) $user['id'], $createurId);

        Response::ok([
            'createur'        => $createur,
            'formules'        => $formules,
            'moyens_paiement' => $moyens,
            'abonne_jusqua'   => $actif['fin'] ?? null,
        ]);
    }

    /** POST /api/paiement/abonnement  { createur_id, formule, moyen_paiement, numero } */
    public function abonner(): void
    {
        Csrf::check();
        $user = Auth::requireUser();
        if ($user['statut_compte'] === 'suspendu') {
            Response::erreurs(['Votre compte est suspendu.'], 403);
        }
        $in = Request::json();

        $erreurs = [];

        $createurId = filter_var($in['createur_id'] ?? null, FILTER_VALIDATE_INT);
        if ($createurId === false) {
            $erreurs[] = 'Créateur invalide.';
        } elseif ($createurId === (int) $user['id']) {
            $erreurs[] = 'Vous ne pouvez pas vous abonner à vous-même.';
        }

        $formule = is_string($in['formule'] ?? null) ? $in['formule'] : '';
        if (!array_key_exists($formule, Paiement::FORMULES)) {
            $erreurs[] = 'Choisissez une formule.';
        }

        $moyen = is_string($in['moyen_paiement'] ?? null) ? $in['moyen_paiement'] : '';
        if (!in_array($moyen, Paiement::MOYENS, true)) {
            $erreurs[] = 'Choisissez un moyen de paiement.';
        }

        // Le numéro n'est que VÉRIFIÉ pour imiter un vrai formulaire : il n'est ni enregistré ni journalisé.
        $numero = is_string($in['numero'] ?? null) ? preg_replace('/[\s.\-]/', '', $in['numero']) : '';
        if ($moyen === 'carte') {
            if (!preg_match('/^\d{12,19}$/', $numero)) {
                $erreurs[] = 'Numéro de carte invalide (12 à 19 chiffres).';
            }
        } elseif ($moyen !== '' && !preg_match('/^\+?\d{8,15}$/', $numero)) {
            $erreurs[] = 'Numéro de téléphone invalide (8 à 15 chiffres).';
        }

        if ($erreurs !== []) {
            throw new ValidationException($erreurs);
        }

        $resultat = Paiement::souscrire((int) $user['id'], $createurId, $formule, $moyen);
        Response::ok($resultat, 201);
    }
    public function unite(): void
    {
        Csrf::check();
        $user = Auth::requireUser();
        if ($user['statut_compte'] === 'suspendu') {
            Response::erreurs(['Votre compte est suspendu.'], 403);
        }
        $in = Request::json();

        $erreurs = [];

        $createurId = filter_var($in['createur_id'] ?? null, FILTER_VALIDATE_INT);
        if ($createurId === false) {
            $erreurs[] = 'Créateur invalide.';
        } elseif ($createurId === (int) $user['id']) {
            $erreurs[] = 'Ceci est votre vidéo.';
        }

        $prix = null;
        $brut = is_string($in['prix'] ?? null) ? trim($in['prix']) : '';
            if (!ctype_digit($brut) || (float) $brut < 1.0 || (float) $brut > 10000000.0) {
                $erreurs[] = 'Indiquez un prix en FCFA (nombre entier positif).';
            } else {
                $prix = (float) $brut;
            }

        $moyen = is_string($in['moyen_paiement'] ?? null) ? $in['moyen_paiement'] : '';
        if (!in_array($moyen, Paiement::MOYENS, true)) {
            $erreurs[] = 'Choisissez un moyen de paiement.';
        }

        // Le numéro n'est que VÉRIFIÉ pour imiter un vrai formulaire : il n'est ni enregistré ni journalisé.
        $numero = is_string($in['numero'] ?? null) ? preg_replace('/[\s.\-]/', '', $in['numero']) : '';
        if ($moyen === 'carte') {
            if (!preg_match('/^\d{12,19}$/', $numero)) {
                $erreurs[] = 'Numéro de carte invalide (12 à 19 chiffres).';
            }
        } elseif ($moyen !== '' && !preg_match('/^\+?\d{8,15}$/', $numero)) {
            $erreurs[] = 'Numéro de téléphone invalide (8 à 15 chiffres).';
        }

        if ($erreurs !== []) {
            throw new ValidationException($erreurs);
        }

        $resultat = Paiement::contenu((int) $user['id'], (int) $in['id'], $createurId, (float) $prix, $moyen);
        Response::ok($resultat, 201);
    }
    public function retrait(): void
    {
        Csrf::check();
        $user = Auth::requireUser();
        if ($user['role'] !== 'createur') Response::erreurs(['Seuls les créateurs peuvent demander un retrait.'], 403);
        if ($user['statut_compte'] !== 'actif') Response::erreurs(['Votre compte créateur doit être validé pour demander un retrait.'], 403);
        $in = Request::json();

        $erreurs = [];


        $montant = null;
        $brut = is_string($in['montant'] ?? null) ? trim($in['montant']) : '';
            if (!ctype_digit($brut) || (float) $brut < 1.0 || (float) $brut > 10000000.0) {
                $erreurs[] = 'Indiquez un montant en FCFA (nombre entier positif, 10 000 000 maximum).';
            } else {
                $montant = (float) $brut;
            }

        $moyen = is_string($in['moyen_paiement'] ?? null) ? $in['moyen_paiement'] : '';
        if (!in_array($moyen, ['mtn_momo', 'moov_money'], true)) {
            $erreurs[] = 'Choisissez MTN MoMo ou Moov Money.';
        }

        // Le numéro n'est que VÉRIFIÉ pour imiter un vrai formulaire : il n'est ni enregistré ni journalisé.
        $numero = is_string($in['numero'] ?? null) ? preg_replace('/[\s.\-]/', '', $in['numero']) : '';
        if ($moyen !== '' && !preg_match('/^\+?\d{8,15}$/', $numero)) {
            $erreurs[] = 'Numéro de téléphone invalide (8 à 15 chiffres).';
        }

        if ($erreurs !== []) {
            throw new ValidationException($erreurs);
        }

        $resultat = Paiement::retrait((int) $user['id'], (float) $montant, $moyen, $numero);
        Response::ok($resultat, 201);
    }
}
