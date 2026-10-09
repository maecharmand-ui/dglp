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

/*
 * Vérifier si l'offre possède déjà des candidatures.
 * Une offre ayant des candidatures ne doit pas être supprimée
 * directement afin de préserver l'historique de l'application.
 */
$verificationCandidatures = $pdo->prepare("
    SELECT COUNT(*)
    FROM candidature
    WHERE id_offre = ?
");

$verificationCandidatures->execute([$id_offre]);

$nombreCandidatures = (int) $verificationCandidatures->fetchColumn();

if ($nombreCandidatures > 0) {
    header("Location: offres.php?erreur=offre_utilisee");
    exit;
}

/* Supprimer l'offre */
$requete = $pdo->prepare("
    DELETE FROM offre
    WHERE id_offre = ?
");

$requete->execute([$id_offre]);

/* Retourner à la liste des offres */
header("Location: offres.php?suppression=success");
exit;