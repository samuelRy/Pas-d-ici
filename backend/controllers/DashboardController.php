<?php
declare(strict_types=1);

final class DashboardController
{
    /** GET /api/dashboard : statistiques et historique du créateur connecté. */
    public function index(): void
    {
        $user = Auth::requireUser();
        if ($user['role'] !== 'createur') Response::erreurs(['Le tableau de bord est réservé aux créateurs.'], 403);
        $id = (int) $user['id'];
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT solde_disponible FROM createurs WHERE utilisateur_id = :id');
        $stmt->execute(['id' => $id]);
        $solde = $stmt->fetchColumn();
        if ($solde === false) Response::erreurs(['Profil créateur introuvable.'], 404);
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(nb_vues), 0) AS vues, SUM(statut = 'publie') AS publies, SUM(statut = 'en_validation') AS en_validation FROM contenus WHERE createur_id = :id");
        $stmt->execute(['id' => $id]);
        $stats = $stmt->fetch() ?: [];
        Response::ok([
            'solde_disponible' => (int) $solde,
            'vues_totales' => (int) ($stats['vues'] ?? 0),
            'contenus_total' => (int) ($stats['total'] ?? 0),
            'contenus_publies' => (int) ($stats['publies'] ?? 0),
            'en_validation' => (int) ($stats['en_validation'] ?? 0),
            'contenus' => Contenu::parCreateur($id),
            'retraits' => Paiement::retraitsCreateur($id),
        ]);
    }
}
