<?php 
/* ============================================================
   ScholarIS — database/connexion.php
   Connexion MySQL via PDO
   Responsable : Baba Sarr

   Ce fichier crée la connexion à MySQL et
   initialise la base si elle n'existe pas encore.
============================================================ */

require_once __DIR__ . '/config.php';

/**
 * Retourne une connexion PDO à MySQL.
 * Crée la base et les tables si elles n'existent pas.
 */
function getConnexion() {

    try {
        /*
         * Connexion en deux étapes :
         * 1. On se connecte SANS préciser la base (pour pouvoir la créer)
         * 2. On sélectionne / crée la base, puis on s'y connecte
         */

        // Étape 1 : Connexion initiale sans base
        $dsn_init = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=' . DB_CHARSET;

        $pdo_init = new PDO($dsn_init, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Étape 2 : Création de la base si elle n'existe pas
        $pdo_init->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Étape 3 : Connexion finale avec la base sélectionnée
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Création des tables si elles n'existent pas
        creerTables($pdo);

        return $pdo;

    } catch (PDOException $e) {
        // Message d'erreur clair pour faciliter le débogage
        http_response_code(500);
        echo json_encode([
            'succes'  => false,
            'message' => 'Erreur de connexion MySQL : ' . $e->getMessage(),
            'conseil' => 'Vérifiez que XAMPP (Apache + MySQL) est bien lancé sur le port 3307.'
        ]);
        exit;
    }
}


/**
 * Crée toutes les tables nécessaires si elles n'existent pas.
 * Utilise IF NOT EXISTS donc sans risque si la table existe déjà.
 */
function creerTables(PDO $pdo) {

    // ── Table des candidatures ──────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS candidatures (
            id                INT          AUTO_INCREMENT PRIMARY KEY,
            nom               VARCHAR(150) NOT NULL,
            email             VARCHAR(200) NOT NULL UNIQUE,
            filiere           VARCHAR(100) NOT NULL,
            annee             VARCHAR(50)  NOT NULL,
            moyenne           FLOAT        NOT NULL DEFAULT 0,
            situation         VARCHAR(50)  NOT NULL DEFAULT 'modeste',
            message           TEXT,
            statut            ENUM('en_attente','en_traitement','validee','refusee')
                                           NOT NULL DEFAULT 'en_attente',
            score             FLOAT        NOT NULL DEFAULT 0,
            note_admin        TEXT,
            date_soumission   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            date_modification DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                           ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Table des administrateurs ───────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id            INT          AUTO_INCREMENT PRIMARY KEY,
            nom           VARCHAR(150) NOT NULL,
            email         VARCHAR(200) NOT NULL UNIQUE,
            mot_de_passe  VARCHAR(255) NOT NULL,
            role          VARCHAR(50)  NOT NULL DEFAULT 'admin',
            date_creation DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Données de démonstration (une seule fois) ───────────
    insererDemoSiVide($pdo);
}


/**
 * Insère des candidatures fictives si la table est vide.
 * Permet de tester le dashboard immédiatement.
 */
function insererDemoSiVide(PDO $pdo) {

    // On vérifie d'abord si la table est vide
    $compte = $pdo->query("SELECT COUNT(*) as total FROM candidatures")->fetch();

    if ($compte['total'] > 0) {
        return; // Déjà des données, on ne réinsère pas
    }

    // Données de démonstration
    $candidatures = [
        ['Amadou Diallo',   'amadou@isi.sn',   'Génie Logiciel', '2ème année', 14.5, 'modeste',  'Je suis motivé et travailleur.',            'validee',       82.5],
        ['Fatou Ndiaye',    'fatou@isi.sn',     'IAGE',           '1ère année', 16.2, 'difficile','Ma famille a besoin de soutien financier.',  'validee',       91.0],
        ['Ibrahima Sow',    'ibrahima@isi.sn',  'Réseaux',        '3ème année', 11.8, 'modeste',  'J ai progressé de 4 points cette année.',   'en_traitement', 67.0],
        ['Mariama Ba',      'mariama@isi.sn',   'Génie Logiciel', '2ème année', 13.0, 'difficile','Je me suis rattrapée après un échec.',       'en_traitement', 74.5],
        ['Ousmane Fall',    'ousmane@isi.sn',   'IAGE',           '2ème année',  9.5, 'aisée',    'Je veux continuer mes études.',              'refusee',       38.0],
        ['Aissatou Camara', 'aissatou@isi.sn',  'Informatique',   '1ère année', 15.8, 'difficile','Première de ma promotion.',                  'en_attente',    88.0],
        ['Modou Gueye',     'modou@isi.sn',     'Génie Logiciel', '3ème année', 12.5, 'modeste',  'En progression constante depuis 2 ans.',     'en_attente',    70.0],
        ['Rokhaya Diop',    'rokhaya@isi.sn',   'Réseaux',        '2ème année', 17.1, 'difficile','Meilleure de ma promotion.',                 'en_attente',    95.0],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO candidatures (nom, email, filiere, annee, moyenne, situation, message, statut, score)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($candidatures as $c) {
        $stmt->execute($c);
    }

    // Compte admin par défaut (Baba Sarr)
    $pdo->exec("
        INSERT IGNORE INTO admins (nom, email, mot_de_passe, role)
        VALUES ('Baba Sarr', 'baba@scholaris.sn', 'admin123', 'admin')
    ");
}
