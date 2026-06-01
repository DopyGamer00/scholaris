<?php
/* ============================================================
   ScholarIS — database/api.php
   API REST pour gérer les candidatures (MySQL)
   Responsable : Baba Sarr

   Actions disponibles (via ?action=...) :
   ├── tester_connexion      → vérifie que MySQL répond
   ├── soumettre_candidature → enregistre une candidature
   ├── lister_candidatures   → liste avec filtre statut
   ├── changer_statut        → admin change le statut
   └── obtenir_stats         → statistiques du dashboard
============================================================ */

// Headers : on autorise les appels depuis le même serveur
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// On inclut la connexion
require_once __DIR__ . '/connexion.php';

// Lecture de l'action demandée
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Routage vers la bonne fonction
switch ($action) {

    case 'tester_connexion':
        testerConnexion();
        break;

    case 'soumettre_candidature':
        soumettreCandidature();
        break;

    case 'lister_candidatures':
        listerCandidatures();
        break;

    case 'changer_statut':
        changerStatut();
        break;

    case 'obtenir_stats':
        obtenirStats();
        break;

    default:
        repondre(false, 'Action inconnue : ' . htmlspecialchars($action));
        break;
}


/* ============================================================
   UTILITAIRE — Réponse JSON uniforme
============================================================ */
function repondre(bool $succes, string $message, array $data = []) {
    echo json_encode(array_merge(
        ['succes' => $succes, 'message' => $message],
        $data
    ), JSON_UNESCAPED_UNICODE);
    exit;
}


/* ============================================================
   UTILITAIRE — Calcul du score IA (sur 100 pts)
============================================================ */
function calculerScore(float $moyenne, string $situation, string $message): float {

    $score = 0;

    // 1. Moyenne : 40 pts max
    $score += ($moyenne / 20) * 40;

    // 2. Situation socio-économique : 30 pts max
    if ($situation === 'difficile')     $score += 30;
    elseif ($situation === 'modeste')   $score += 20;
    else                                $score += 5;

    // 3. Bonus progression/rebond dans le message : 10 pts
    $mots_positifs = ['progress', 'rattrap', 'amélior', 'remonté', 'effort', 'motiv', 'relevant'];
    foreach ($mots_positifs as $mot) {
        if (mb_stripos($message, $mot) !== false) {
            $score += 10;
            break;
        }
    }

    // 4. Bonus très bonne moyenne : 10 pts max
    if ($moyenne >= 16)      $score += 10;
    elseif ($moyenne >= 14)  $score += 7;
    elseif ($moyenne >= 12)  $score += 4;

    // Le score ne dépasse pas 100
    return min(100, round($score, 1));
}


/* ============================================================
   ACTION 1 — Tester la connexion MySQL
   Utile pour vérifier que tout fonctionne
============================================================ */
function testerConnexion() {
    $pdo = getConnexion(); // Lance une exception si impossible
    $version = $pdo->query("SELECT VERSION() as v")->fetch();
    repondre(true, 'Connexion MySQL OK !', [
        'mysql_version' => $version['v'],
        'base'          => DB_NAME,
        'port'          => DB_PORT,
    ]);
}


/* ============================================================
   ACTION 2 — Soumettre une candidature (formulaire public)
============================================================ */
function soumettreCandidature() {

    // Récupération et nettoyage des données POST
    $nom       = trim($_POST['nom']       ?? '');
    $email     = trim($_POST['email']     ?? '');
    $filiere   = trim($_POST['filiere']   ?? '');
    $annee     = trim($_POST['annee']     ?? '');
    $moyenne   = floatval($_POST['moyenne']   ?? 0);
    $situation = trim($_POST['situation'] ?? '');
    $message   = trim($_POST['message']   ?? '');

    // Validation des champs obligatoires
    if (!$nom || !$email || !$filiere || !$annee || !$situation || !$message) {
        repondre(false, 'Tous les champs obligatoires doivent être remplis.');
    }

    // Validation email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        repondre(false, 'Adresse email invalide.');
    }

    // Validation moyenne
    if ($moyenne < 0 || $moyenne > 20) {
        repondre(false, 'La moyenne doit être entre 0 et 20.');
    }

    // Calcul automatique du score
    $score = calculerScore($moyenne, $situation, $message);

    try {
        $pdo = getConnexion();

        $stmt = $pdo->prepare("
            INSERT INTO candidatures
                (nom, email, filiere, annee, moyenne, situation, message, statut, score)
            VALUES
                (:nom, :email, :filiere, :annee, :moyenne, :situation, :message, 'en_attente', :score)
        ");

        $stmt->execute([
            ':nom'       => htmlspecialchars($nom, ENT_QUOTES, 'UTF-8'),
            ':email'     => strtolower($email),
            ':filiere'   => htmlspecialchars($filiere, ENT_QUOTES, 'UTF-8'),
            ':annee'     => htmlspecialchars($annee, ENT_QUOTES, 'UTF-8'),
            ':moyenne'   => $moyenne,
            ':situation' => $situation,
            ':message'   => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
            ':score'     => $score,
        ]);

        repondre(true, 'Candidature soumise avec succès !', ['score' => $score]);

    } catch (PDOException $e) {
        // Email déjà utilisé → contrainte UNIQUE
        if ($e->getCode() === '23000') {
            repondre(false, 'Cet email a déjà été utilisé pour une candidature.');
        }
        repondre(false, 'Erreur serveur. Réessayez plus tard.');
    }
}


