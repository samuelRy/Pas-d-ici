<?php
declare(strict_types=1);

/** Erreur « normale » (données invalides, fichier refusé...) : devient une réponse JSON avec le bon statut. */
final class ValidationException extends RuntimeException
{
    /** @param string[] $erreurs */
    public function __construct(public readonly array $erreurs, public readonly int $status = 422)
    {
        parent::__construct($erreurs[0] ?? 'Données invalides.');
    }
}
