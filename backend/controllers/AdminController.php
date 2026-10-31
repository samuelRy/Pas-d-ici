<?php
declare(strict_types=1);

/** Toutes les routes de ce contrôleur sont réservées aux administrateurs. */
final class AdminController
{
    /** GET /api/admin/contenus : contenus en attente de validation. */
    public function contenus(): void
    {
        Auth::requireAdmin();
        Response::ok(Contenu::enValidation());
    }

    /** POST /api/admin/contenus/decision  { id, decision, motif? } */
    public function decisionContenu(): void
    {
        Csrf::check();
        $admin = Auth::requireAdmin();
        $in = Request::json();

        $id = filter_var($in['id'] ?? null, FILTER_VALIDATE_INT);
        $decision = is_string($in['decision'] ?? null) ? $in['decision'] : '';
        $motif = is_string($in['motif'] ?? null) ? trim($in['motif']) : '';

        if ($id === false || !in_array($decision, ['approuver', 'refuser', 'remettre_en_attente'], true)) {
            throw new ValidationException(['Requête invalide.'], 400);
        }
        if ($decision === 'refuser' && ($motif === '' || mb_strlen($motif) > 255)) {
            throw new ValidationException(['Indiquez le motif du refus (255 caractères au plus) : le créateur le verra.']);
        }

        Contenu::moderer($id, (int) $admin['id'], $decision, $decision === 'refuser' ? $motif : null);
        Response::ok(null);
    }

    /** GET /api/admin/createurs : comptes créateurs en attente de validation. */
    public function createurs(): void
    {
        Auth::requireAdmin();
        Response::ok(Createur::enAttente());
    }

    /** POST /api/admin/createurs/decision  { id, decision: valider | refuser } */
    public function decisionCreateur(): void
    {
        Csrf::check();
        $admin = Auth::requireAdmin();
        $in = Request::json();

        $id = filter_var($in['id'] ?? null, FILTER_VALIDATE_INT);
        $decision = is_string($in['decision'] ?? null) ? $in['decision'] : '';

        if ($id === false || !in_array($decision, ['valider', 'refuser'], true)) {
            throw new ValidationException(['Requête invalide.'], 400);
        }

        Createur::decider($id, (int) $admin['id'], $decision);
        Response::ok(null);
    }

    /** GET /api/admin/retraits : demandes de retrait à confirmer. */
    public function retraits(): void
    {
        Auth::requireAdmin();
        Response::ok(Paiement::retraitsEnAttente());
    }

    /** POST /api/admin/retraits/decision { id, decision, motif? } */
    public function decisionRetrait(): void
    {
        Csrf::check();
        $admin = Auth::requireAdmin();
        $in = Request::json();
        $id = filter_var($in['id'] ?? null, FILTER_VALIDATE_INT);
        $decision = is_string($in['decision'] ?? null) ? $in['decision'] : '';
        $motif = is_string($in['motif'] ?? null) ? trim($in['motif']) : '';
        if ($id === false || !in_array($decision, ['approuver', 'refuser'], true)) {
            throw new ValidationException(['Requête de retrait invalide.'], 400);
        }
        if ($decision === 'refuser' && ($motif === '' || mb_strlen($motif) > 255)) {
            throw new ValidationException(['Indiquez le motif du refus (255 caractères maximum).']);
        }
        Paiement::deciderRetrait((int) $id, (int) $admin['id'], $decision, $decision === 'refuser' ? $motif : null);
        Response::ok(null);
    }

    /** GET /api/admin/media?id=<id du contenu> : lecture du fichier d'un contenu, pour le juger. */
    public function media(): void
    {
        Auth::requireAdmin();

        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false) {
            throw new ValidationException(['Requête invalide.'], 400);
        }

        $chemin = Contenu::cheminMedia($id);
        if ($chemin === null) {
            throw new ValidationException(['Fichier introuvable.'], 404);
        }
        StockageService::envoyer($chemin);
    }
}
