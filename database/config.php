<?php
/* ============================================================
   ScholarIS — database/config.php
   Configuration de la base de données MySQL
   Responsable : Baba Sarr

   ⚠️  C'est ICI que tu modifies si tu changes de machine.
   Ne touche à rien d'autre pour la connexion.
============================================================ */

define('DB_HOST',     'localhost');   // Serveur MySQL (toujours localhost avec XAMPP)
define('DB_PORT',     '3307');        // Port MySQL XAMPP (le tien est 3307)
define('DB_NAME',     'scholaris');   // Nom de la base de données
define('DB_USER',     'root');        // Utilisateur MySQL
define('DB_PASS',     '');            // Mot de passe (vide sur XAMPP par défaut)
define('DB_CHARSET',  'utf8mb4');     // Encodage (supporte les accents et emojis)
