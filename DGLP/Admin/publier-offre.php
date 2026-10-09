<?php

session_start();

require_once "../../database.php";

/* Vérifier que l'administrateur est connecté */
if (!isset($_SESSION["id_admin"])) {
    header("Location: connexion.php");
    exit;
}

/* Vérifier que l'identifiant de l'offre est valide */
if (!isset($_GET["id"]) || !ctype_digit($_GET["id"])) {
    header("Location: offres.php");
    exit;
}

$id_offre = (int) $_GET["id"];

/* Vérifier que l'offre existe */
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

/* Publier l'offre */
$requete = $pdo->prepare("
    UPDATE offre
    SET statut = 'ouverte'
    WHERE id_offre = ?
");

$requete->execute([$id_offre]);

/* Retourner à la liste des offres */
header("Location: offres.php");
exit;