/* ============================================================
   ACTION 3 — Lister les candidatures (dashboard admin)
============================================================ */
function listerCandidatures() {

    $statut = $_GET['statut'] ?? 'tous';

    // Statuts valides
    $statuts_ok = ['tous', 'en_attente', 'en_traitement', 'validee', 'refusee'];
    if (!in_array($statut, $statuts_ok)) {
        repondre(false, 'Filtre invalide.');
    }

    try {
        $pdo = getConnexion();

        if ($statut === 'tous') {
            $stmt = $pdo->query("
                SELECT * FROM candidatures
                ORDER BY score DESC, date_soumission DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM candidatures
                WHERE statut = :statut
                ORDER BY score DESC, date_soumission DESC
            ");
            $stmt->execute([':statut' => $statut]);
        }

        $candidatures = $stmt->fetchAll();

        repondre(true, 'OK', [
            'candidatures' => $candidatures,
            'total'        => count($candidatures),
        ]);

    } catch (PDOException $e) {
        repondre(false, 'Erreur lors de la récupération des candidatures.');
    }
}


/* ============================================================
   ACTION 4 — Changer le statut d'une candidature (admin)
============================================================ */
function changerStatut() {

    $id         = intval($_POST['id']         ?? 0);
    $statut     = trim($_POST['statut']       ?? '');
    $note_admin = trim($_POST['note_admin']   ?? '');

    // Validation
    $statuts_valides = ['en_attente', 'en_traitement', 'validee', 'refusee'];
    if ($id <= 0 || !in_array($statut, $statuts_valides)) {
        repondre(false, 'Données invalides.');
    }

    try {
        $pdo = getConnexion();

        $stmt = $pdo->prepare("
            UPDATE candidatures
            SET statut     = :statut,
                note_admin = :note_admin
            WHERE id       = :id
        ");

        $stmt->execute([
            ':statut'     => $statut,
            ':note_admin' => htmlspecialchars($note_admin, ENT_QUOTES, 'UTF-8'),
            ':id'         => $id,
        ]);

        if ($stmt->rowCount() === 0) {
            repondre(false, 'Candidature introuvable.');
        }

        repondre(true, 'Statut mis à jour avec succès.');

    } catch (PDOException $e) {
        repondre(false, 'Erreur lors de la mise à jour.');
    }
}


/* ============================================================
   ACTION 5 — Statistiques pour le dashboard
============================================================ */
function obtenirStats() {

    try {
        $pdo = getConnexion();

        $stmt = $pdo->query("
            SELECT
                COUNT(*)                                          AS total,
                SUM(statut = 'en_attente')                       AS en_attente,
                SUM(statut = 'en_traitement')                    AS en_traitement,
                SUM(statut = 'validee')                          AS validees,
                SUM(statut = 'refusee')                          AS refusees,
                ROUND(AVG(score),   1)                           AS score_moyen,
                ROUND(AVG(moyenne), 1)                           AS moyenne_generale
            FROM candidatures
        ");

        $stats = $stmt->fetch();

        // Taux de validation (évite division par zéro)
        $traitees = (int)$stats['validees'] + (int)$stats['refusees'];
        $stats['taux_validation'] = $traitees > 0
            ? round(($stats['validees'] / $traitees) * 100, 1)
            : 0;

        repondre(true, 'OK', ['stats' => $stats]);

    } catch (PDOException $e) {
        repondre(false, 'Erreur lors du calcul des statistiques.');
    }
}
