
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../database.php";

/* Vérifier la connexion */
if (!isset($_SESSION["id_admin"])) {
    header("Location: connexion.php");
    exit;
}

/* Charger le rôle de l'administrateur */
$requete = $pdo->prepare("
    SELECT role
    FROM admin
    WHERE id_admin = ?
    LIMIT 1
");

$requete->execute([$_SESSION["id_admin"]]);
$roleConnecte = $requete->fetchColumn();

if ($roleConnecte === false) {
    session_destroy();
    header("Location: connexion.php");
    exit;
}

/* Le super administrateur conserve tous ses accès */
if ($roleConnecte === "super_admin") {
    return;
}

/* Identifier la page demandée */
$pageDemandee = basename($_SERVER["SCRIPT_NAME"]);

/* Vérifier la permission enregistrée */
$requetePermission = $pdo->prepare("
    SELECT autorise
    FROM permission p
    INNER JOIN role r ON r.id_role = p.id_role
    WHERE r.nom_role = ?
      AND p.page = ?
    LIMIT 1
");

$requetePermission->execute([
    $roleConnecte,
    $pageDemandee
]);

$autorise = $requetePermission->fetchColumn();

/* Refuser l'accès si la permission n'est pas accordée */
if ((string) $autorise !== "1") {
    http_response_code(403);
    exit(
        "Accès refusé : vous n'avez pas l'autorisation "
        . "de consulter cette page."
    );
}
