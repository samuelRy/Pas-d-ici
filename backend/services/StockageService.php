<?php
declare(strict_types=1);

/**
 * Enregistre les fichiers déposés par les créateurs, hors du dossier accessible depuis le web.
 * Le nom envoyé par le navigateur n'est jamais utilisé : on le remplace par un nom aléatoire,
 * et le type du fichier est vérifié sur son contenu (pas sur son extension).
 */
final class StockageService
{
    /** Types vidéo acceptés pour le MVP => extension utilisée pour le stockage. */
    private const TYPES_VIDEO = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
    private const TYPES_IMAGE = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** @return string chemin relatif au dossier storage/uploads (ex. "videos/ab12....mp4") */
    public static function enregistrerVideo(mixed $fichier): string
    {
        if (!is_array($fichier) || !isset($fichier['error'], $fichier['tmp_name'], $fichier['size'])) {
            throw new ValidationException(['Choisissez un fichier vidéo.']);
        }

        switch ($fichier['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new ValidationException(['Choisissez un fichier vidéo.']);
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new ValidationException(['Le fichier est trop volumineux.'], 413);
            default:
                throw new ValidationException(['Le dépôt du fichier a échoué. Réessayez.'], 400);
        }

        $maxOctets = (int) Config::get('upload.video_max_mo', 200) * 1024 * 1024;
        if ((int) $fichier['size'] > $maxOctets) {
            throw new ValidationException(['Le fichier dépasse la taille maximale de ' . (int) Config::get('upload.video_max_mo', 200) . ' Mo.'], 413);
        }

        if (!is_uploaded_file($fichier['tmp_name'])) {
            throw new ValidationException(['Fichier invalide.'], 400);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($fichier['tmp_name']);
        if (!is_string($mime) || !isset(self::TYPES_VIDEO[$mime])) {
            throw new ValidationException(['Format non accepté : envoyez une vidéo MP4 ou WebM.'], 415);
        }

        $dossier = self::racine() . '/videos';
        if (!is_dir($dossier) && !mkdir($dossier, 0750, true) && !is_dir($dossier)) {
            throw new RuntimeException('Impossible de créer le dossier de stockage des vidéos.');
        }

        $nom = bin2hex(random_bytes(16)) . '.' . self::TYPES_VIDEO[$mime];
        if (!move_uploaded_file($fichier['tmp_name'], $dossier . '/' . $nom)) {
            $err = error_get_last()['message'] ?? 'aucun détail';
            error_log("Upload KO : dossier=$dossier existe=" . var_export(is_dir($dossier), true)
                . " ecriture=" . var_export(is_writable($dossier), true) . " | $err");
            throw new RuntimeException('Impossible d\'enregistrer le fichier déposé.');
        }
        chmod($dossier . '/' . $nom, 0640);

        return 'videos/' . $nom;
    }

    /** Enregistre une image JPG, PNG ou WebP, avec une limite de 10 Mo. */
    public static function enregistrerImage(mixed $fichier): string
    {
        if (!is_array($fichier) || !isset($fichier['error'], $fichier['tmp_name'], $fichier['size'])) {
            throw new ValidationException(['Choisissez au moins une photo.']);
        }
        if ($fichier['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException([$fichier['error'] === UPLOAD_ERR_NO_FILE ? 'Choisissez au moins une photo.' : 'Le dépôt d’une photo a échoué ou le fichier est trop volumineux.']);
        }
        if ((int) $fichier['size'] > 10 * 1024 * 1024) {
            throw new ValidationException(['Chaque photo doit peser 10 Mo maximum.'], 413);
        }
        if (!is_uploaded_file($fichier['tmp_name'])) {
            throw new ValidationException(['Fichier photo invalide.'], 400);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($fichier['tmp_name']);
        if (!is_string($mime) || !isset(self::TYPES_IMAGE[$mime])) {
            throw new ValidationException(['Format photo non accepté : utilisez JPG, PNG ou WebP.'], 415);
        }
        $dossier = self::racine() . '/images';
        if (!is_dir($dossier) && !mkdir($dossier, 0750, true) && !is_dir($dossier)) {
            throw new RuntimeException('Impossible de créer le dossier de stockage des photos.');
        }
        $nom = bin2hex(random_bytes(16)) . '.' . self::TYPES_IMAGE[$mime];
        if (!move_uploaded_file($fichier['tmp_name'], $dossier . '/' . $nom)) {
            throw new RuntimeException('Impossible d’enregistrer la photo déposée.');
        }
        chmod($dossier . '/' . $nom, 0640);
        return 'images/' . $nom;
    }

    /** Supprime un fichier déjà enregistré (utile si l'enregistrement en base échoue ensuite). */
    public static function supprimer(string $cheminRelatif): void
    {
        if (!preg_match('#^(videos/[a-f0-9]{32}\.(mp4|webm)|images/[a-f0-9]{32}\.(jpg|png|webp))$#', $cheminRelatif)) {
            return; // on ne supprime que des fichiers créés par ce service
        }
        $fichier = self::racine() . '/' . $cheminRelatif;
        if (is_file($fichier)) {
            unlink($fichier);
        }
    }

    /**
     * Envoie un fichier stocké au navigateur, avec prise en charge des requêtes partielles (Range) :
     * sans cela, la lecture et le déplacement dans une vidéo ne fonctionnent pas (Safari notamment).
     * L'appelant doit avoir vérifié que l'utilisateur a le droit de voir ce fichier.
     */
    public static function envoyer(string $cheminRelatif): never
    {
        if (!preg_match('#^(videos/[a-f0-9]{32}\.(mp4|webm)|images/[a-f0-9]{32}\.(jpg|png|webp))$#', $cheminRelatif, $m)) {
            Response::erreurs(['Fichier introuvable.'], 404);
        }
        $fichier = self::racine() . '/' . $cheminRelatif;
        if (!is_file($fichier)) {
            Response::erreurs(['Fichier introuvable.'], 404);
        }

        $taille = (int) filesize($fichier);
        $extension = strtolower(pathinfo($cheminRelatif, PATHINFO_EXTENSION));
        $mime = match ($extension) { 'webm' => 'video/webm', 'mp4' => 'video/mp4', 'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', default => 'application/octet-stream'};
        if (str_starts_with($mime, 'image/')) {
            session_write_close();
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . $taille);
            header('X-Content-Type-Options: nosniff');
            readfile($fichier);
            exit;
        }
        $debut = 0;
        $fin = $taille - 1;
        $statut = 200;

        session_write_close(); // ne pas bloquer les autres requêtes de l'utilisateur pendant l'envoi
        header('Accept-Ranges: bytes');

        if (isset($_SERVER['HTTP_RANGE'])) {
            if (!preg_match('/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'], $r) || ($r[1] === '' && $r[2] === '')) {
                http_response_code(416);
                header('Content-Range: bytes */' . $taille);
                exit;
            }
            if ($r[1] === '') {                         // « les N derniers octets »
                $debut = max(0, $taille - (int) $r[2]);
            } else {
                $debut = (int) $r[1];
                if ($r[2] !== '') {
                    $fin = min((int) $r[2], $taille - 1);
                }
            }
            if ($debut > $fin || $debut >= $taille) {
                http_response_code(416);
                header('Content-Range: bytes */' . $taille);
                exit;
            }
            $statut = 206;
            header('Content-Range: bytes ' . $debut . '-' . $fin . '/' . $taille);
        }

        http_response_code($statut);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . ($fin - $debut + 1));
        header('X-Content-Type-Options: nosniff');

        $flux = fopen($fichier, 'rb');
        fseek($flux, $debut);
        $reste = $fin - $debut + 1;
        while ($reste > 0 && !feof($flux)) {
            $morceau = fread($flux, min(65536, $reste));
            if ($morceau === false || $morceau === '') {
                break;
            }
            echo $morceau;
            $reste -= strlen($morceau);
            flush();
        }
        fclose($flux);
        exit;
    }

    private static function racine(): string
    {
        return BACKEND_PATH . '/storage/uploads';
    }
}
