<?php

session_start();
require_once "../../database.php";
require_once "enregistrer-historique.php";

/* =========================================================
   VÉRIFIER LA CONNEXION DE L'ADMINISTRATEUR
   ========================================================= */

if (!isset($_SESSION["id_admin"])) {
    header("Location: connexion.php");
    exit;
}


/* =========================================================
   MESSAGE
   ========================================================= */

$message = "";
$type_message = "";


/* =========================================================
   TRAITEMENT DU CLASSEMENT
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id_candidature = filter_input(
        INPUT_POST,
        "id_candidature",
        FILTER_VALIDATE_INT
    );

    $rang = filter_input(
        INPUT_POST,
        "rang",
        FILTER_VALIDATE_INT
    );

    $statut = trim($_POST["statut"] ?? "");
    $justification = trim($_POST["justification"] ?? "");

    if (!$id_candidature) {

        $message = "Candidature invalide.";
        $type_message = "erreur";

    } elseif (!$rang || $rang < 1) {

        $message = "Le rang doit être un nombre supérieur ou égal à 1.";
        $type_message = "erreur";

    } elseif (
        !in_array(
            $statut,
            ["Classé", "En attente", "Non retenu"],
            true
        )
    ) {

        $message = "Statut de classement invalide.";
        $type_message = "erreur";

    } elseif ($justification === "") {

        $message = "Veuillez saisir une justification.";
        $type_message = "erreur";

    } else {

        try {

            /* =====================================================
               VÉRIFIER QUE LA CANDIDATURE EST ÉLIGIBLE
               ===================================================== */

            $verification = $pdo->prepare("
                SELECT
                    c.id_candidature,
                    c.numero_candidature,
                    ce.score,
                    ce.statut
                FROM candidature c

                INNER JOIN controle_eligibilite ce
                    ON c.id_candidature = ce.id_candidature

                WHERE c.id_candidature = ?
                AND ce.statut = 'Éligible'
            ");

            $verification->execute([
                $id_candidature
            ]);

            $candidature = $verification->fetch(PDO::FETCH_ASSOC);


            if (!$candidature) {

                $message = "Cette candidature n'est pas déclarée éligible.";
                $type_message = "erreur";

            } else {

                /* =================================================
                   VÉRIFIER SI LA CANDIDATURE EST DÉJÀ CLASSÉE
                   ================================================= */

                $existant = $pdo->prepare("
                    SELECT id_classement
                    FROM classement
                    WHERE id_candidature = ?
                ");

                $existant->execute([
                    $id_candidature
                ]);

                $classement_existant = $existant->fetch(PDO::FETCH_ASSOC);


                /* =================================================
                   ENREGISTRER OU MODIFIER LE CLASSEMENT
                   ================================================= */

                if ($classement_existant) {

                    $requete = $pdo->prepare("
                        UPDATE classement
                        SET
                            rang = ?,
                            score = ?,
                            statut = ?,
                            justification = ?,
                            date_classement = NOW()
                        WHERE id_candidature = ?
                    ");

                    $requete->execute([
                        $rang,
                        $candidature["score"],
                        $statut,
                        $justification,
                        $id_candidature
                    ]);


                    $descriptionHistorique =
                        "Classement de la candidature "
                        . $candidature["numero_candidature"]
                        . " modifié. Rang : "
                        . $rang
                        . ". Score : "
                        . $candidature["score"]
                        . "%. Statut : "
                        . $statut
                        . ". Justification : "
                        . $justification;

                    enregistrerHistorique(
                        $pdo,
                        (int) $_SESSION["id_admin"],
                        $id_candidature,
                        "Classement",
                        $descriptionHistorique
                    );

                    $message = "Le classement de la candidature a été mis à jour.";
                    $type_message = "succes";

                } else {

                    $requete = $pdo->prepare("
                        INSERT INTO classement (
                            id_candidature,
                            rang,
                            score,
                            statut,
                            justification,
                            date_classement
                        )
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");

                    $requete->execute([
                        $id_candidature,
                        $rang,
                        $candidature["score"],
                        $statut,
                        $justification
                    ]);


                    $descriptionHistorique =
                        "Classement de la candidature "
                        . $candidature["numero_candidature"]
                        . " enregistré. Rang : "
                        . $rang
                        . ". Score : "
                        . $candidature["score"]
                        . "%. Statut : "
                        . $statut
                        . ". Justification : "
                        . $justification;

                    enregistrerHistorique(
                        $pdo,
                        (int) $_SESSION["id_admin"],
                        $id_candidature,
                        "Classement",
                        $descriptionHistorique
                    );

                    $message = "La candidature a été classée avec succès.";
                    $type_message = "succes";
                }
            }

        } catch (PDOException $e) {

            $message = "Une erreur est survenue lors de l'enregistrement du classement.";
            $type_message = "erreur";
        }
    }
}


/* =========================================================
   RÉCUPÉRER LES CANDIDATURES ÉLIGIBLES
   ========================================================= */

$requete = $pdo->query("
    SELECT
        c.id_candidature,
        c.numero_candidature,
        c.date_candidature,

        ca.nom AS nom_candidat,
        ca.prenom AS prenom_candidat,

        o.id_offre,
        o.nom AS nom_offre,

        ce.id_controle,
        ce.score AS score_eligibilite,
        ce.statut AS statut_eligibilite,

        cl.id_classement,
        cl.rang,
        cl.statut AS statut_classement,
        cl.justification

    FROM candidature c

    INNER JOIN candidat ca
        ON c.id_candidat = ca.id_candidat

    INNER JOIN offre o
        ON c.id_offre = o.id_offre

    INNER JOIN controle_eligibilite ce
        ON c.id_candidature = ce.id_candidature

    LEFT JOIN classement cl
        ON c.id_candidature = cl.id_candidature

    WHERE ce.statut = 'Éligible'

    ORDER BY
        o.nom ASC,
        ce.score DESC,
        c.date_candidature ASC
");

$candidatures = $requete->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   RÉCUPÉRER LES CLASSEMENTS ENREGISTRÉS
   ========================================================= */

$requeteClassement = $pdo->query("
    SELECT
        cl.id_classement,
        cl.id_candidature,
        cl.rang,
        cl.score,
        cl.statut,
        cl.justification,
        cl.date_classement,

        c.numero_candidature,

        ca.nom AS nom_candidat,
        ca.prenom AS prenom_candidat,

        o.nom AS nom_offre

    FROM classement cl

    INNER JOIN candidature c
        ON cl.id_candidature = c.id_candidature

    INNER JOIN candidat ca
        ON c.id_candidat = ca.id_candidat

    INNER JOIN offre o
        ON c.id_offre = o.id_offre

    ORDER BY
        o.nom ASC,
        cl.rang ASC,
        cl.score DESC
");

$classements = $requeteClassement->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Classement - DGLP Administration</title>


    <style>

        /* =====================================================
           RESET
        ===================================================== */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {
            font-family: Arial, sans-serif;
            background:
                linear-gradient(
                    rgba(244, 246, 248, 0.94),
                    rgba(244, 246, 248, 0.94)
                ),
                url("../images/dglp.jpg") center center / 420px auto no-repeat fixed;
            background-color: #f4f6f8;
            color: #263746;
            line-height: 1.6;
        }


        a {
            text-decoration: none;
            color: inherit;
        }


        /* =====================================================
           SIDEBAR
        ===================================================== */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 255px;
            height: 100vh;
            background: #123b68;
            color: white;
            padding: 22px 15px;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.12);
            z-index: 1000;
            overflow-y: auto;
        }


        .sidebar-logo {
            text-align: center;
            padding-bottom: 22px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.18);
            margin-bottom: 20px;
        }


        .sidebar-logo img {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 50%;
            background: white;
            padding: 4px;
            margin-bottom: 10px;
        }


        .sidebar-logo h2 {
            font-size: 20px;
            margin-bottom: 3px;
        }


        .sidebar-logo p {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.78);
        }


        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }


        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            transition: 0.25s ease;
        }


        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.12);
            transform: translateX(2px);
        }


        .sidebar-menu a.active {
            background: white;
            color: #123b68;
            font-weight: bold;
        }


        .menu-icon {
            width: 24px;
            text-align: center;
            font-size: 17px;
        }


        /* =====================================================
           CONTENU PRINCIPAL
        ===================================================== */

        .main {
            margin-left: 255px;
            min-height: 100vh;
        }


        /* =====================================================
           HEADER
        ===================================================== */

        .top-header {
            height: 82px;
            background: white;
            border-bottom: 1px solid #e1e6eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 35px;
            position: sticky;
            top: 0;
            z-index: 900;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }


        .ministere {
            display: flex;
            align-items: center;
            gap: 14px;
        }


        .ministere img {
            width: 52px;
            height: 52px;
            object-fit: contain;
        }


        .ministere-text strong {
            display: block;
            color: #123b68;
            font-size: 15px;
        }


        .ministere-text span {
            display: block;
            color: #6d7882;
            font-size: 12px;
            margin-top: 2px;
        }


        .admin-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }


        .admin-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #123b68;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }


        .admin-user-info strong {
            display: block;
            color: #263746;
            font-size: 14px;
        }


        .admin-user-info span {
            color: #7b8791;
            font-size: 12px;
        }


        /* =====================================================
           CONTENU
        ===================================================== */

        .content {
            padding: 32px 35px 45px;
        }


        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
        }


        .page-header h1 {
            color: #123b68;
            font-size: 29px;
            margin-bottom: 7px;
        }


        .page-header p {
            color: #697783;
            font-size: 14px;
        }


        .page-header strong {
            color: #123b68;
        }


        /* =====================================================
           MESSAGE
        ===================================================== */

        .message {
            padding: 15px 18px;
            margin-bottom: 25px;
            border-radius: 9px;
            font-weight: 600;
            border-left: 5px solid;
        }


        .message.succes {
            background: #eaf7ef;
            color: #176b3a;
            border-left-color: #008c4a;
        }


        .message.erreur {
            background: #fff0f0;
            color: #a12828;
            border-left-color: #c93636;
        }


        /* =====================================================
           STATISTIQUES
        ===================================================== */

        .stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(180px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }


        .stat-card {
            background: white;
            border: 1px solid #e3e8ed;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(18, 59, 104, 0.06);
            display: flex;
            align-items: center;
            gap: 15px;
        }


        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            background: #eaf1f8;
            color: #123b68;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 21px;
            font-weight: bold;
        }


        .stat-info span {
            display: block;
            color: #7b8791;
            font-size: 12px;
            margin-bottom: 3px;
        }


        .stat-info strong {
            color: #123b68;
            font-size: 23px;
        }


        /* =====================================================
           SECTIONS
        ===================================================== */

        .section-card {
            background: white;
            border: 1px solid #e1e6eb;
            border-radius: 13px;
            box-shadow: 0 4px 18px rgba(18, 59, 104, 0.06);
            margin-bottom: 28px;
            overflow: hidden;
        }


        .section-header {
            padding: 22px 24px;
            border-bottom: 1px solid #e8edf1;
        }


        .section-header h2 {
            color: #123b68;
            font-size: 19px;
            margin-bottom: 5px;
        }


        .section-header p {
            color: #73808b;
            font-size: 13px;
        }


        .section-body {
            padding: 22px 24px;
        }


        /* =====================================================
           TABLEAU
        ===================================================== */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }


        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1050px;
        }


        th {
            background: #123b68;
            color: white;
            padding: 14px 12px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }


        td {
            padding: 14px 12px;
            border-bottom: 1px solid #edf0f3;
            color: #465563;
            font-size: 13px;
            vertical-align: top;
        }


        tbody tr:hover {
            background: #f8fafc;
        }


        tbody tr:last-child td {
            border-bottom: none;
        }


        .numero {
            font-weight: bold;
            color: #123b68;
        }


        .candidate-name {
            font-weight: 600;
            color: #263746;
        }


        .offer-name {
            font-weight: 600;
            color: #123b68;
        }


        /* =====================================================
           SCORE
        ===================================================== */

        .score {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #eaf7ef;
            color: #08753e;
            font-weight: bold;
            white-space: nowrap;
        }


        /* =====================================================
           FORMULAIRE DE CLASSEMENT
        ===================================================== */

        .form-classement {
            min-width: 330px;
            background: #f8fafc;
            border: 1px solid #e3e8ed;
            border-radius: 10px;
            padding: 16px;
        }


        .form-title {
            color: #123b68;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 13px;
        }


        .form-group {
            margin-bottom: 12px;
        }


        .form-group label {
            display: block;
            color: #4c5b68;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
        }


        .form-classement input,
        .form-classement select,
        .form-classement textarea {
            width: 100%;
            padding: 10px 11px;
            border: 1px solid #d5dde4;
            border-radius: 7px;
            background: white;
            color: #263746;
            font-family: Arial, sans-serif;
            font-size: 13px;
            outline: none;
            transition: 0.2s ease;
        }


        .form-classement input:focus,
        .form-classement select:focus,
        .form-classement textarea:focus {
            border-color: #123b68;
            box-shadow: 0 0 0 3px rgba(18, 59, 104, 0.08);
        }


        .form-classement textarea {
            min-height: 78px;
            resize: vertical;
        }


        .btn-classement {
            width: 100%;
            padding: 11px 15px;
            background: #123b68;
            color: white;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
            transition: 0.25s ease;
        }


        .btn-classement:hover {
            background: #0d2d50;
            transform: translateY(-1px);
        }


        /* =====================================================
           STATUTS
        ===================================================== */

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }


        .badge-classe {
            background: #eaf7ef;
            color: #08753e;
        }


        .badge-attente {
            background: #fff6df;
            color: #966600;
        }


        .badge-non-retenu {
            background: #fff0f0;
            color: #a12828;
        }


        .rang {
            display: inline-flex;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            background: #eaf1f8;
            color: #123b68;
            font-weight: bold;
        }


        .justification {
            max-width: 300px;
            color: #65727d;
            line-height: 1.5;
        }


        /* =====================================================
           ÉTAT VIDE
        ===================================================== */

        .empty-state {
            text-align: center;
            padding: 45px 20px;
        }


        .empty-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: #edf2f7;
            color: #123b68;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            font-weight: bold;
        }


        .empty-state h3 {
            color: #123b68;
            margin-bottom: 6px;
        }


        .empty-state p {
            color: #78848f;
            font-size: 13px;
        }


        /* =====================================================
           FOOTER
        ===================================================== */

        footer {
            margin-left: 255px;
            background: #0d2d50;
            color: rgba(255, 255, 255, 0.85);
            text-align: center;
            padding: 18px;
            font-size: 12px;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1100px) {

            .sidebar {
                width: 75px;
                padding: 18px 10px;
            }


            .sidebar-logo h2,
            .sidebar-logo p,
            .sidebar-menu span {
                display: none;
            }


            .sidebar-logo img {
                width: 48px;
                height: 48px;
            }


            .sidebar-menu a {
                justify-content: center;
                padding: 13px 8px;
            }


            .menu-icon {
                width: auto;
            }


            .main {
                margin-left: 75px;
            }


            footer {
                margin-left: 75px;
            }

        }


        @media (max-width: 750px) {

            .top-header {
                height: auto;
                min-height: 75px;
                padding: 12px 18px;
            }


            .ministere-text {
                display: none;
            }


            .admin-user-info {
                display: none;
            }


            .content {
                padding: 22px 15px 35px;
            }


            .page-header {
                flex-direction: column;
            }


            .page-header h1 {
                font-size: 23px;
            }


            .stats {
                grid-template-columns: 1fr;
            }


            .section-header,
            .section-body {
                padding: 18px;
            }


            footer {
                padding: 15px 10px;
            }

        }


        @media (max-width: 480px) {

            .sidebar {
                width: 60px;
                padding-left: 7px;
                padding-right: 7px;
            }


            .main {
                margin-left: 60px;
            }


            footer {
                margin-left: 60px;
            }


            .content {
                padding-left: 10px;
                padding-right: 10px;
            }


            .top-header {
                padding-left: 12px;
                padding-right: 12px;
            }


            .ministere img {
                width: 43px;
                height: 43px;
            }


            .admin-avatar {
                width: 38px;
                height: 38px;
            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
     ===================================================== -->

<aside class="sidebar">

    <div class="sidebar-logo">

        <img
            src="../images/dglp.jpg"
            alt="Logo DGLP"
        >

        <h2>DGLP</h2>

        <p>Administration</p>

    </div>


    <nav class="sidebar-menu">

        <a href="dashboard.php">

            <span class="menu-icon">⌂</span>

            <span>
                Tableau de bord
            </span>

        </a>


        <a href="offres.php">

            <span class="menu-icon">▣</span>

            <span>
                Offres
            </span>

        </a>


        <a href="candidatures.php">

            <span class="menu-icon">▤</span>

            <span>
                Candidatures
            </span>

        </a>


        <a
            href="classement.php"
            class="active"
        >

            <span class="menu-icon">★</span>

            <span>
                Classement
            </span>

        </a>


        <a href="historique.php">

            <span class="menu-icon">◷</span>

            <span>
                Historique
            </span>

        </a>


        <a href="deconnexion.php">

            <span class="menu-icon">↪</span>

            <span>
                Déconnexion
            </span>

        </a>

    </nav>

</aside>


<!-- =====================================================
     CONTENU PRINCIPAL
     ===================================================== -->

<div class="main">


    <!-- =================================================
         HEADER
         ================================================= -->

    <header class="top-header">

        <div class="ministere">

            <img
                src="../images/ministere.jpg"
                alt="Ministère"
            >

            <div class="ministere-text">

                <strong>
                    Ministère du Commerce, des PME/PMI
                    et de l'Entrepreneuriat
                </strong>

                <span>
                    Direction Générale de la Lutte contre la Pauvreté
                </span>

            </div>

        </div>


        <div class="admin-user">

            <div class="admin-avatar">

                <?= strtoupper(
                    substr(
                        $_SESSION["prenom_admin"] ?? "A",
                        0,
                        1
                    ) .
                    substr(
                        $_SESSION["nom_admin"] ?? "",
                        0,
                        1
                    )
                ) ?>

            </div>


            <div class="admin-user-info">

                <strong>

                    <?= htmlspecialchars(
                        $_SESSION["prenom_admin"] ?? ""
                    ) ?>

                    <?= htmlspecialchars(
                        $_SESSION["nom_admin"] ?? ""
                    ) ?>

                </strong>

                <span>
                    Administrateur
                </span>

            </div>

        </div>

    </header>


    <!-- =================================================
         CONTENU
         ================================================= -->

    <main class="content">


        <!-- =================================================
             TITRE
             ================================================= -->

        <div class="page-header">

            <div>

                <h1>
                    Classement des candidatures
                </h1>

                <p>
                    Gérez le classement des candidatures déclarées
                    éligibles après leur contrôle.
                </p>

                <p style="margin-top: 5px;">

                    Bienvenue
                    <strong>

                        <?= htmlspecialchars(
                            $_SESSION["prenom_admin"] ?? ""
                        ) ?>

                        <?= htmlspecialchars(
                            $_SESSION["nom_admin"] ?? ""
                        ) ?>

                    </strong>

                </p>

            </div>

        </div>


        <!-- =================================================
             MESSAGE
             ================================================= -->

        <?php if ($message !== ""): ?>

            <div class="message <?= htmlspecialchars($type_message) ?>">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             STATISTIQUES
             ================================================= -->

        <div class="stats">


            <div class="stat-card">

                <div class="stat-icon">
                    ✓
                </div>

                <div class="stat-info">

                    <span>
                        Candidatures éligibles
                    </span>

                    <strong>
                        <?= count($candidatures) ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    #
                </div>

                <div class="stat-info">

                    <span>
                        Classements enregistrés
                    </span>

                    <strong>
                        <?= count($classements) ?>
                    </strong>

                </div>

            </div>


        </div>


        <!-- =================================================
             CANDIDATURES ÉLIGIBLES
             ================================================= -->

        <section class="section-card">

            <div class="section-header">

                <h2>
                    Candidatures éligibles
                </h2>

                <p>
                    Ces candidatures peuvent maintenant être classées
                    selon leur score d'éligibilité.
                </p>

            </div>


            <div class="section-body">


                <?php if (empty($candidatures)): ?>


                    <div class="empty-state">

                        <div class="empty-icon">
                            ✓
                        </div>

                        <h3>
                            Aucune candidature éligible
                        </h3>

                        <p>
                            Aucune candidature éligible n'est actuellement
                            disponible pour le classement.
                        </p>

                    </div>


                <?php else: ?>


                    <div class="table-wrapper">

                        <table>

                            <thead>

                                <tr>

                                    <th>
                                        Candidature
                                    </th>

                                    <th>
                                        Candidat
                                    </th>

                                    <th>
                                        Offre
                                    </th>

                                    <th>
                                        Score
                                    </th>

                                    <th>
                                        Classement
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach ($candidatures as $candidature): ?>


                                <tr>


                                    <!-- CANDIDATURE -->

                                    <td>

                                        <div class="numero">

                                            <?= htmlspecialchars(
                                                $candidature["numero_candidature"]
                                            ) ?>

                                        </div>

                                    </td>


                                    <!-- CANDIDAT -->

                                    <td>

                                        <div class="candidate-name">

                                            <?= htmlspecialchars(
                                                $candidature["prenom_candidat"]
                                            ) ?>

                                            <?= htmlspecialchars(
                                                $candidature["nom_candidat"]
                                            ) ?>

                                        </div>

                                    </td>


                                    <!-- OFFRE -->

                                    <td>

                                        <div class="offer-name">

                                            <?= htmlspecialchars(
                                                $candidature["nom_offre"]
                                            ) ?>

                                        </div>

                                    </td>


                                    <!-- SCORE -->

                                    <td>

                                        <span class="score">

                                            <?= number_format(
                                                (float) $candidature["score_eligibilite"],
                                                2,
                                                ",",
                                                " "
                                            ) ?>

                                            %

                                        </span>

                                    </td>


                                    <!-- FORMULAIRE -->

                                    <td>

                                        <form
                                            method="POST"
                                            action="classement.php"
                                            class="form-classement"
                                        >

                                            <div class="form-title">

                                                <?php if ($candidature["id_classement"]): ?>

                                                    Modification du classement

                                                <?php else: ?>

                                                    Nouveau classement

                                                <?php endif; ?>

                                            </div>


                                            <input
                                                type="hidden"
                                                name="id_candidature"
                                                value="<?= htmlspecialchars(
                                                    $candidature["id_candidature"]
                                                ) ?>"
                                            >


                                            <div class="form-group">

                                                <label>
                                                    Rang
                                                </label>

                                                <input
                                                    type="number"
                                                    name="rang"
                                                    min="1"
                                                    required
                                                    value="<?= htmlspecialchars(
                                                        $candidature["rang"] ?? ""
                                                    ) ?>"
                                                    placeholder="Ex. 1"
                                                >

                                            </div>


                                            <div class="form-group">

                                                <label>
                                                    Statut
                                                </label>

                                                <select
                                                    name="statut"
                                                    required
                                                >

                                                    <option
                                                        value="Classé"
                                                        <?= (
                                                            ($candidature["statut_classement"] ?? "")
                                                            === "Classé"
                                                        )
                                                        ? "selected"
                                                        : ""
                                                        ?>
                                                    >
                                                        Classé
                                                    </option>


                                                    <option
                                                        value="En attente"
                                                        <?= (
                                                            ($candidature["statut_classement"] ?? "")
                                                            === "En attente"
                                                        )
                                                        ? "selected"
                                                        : ""
                                                        ?>
                                                    >
                                                        En attente
                                                    </option>


                                                    <option
                                                        value="Non retenu"
                                                        <?= (
                                                            ($candidature["statut_classement"] ?? "")
                                                            === "Non retenu"
                                                        )
                                                        ? "selected"
                                                        : ""
                                                        ?>
                                                    >
                                                        Non retenu
                                                    </option>

                                                </select>

                                            </div>


                                            <div class="form-group">

                                                <label>
                                                    Justification
                                                </label>

                                                <textarea
                                                    name="justification"
                                                    required
                                                    placeholder="Saisissez la justification du classement..."
                                                ><?= htmlspecialchars(
                                                    $candidature["justification"] ?? ""
                                                ) ?></textarea>

                                            </div>


                                            <button
                                                type="submit"
                                                class="btn-classement"
                                            >

                                                <?php if ($candidature["id_classement"]): ?>

                                                    Modifier le classement

                                                <?php else: ?>

                                                    Enregistrer le classement

                                                <?php endif; ?>

                                            </button>

                                        </form>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                <?php endif; ?>


            </div>

        </section>


        <!-- =================================================
             CLASSEMENTS ENREGISTRÉS
             ================================================= -->

        <section class="section-card">

            <div class="section-header">

                <h2>
                    Classements enregistrés
                </h2>

                <p>
                    Historique des candidatures ayant reçu un classement.
                </p>

            </div>


            <div class="section-body">


                <?php if (empty($classements)): ?>


                    <div class="empty-state">

                        <div class="empty-icon">
                            #
                        </div>

                        <h3>
                            Aucun classement enregistré
                        </h3>

                        <p>
                            Aucun classement n'a encore été enregistré.
                        </p>

                    </div>


                <?php else: ?>


                    <div class="table-wrapper">

                        <table>

                            <thead>

                                <tr>

                                    <th>
                                        Rang
                                    </th>

                                    <th>
                                        Candidature
                                    </th>

                                    <th>
                                        Candidat
                                    </th>

                                    <th>
                                        Offre
                                    </th>

                                    <th>
                                        Score
                                    </th>

                                    <th>
                                        Statut
                                    </th>

                                    <th>
                                        Justification
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach ($classements as $classement): ?>


                                <?php

                                $statutClassement =
                                    $classement["statut"] ?? "";

                                $classeBadge = "badge-attente";

                                if ($statutClassement === "Classé") {
                                    $classeBadge = "badge-classe";
                                } elseif ($statutClassement === "Non retenu") {
                                    $classeBadge = "badge-non-retenu";
                                }

                                ?>


                                <tr>


                                    <!-- RANG -->

                                    <td>

                                        <span class="rang">

                                            <?= htmlspecialchars(
                                                $classement["rang"]
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- CANDIDATURE -->

                                    <td>

                                        <span class="numero">

                                            <?= htmlspecialchars(
                                                $classement["numero_candidature"]
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- CANDIDAT -->

                                    <td>

                                        <span class="candidate-name">

                                            <?= htmlspecialchars(
                                                $classement["prenom_candidat"]
                                            ) ?>

                                            <?= htmlspecialchars(
                                                $classement["nom_candidat"]
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- OFFRE -->

                                    <td>

                                        <span class="offer-name">

                                            <?= htmlspecialchars(
                                                $classement["nom_offre"]
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- SCORE -->

                                    <td>

                                        <span class="score">

                                            <?= number_format(
                                                (float) $classement["score"],
                                                2,
                                                ",",
                                                " "
                                            ) ?>

                                            %

                                        </span>

                                    </td>


                                    <!-- STATUT -->

                                    <td>

                                        <span
                                            class="badge <?= $classeBadge ?>"
                                        >

                                            <?= htmlspecialchars(
                                                $statutClassement
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- JUSTIFICATION -->

                                    <td>

                                        <div class="justification">

                                            <?= nl2br(
                                                htmlspecialchars(
                                                    $classement["justification"] ?? ""
                                                )
                                            ) ?>

                                        </div>

                                    </td>


                                    <!-- DATE -->

                                    <td>

                                        <?= htmlspecialchars(
                                            $classement["date_classement"]
                                        ) ?>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                <?php endif; ?>


            </div>

        </section>


    </main>

</div>


<!-- =====================================================
     FOOTER
     ===================================================== -->

<footer>

    © 2026 Direction Générale de la Lutte contre la Pauvreté -
    Administration

</footer>


</body>

</html>