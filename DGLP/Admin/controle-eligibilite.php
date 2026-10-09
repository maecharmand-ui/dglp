<?php

session_start();

require_once "../../database.php";
require_once "enregistrer-historique.php";

/* =========================================================
   1. Vérifier la connexion de l'administrateur
   ========================================================= */

if (!isset($_SESSION["id_admin"])) {
    header("Location: connexion.php");
    exit;
}

/* =========================================================
   2. Vérifier l'identifiant de la candidature
   ========================================================= */

if (!isset($_GET["id"]) || !ctype_digit($_GET["id"])) {
    header("Location: candidatures.php");
    exit;
}

$id_candidature = (int) $_GET["id"];

/* =========================================================
   3. Récupérer les informations de la candidature
   ========================================================= */

$requete = $pdo->prepare("
    SELECT
        c.id_candidature,
        c.numero_candidature,
        c.date_candidature,
        c.statut,

        ca.id_candidat,
        ca.nom,
        ca.prenom,
        ca.date_naissance,
        ca.province,
        ca.ville,
        ca.telephone,

        o.id_offre,
        o.nom AS nom_offre,
        o.description AS description_offre,
        o.date_creation,
        o.date_limite_candidature,
        o.nombre_place,
        o.montant,
        o.document_supplementaire,
        o.statut AS statut_offre

    FROM candidature c

    INNER JOIN candidat ca
        ON c.id_candidat = ca.id_candidat

    INNER JOIN offre o
        ON c.id_offre = o.id_offre

    WHERE c.id_candidature = ?
");

$requete->execute([$id_candidature]);

$candidature = $requete->fetch(PDO::FETCH_ASSOC);

if (!$candidature) {
    header("Location: candidatures.php");
    exit;
}

/* =========================================================
   4. Récupérer les critères de l'offre
   ========================================================= */

$requete = $pdo->prepare("
    SELECT
        id_critere,
        nom,
        description,
        valeur_min,
        valeur_max,
        valeur_attendue,
        poids,
        obligatoire
    FROM critere
    WHERE id_offre = ?
    ORDER BY id_critere ASC
");

$requete->execute([$candidature["id_offre"]]);

$criteria = $requete->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   5. Récupérer la règle d'éligibilité
   ========================================================= */

$requete = $pdo->prepare("
    SELECT
        id_regle,
        type_regle,
        date_creation
    FROM regle_eligibilite
    WHERE id_offre = ?
    LIMIT 1
");

$requete->execute([$candidature["id_offre"]]);

$regle = $requete->fetch(PDO::FETCH_ASSOC);

/*
 * Si aucune règle n'est enregistrée,
 * on utilise "tous" par défaut.
 */
$type_regle = $regle["type_regle"] ?? "tous";

/* =========================================================
   6. Récupérer le contrôle existant
   ========================================================= */

$requete = $pdo->prepare("
    SELECT
        id_controle,
        score,
        statut,
        justification,
        date_controle
    FROM controle_eligibilite
    WHERE id_candidature = ?
    ORDER BY id_controle DESC
    LIMIT 1
");

$requete->execute([$id_candidature]);

$controle = $requete->fetch(PDO::FETCH_ASSOC);

/* =========================================================
   7. Récupérer les vérifications existantes
   ========================================================= */

$verifications = [];

if ($controle) {

    $requete = $pdo->prepare("
        SELECT
            id_verification,
            id_controle,
            id_critere,
            resultat,
            observation,
            date_verification
        FROM verification
        WHERE id_controle = ?
    ");

    $requete->execute([$controle["id_controle"]]);

    $resultats = $requete->fetchAll(PDO::FETCH_ASSOC);

    foreach ($resultats as $verification) {
        $verifications[$verification["id_critere"]] = $verification;
    }
}

/* =========================================================
   8. Variables par défaut
   ========================================================= */

$message_succes = "";
$message_erreur = "";

$statut_global = $controle["statut"] ?? "À examiner";
$justification = $controle["justification"] ?? "";
$score = $controle["score"] ?? 0;

/* =========================================================
   9. Traitement du formulaire
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $resultats_formulaire = $_POST["resultat"] ?? [];
    $observations_formulaire = $_POST["observation"] ?? [];

    $statut_global = trim($_POST["statut_global"] ?? "À examiner");
    $justification = trim($_POST["justification"] ?? "");

    $statuts_autorises = [
        "À examiner",
        "Éligible",
        "Non éligible"
    ];

    if (!in_array($statut_global, $statuts_autorises, true)) {

        $message_erreur = "Le statut sélectionné est invalide.";

    } else {

        /*
         * Vérifier qu'une justification est fournie
         * lorsque le contrôle est terminé.
         */
        if (
            ($statut_global === "Éligible" ||
             $statut_global === "Non éligible")
            && $justification === ""
        ) {

            $message_erreur =
                "Veuillez fournir une justification pour ce résultat.";

        } else {

            /* =================================================
               10. Calcul du score
               ================================================= */

            $total_poids = 0;
            $score_obtenu = 0;

            $nombre_criteres_obligatoires = 0;
            $nombre_criteres_obligatoires_conformes = 0;

            foreach ($criteria as $critere) {

                $id_critere = (int) $critere["id_critere"];

                $poids = (float) $critere["poids"];

                if ($poids < 0) {
                    $poids = 0;
                }

                $total_poids += $poids;

                $resultat = $resultats_formulaire[$id_critere] ?? "Non vérifié";

                if ((int) $critere["obligatoire"] === 1) {

                    $nombre_criteres_obligatoires++;

                    if ($resultat === "Conforme") {
                        $nombre_criteres_obligatoires_conformes++;
                    }
                }

                if ($resultat === "Conforme") {

                    $score_obtenu += $poids;

                } elseif ($resultat === "Partiellement conforme") {

                    $score_obtenu += ($poids / 2);
                }
            }

            /*
             * Calcul du score final sur 100.
             */
            if ($total_poids > 0) {

                $score = round(
                    ($score_obtenu / $total_poids) * 100,
                    2
                );

            } else {

                $score = 0;
            }

            /* =================================================
               11. Vérification de la règle d'éligibilité
               ================================================= */

            $regle_respectee = true;

            if ($statut_global === "Éligible") {

                if ($nombre_criteres_obligatoires > 0) {

                    if ($type_regle === "tous") {

                        /*
                         * Tous les critères obligatoires
                         * doivent être conformes.
                         */
                        if (
                            $nombre_criteres_obligatoires_conformes
                            < $nombre_criteres_obligatoires
                        ) {

                            $regle_respectee = false;

                            $message_erreur =
                                "Le candidat ne peut pas être déclaré "
                                . "éligible : tous les critères obligatoires "
                                . "doivent être conformes.";
                        }

                    } elseif ($type_regle === "au_moins_un") {

                        /*
                         * Au moins un critère obligatoire
                         * doit être conforme.
                         */
                        if ($nombre_criteres_obligatoires_conformes < 1) {

                            $regle_respectee = false;

                            $message_erreur =
                                "Le candidat ne peut pas être déclaré "
                                . "éligible : au moins un critère obligatoire "
                                . "doit être conforme.";
                        }
                    }
                }
            }

            /* =================================================
               12. Enregistrement en base
               ================================================= */

            if ($regle_respectee && $message_erreur === "") {

                try {

                    $pdo->beginTransaction();

                    /* =========================================
                       Créer ou mettre à jour le contrôle
                       ========================================= */

                    if ($controle) {

                        $requete = $pdo->prepare("
                            UPDATE controle_eligibilite
                            SET
                                score = ?,
                                statut = ?,
                                justification = ?,
                                date_controle = NOW()
                            WHERE id_controle = ?
                        ");

                        $requete->execute([
                            $score,
                            $statut_global,
                            $justification !== ""
                                ? $justification
                                : null,
                            $controle["id_controle"]
                        ]);

                        $id_controle = (int) $controle["id_controle"];

                    } else {

                        $requete = $pdo->prepare("
                            INSERT INTO controle_eligibilite
                            (
                                id_candidature,
                                score,
                                statut,
                                justification,
                                date_controle
                            )
                            VALUES (?, ?, ?, ?, NOW())
                        ");

                        $requete->execute([
                            $id_candidature,
                            $score,
                            $statut_global,
                            $justification !== ""
                                ? $justification
                                : null
                        ]);

                        $id_controle = (int) $pdo->lastInsertId();
                    }

                    /* =========================================
                       Supprimer les anciennes vérifications
                       ========================================= */

                    $requete = $pdo->prepare("
                        DELETE FROM verification
                        WHERE id_controle = ?
                    ");

                    $requete->execute([$id_controle]);

                    /* =========================================
                       Enregistrer les nouvelles vérifications
                       ========================================= */

                    $requete_verification = $pdo->prepare("
                        INSERT INTO verification
                        (
                            id_controle,
                            id_critere,
                            resultat,
                            observation,
                            date_verification
                        )
                        VALUES (?, ?, ?, ?, NOW())
                    ");

                    foreach ($criteria as $critere) {

                        $id_critere = (int) $critere["id_critere"];

                        $resultat =
                            trim(
                                $resultats_formulaire[$id_critere]
                                ?? "Non vérifié"
                            );

                        $observations =
                            trim(
                                $observations_formulaire[$id_critere]
                                ?? ""
                            );

                        $resultats_autorises = [
                            "Non vérifié",
                            "Conforme",
                            "Partiellement conforme",
                            "Non conforme"
                        ];

                        if (!in_array(
                            $resultat,
                            $resultats_autorises,
                            true
                        )) {

                            throw new Exception(
                                "Résultat de vérification invalide."
                            );
                        }

                        $requete_verification->execute([
                            $id_controle,
                            $id_critere,
                            $resultat,
                            $observations !== ""
                                ? $observations
                                : null
                        ]);
                    }

                    /* =========================================
                       Mettre à jour le statut de la candidature
                       ========================================= */

                    if ($statut_global === "Éligible") {

                        $statut_candidature = "éligible";

                    } elseif ($statut_global === "Non éligible") {

                        $statut_candidature = "non éligible";

                    } else {

                        $statut_candidature = "en attente";
                    }

                    $requete = $pdo->prepare("
                        UPDATE candidature
                        SET statut = ?
                        WHERE id_candidature = ?
                    ");

                    $requete->execute([
                        $statut_candidature,
                        $id_candidature
                    ]);

                    /* =========================================
                       Enregistrer dans l'historique
                       ========================================= */

                    enregistrerHistorique(
                        $pdo,
                        (int) $_SESSION["id_admin"],
                        $id_candidature,
                        "Contrôle d'éligibilité",
                        "Contrôle de la candidature "
                        . $candidature["numero_candidature"]
                        . " effectué. Score : "
                        . number_format(
                            $score,
                            2,
                            ",",
                            " "
                        )
                        . "/100. Statut : "
                        . $statut_global
                    );

                    $pdo->commit();

                    /*
                     * Recharger le contrôle après l'enregistrement.
                     */
                    $requete = $pdo->prepare("
                        SELECT
                            id_controle,
                            score,
                            statut,
                            justification,
                            date_controle
                        FROM controle_eligibilite
                        WHERE id_candidature = ?
                        LIMIT 1
                    ");

                    $requete->execute([$id_candidature]);

                    $controle = $requete->fetch(PDO::FETCH_ASSOC);

                    /*
                     * Recharger les vérifications.
                     */
                    $verifications = [];

                    if ($controle) {

                        $requete = $pdo->prepare("
                            SELECT
                                id_verification,
                                id_controle,
                                id_critere,
                                resultat,
                                observation,
                                date_verification
                            FROM verification
                            WHERE id_controle = ?
                        ");

                        $requete->execute([
                            $controle["id_controle"]
                        ]);

                        $resultats =
                            $requete->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($resultats as $verification) {

                            $verifications[
                                $verification["id_critere"]
                            ] = $verification;
                        }
                    }

                    $message_succes =
                        "Le contrôle d'éligibilité a été "
                        . "enregistré avec succès.";

                } catch (Throwable $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $message_erreur =
                        "Une erreur est survenue lors de "
                        . "l'enregistrement du contrôle : "
                        . $e->getMessage();
                }
            }
        }
    }
}

/* =========================================================
   13. Valeurs affichées dans le formulaire
   ========================================================= */

if ($controle) {

    $score = $controle["score"];
    $statut_global = $controle["statut"];
    $justification = $controle["justification"] ?? "";
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Contrôle d'éligibilité - DGLP
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #333;
        }

        header {
            background: #1f4e79;
            color: white;
            padding: 18px 30px;
        }

        header h1 {
            margin: 0 0 8px;
            font-size: 24px;
        }

        header p {
            margin: 0;
        }

        nav {
            background: #173b5c;
            padding: 12px 30px;
        }

        nav a {
            color: white;
            text-decoration: none;
            margin-right: 20px;
            font-size: 14px;
        }

        nav a:hover {
            text-decoration: underline;
        }

        .container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .card h2 {
            margin-top: 0;
            color: #1f4e79;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .info {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .info strong {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }

        .message-succes {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .message-erreur {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .regle {
            background: #eef5fb;
            border-left: 5px solid #1f4e79;
            padding: 15px;
            margin-bottom: 20px;
        }

        .critere {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .critere h3 {
            margin-top: 0;
            color: #1f4e79;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            background: #eee;
            margin-left: 5px;
        }

        .badge-obligatoire {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-optionnel {
            background: #e2e3e5;
            color: #383d41;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        .score-box {
            background: #eef5fb;
            border: 1px solid #c8dceb;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }

        .score {
            font-size: 36px;
            font-weight: bold;
            color: #1f4e79;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            display: inline-block;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-primary {
            background: #1f4e79;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }

        @media (max-width: 700px) {

            .grid {
                grid-template-columns: 1fr;
            }

            nav a {
                display: block;
                margin-bottom: 8px;
            }

            .actions {
                flex-direction: column;
            }

        }

    </style>

</head>

<body>

<header>

    <h1>DGLP - Contrôle d'éligibilité</h1>

    <p>
        Espace d'administration
    </p>

</header>

<nav>

    <a href="dashboard.php">Tableau de bord</a>

    <a href="offres.php">Offres</a>

    <a href="candidatures.php">Candidatures</a>

    <a href="classement.php">Classement</a>

    <a href="historique.php">Historique</a>

    <a href="deconnexion.php">Déconnexion</a>

</nav>

<div class="container">

    <?php if ($message_succes !== ""): ?>

        <div class="message-succes">
            <?= htmlspecialchars($message_succes) ?>
        </div>

    <?php endif; ?>

    <?php if ($message_erreur !== ""): ?>

        <div class="message-erreur">
            <?= htmlspecialchars($message_erreur) ?>
        </div>

    <?php endif; ?>

    <!-- =====================================================
         Informations de la candidature
         ===================================================== -->

    <div class="card">

        <h2>Informations de la candidature</h2>

        <div class="grid">

            <div class="info">

                <strong>Numéro de candidature</strong>

                <?= htmlspecialchars(
                    $candidature["numero_candidature"]
                ) ?>

            </div>

            <div class="info">

                <strong>Date de candidature</strong>

                <?= htmlspecialchars(
                    $candidature["date_candidature"]
                ) ?>

            </div>

            <div class="info">

                <strong>Candidat</strong>

                <?= htmlspecialchars(
                    $candidature["nom"]
                    . " "
                    . $candidature["prenom"]
                ) ?>

            </div>

            <div class="info">

                <strong>Province</strong>

                <?= htmlspecialchars(
                    $candidature["province"]
                ) ?>

            </div>

            <div class="info">

                <strong>Ville</strong>

                <?= htmlspecialchars(
                    $candidature["ville"]
                ) ?>

            </div>

            <div class="info">

                <strong>Téléphone</strong>

                <?= htmlspecialchars(
                    $candidature["telephone"]
                ) ?>

            </div>

        </div>

    </div>

    <!-- =====================================================
         Informations de l'offre
         ===================================================== -->

    <div class="card">

        <h2>Offre concernée</h2>

        <div class="grid">

            <div class="info">

                <strong>Nom de l'offre</strong>

                <?= htmlspecialchars(
                    $candidature["nom_offre"]
                ) ?>

            </div>

            <div class="info">

                <strong>Statut de l'offre</strong>

                <?= htmlspecialchars(
                    $candidature["statut_offre"]
                ) ?>

            </div>

            <div class="info">

                <strong>Date de création</strong>

                <?= htmlspecialchars(
                    $candidature["date_creation"]
                ) ?>

            </div>

            <div class="info">

                <strong>Date limite</strong>

                <?= htmlspecialchars(
                    $candidature["date_limite_candidature"]
                ) ?>

            </div>

            <div class="info">

                <strong>Nombre de places</strong>

                <?= htmlspecialchars(
                    $candidature["nombre_place"]
                ) ?>

            </div>

            <div class="info">

                <strong>Montant</strong>

                <?php if ($candidature["montant"] !== null): ?>

                    <?= number_format(
                        (float) $candidature["montant"],
                        2,
                        ",",
                        " "
                    ) ?>

                <?php else: ?>

                    Non renseigné

                <?php endif; ?>

            </div>

        </div>

        <div style="margin-top: 15px;">

            <strong>Description :</strong>

            <p>
                <?= nl2br(
                    htmlspecialchars(
                        $candidature["description_offre"]
                    )
                ) ?>
            </p>

        </div>

    </div>

    <!-- =====================================================
         Règle d'éligibilité
         ===================================================== -->

    <div class="card">

        <h2>Règle d'éligibilité</h2>

        <div class="regle">

            <?php if ($type_regle === "tous"): ?>

                <strong>Tous les critères obligatoires</strong>

                <p>
                    Tous les critères marqués comme obligatoires
                    doivent être conformes pour que le candidat
                    puisse être déclaré éligible.
                </p>

            <?php elseif ($type_regle === "au_moins_un"): ?>

                <strong>Au moins un critère obligatoire</strong>

                <p>
                    Au moins un critère obligatoire doit être
                    conforme pour que le candidat puisse être
                    déclaré éligible.
                </p>

            <?php else: ?>

                <strong>
                    Règle :
                    <?= htmlspecialchars($type_regle) ?>
                </strong>

            <?php endif; ?>

        </div>

    </div>

    <!-- =====================================================
         Formulaire de contrôle
         ===================================================== -->

    <form method="POST">

        <div class="card">

            <h2>Vérification des critères</h2>

            <?php if (empty($criteria)): ?>

                <div class="message-erreur">

                    Aucun critère n'est enregistré pour cette offre.

                </div>

            <?php else: ?>

                <?php foreach ($criteria as $critere): ?>

                    <?php

                    $id_critere =
                        (int) $critere["id_critere"];

                    $verification_existante =
                        $verifications[$id_critere] ?? null;

                    $resultat_actuel =
                        $verification_existante["resultat"]
                        ?? "Non vérifié";

                    $observation_actuelle =
                        $verification_existante["observation"]
                        ?? "";

                    ?>

                    <div class="critere">

                        <h3>

                            <?= htmlspecialchars(
                                $critere["nom"]
                            ) ?>

                            <?php if (
                                (int) $critere["obligatoire"] === 1
                            ): ?>

                                <span class="badge badge-obligatoire">
                                    Obligatoire
                                </span>

                            <?php else: ?>

                                <span class="badge badge-optionnel">
                                    Facultatif
                                </span>

                            <?php endif; ?>

                        </h3>

                        <?php if (
                            !empty($critere["description"])
                        ): ?>

                            <p>

                                <?= nl2br(
                                    htmlspecialchars(
                                        $critere["description"]
                                    )
                                ) ?>

                            </p>

                        <?php endif; ?>

                        <p>

                            <strong>Poids :</strong>

                            <?= htmlspecialchars(
                                $critere["poids"]
                            ) ?>

                        </p>

                        <?php if (
                            $critere["valeur_min"] !== null
                            || $critere["valeur_max"] !== null
                            || $critere["valeur_attendue"] !== null
                        ): ?>

                            <p>

                                <strong>
                                    Valeur attendue :
                                </strong>

                                <?php if (
                                    $critere["valeur_min"] !== null
                                ): ?>

                                    Minimum :
                                    <?= htmlspecialchars(
                                        $critere["valeur_min"]
                                    ) ?>

                                <?php endif; ?>

                                <?php if (
                                    $critere["valeur_max"] !== null
                                ): ?>

                                    &nbsp;| Maximum :
                                    <?= htmlspecialchars(
                                        $critere["valeur_max"]
                                    ) ?>

                                <?php endif; ?>

                                <?php if (
                                    $critere["valeur_attendue"] !== null
                                ): ?>

                                    &nbsp;| Attendu :
                                    <?= htmlspecialchars(
                                        $critere["valeur_attendue"]
                                    ) ?>

                                <?php endif; ?>

                            </p>

                        <?php endif; ?>

                        <div class="form-group">

                            <label
                                for="resultat_<?= $id_critere ?>"
                            >
                                Résultat de la vérification
                            </label>

                            <select
                                name="resultat[<?= $id_critere ?>]"
                                id="resultat_<?= $id_critere ?>"
                                required
                            >

                                <option
                                    value="Non vérifié"
                                    <?= $resultat_actuel === "Non vérifié"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Non vérifié
                                </option>

                                <option
                                    value="Conforme"
                                    <?= $resultat_actuel === "Conforme"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Conforme
                                </option>

                                <option
                                    value="Partiellement conforme"
                                    <?= $resultat_actuel === "Partiellement conforme"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Partiellement conforme
                                </option>

                                <option
                                    value="Non conforme"
                                    <?= $resultat_actuel === "Non conforme"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Non conforme
                                </option>

                            </select>

                        </div>

                        <div class="form-group">

                            <label
                                for="observation_<?= $id_critere ?>"
                            >
                                Observation
                            </label>

                            <textarea
                                name="observation[<?= $id_critere ?>]"
                                id="observation_<?= $id_critere ?>"
                                placeholder="Ajouter une observation..."
                            ><?= htmlspecialchars(
                                $observation_actuelle
                            ) ?></textarea>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

        <!-- =================================================
             Résultat global
             ================================================= -->

        <div class="card">

            <h2>Résultat du contrôle</h2>

            <div class="score-box">

                <div>
                    Score calculé
                </div>

                <div class="score">

                    <?= number_format(
                        (float) $score,
                        2,
                        ",",
                        " "
                    ) ?>

                    / 100

                </div>

                <p>
                    Le score est calculé automatiquement à partir
                    des résultats et des poids des critères.
                </p>

            </div>

            <div class="form-group">

                <label for="statut_global">
                    Statut global
                </label>

                <select
                    name="statut_global"
                    id="statut_global"
                    required
                >

                    <option
                        value="À examiner"
                        <?= $statut_global === "À examiner"
                            ? "selected"
                            : "" ?>
                    >
                        À examiner
                    </option>

                    <option
                        value="Éligible"
                        <?= $statut_global === "Éligible"
                            ? "selected"
                            : "" ?>
                    >
                        Éligible
                    </option>

                    <option
                        value="Non éligible"
                        <?= $statut_global === "Non éligible"
                            ? "selected"
                            : "" ?>
                    >
                        Non éligible
                    </option>

                </select>

            </div>

            <div class="form-group">

                <label for="justification">
                    Justification
                </label>

                <textarea
                    name="justification"
                    id="justification"
                    placeholder="Expliquez la décision concernant l'éligibilité..."
                ><?= htmlspecialchars(
                    $justification
                ) ?></textarea>

            </div>

            <div class="actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Enregistrer le contrôle
                </button>

                <a
                    href="candidatures.php"
                    class="btn btn-secondary"
                >
                    Retour aux candidatures
                </a>

            </div>

        </div>

    </form>

</div>

</body>

</html>