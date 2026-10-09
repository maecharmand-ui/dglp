<?php

session_start();

require_once "../../database.php";

/* =========================================================
   1. Vérifier que l'administrateur est connecté
   ========================================================= */

if (!isset($_SESSION["id_admin"])) {
    header("Location: connexion.php");
    exit;
}

/* =========================================================
   2. Vérifier l'identifiant de l'offre
   ========================================================= */

if (!isset($_GET["id"]) || !ctype_digit($_GET["id"])) {
    header("Location: offres.php");
    exit;
}

$id_offre = (int) $_GET["id"];

/* =========================================================
   3. Vérifier que l'offre existe
   ========================================================= */

$verification = $pdo->prepare("
    SELECT id_offre
    FROM offre
    WHERE id_offre = ?
    LIMIT 1
");

$verification->execute([$id_offre]);

if (!$verification->fetch()) {
    header("Location: offres.php?erreur=offre_introuvable");
    exit;
}

/* =========================================================
   4. Fermer l'offre
   ========================================================= */

$requete = $pdo->prepare("
    UPDATE offre
    SET statut = 'fermee'
    WHERE id_offre = ?
");

$requete->execute([$id_offre]);

/* =========================================================
   5. Retour à la liste des offres
   ========================================================= */

header("Location: offres.php");
exit;