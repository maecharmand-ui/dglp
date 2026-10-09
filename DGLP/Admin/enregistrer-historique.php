<?php

/**
 * Enregistre une action dans l'historique de l'application DGLP.
 *
 * @param PDO         $pdo
 * @param int|null    $idAdmin
 * @param int|null    $idCandidature
 * @param string      $action
 * @param string      $description
 *
 * @return void
 */

function enregistrerHistorique(
    PDO $pdo,
    ?int $idAdmin,
    ?int $idCandidature,
    string $action,
    string $description
): void {

    $requete = $pdo->prepare("
        INSERT INTO historique (
            id_admin,
            id_candidature,
            action,
            description,
            date_action
        )
        VALUES (?, ?, ?, ?, NOW())
    ");

    $requete->execute([
        $idAdmin,
        $idCandidature,
        $action,
        $description
    ]);
}