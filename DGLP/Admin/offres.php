<?php

session_start();
require_once "../../database.php";

/* =========================================================
   VÉRIFIER QUE L'ADMINISTRATEUR EST CONNECTÉ
   ========================================================= */

if (!isset($_SESSION["id_admin"])) {
    header("Location: connexion.php");
    exit;
}

/* =========================================================
   RÉCUPÉRER TOUTES LES OFFRES
   ========================================================= */

$requete = $pdo->query("
    SELECT
        id_offre,
        nom,
        description,
        date_creation,
        date_limite_candidature,
        nombre_place,
        critere,
        montant,
        document_supplementaire,
        statut,
        id_admin
    FROM offre
    ORDER BY id_offre DESC
");

$offres = $requete->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   FONCTION POUR LE BADGE DE STATUT
   ========================================================= */

function classeStatutOffre(string $statut): string
{
    switch ($statut) {

        case "ouverte":
            return "statut-ouverte";

        case "fermee":
            return "statut-fermee";

        case "brouillon":
            return "statut-brouillon";

        default:
            return "statut-autre";
    }
}


/* =========================================================
   NOM DE L'ADMINISTRATEUR
   ========================================================= */

$prenomAdmin = $_SESSION["prenom_admin"] ?? "";
$nomAdmin = $_SESSION["nom_admin"] ?? "";

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Gestion des offres - DGLP</title>


    <style>

        /* =====================================================
           RESET
           ===================================================== */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        /* =====================================================
           BODY
           ===================================================== */

        body {

            font-family: Arial, sans-serif;

            background:
                linear-gradient(
                    rgba(244, 246, 248, 0.94),
                    rgba(244, 246, 248, 0.94)
                ),
                url("../images/dglp.jpg")
                center center /
                420px auto
                no-repeat fixed;

            background-color: #f4f6f8;

            color: #263238;

            line-height: 1.6;

            min-height: 100vh;
        }


        a {
            text-decoration: none;
        }


        /* =====================================================
           SIDEBAR
           ===================================================== */

        .sidebar {

            position: fixed;

            top: 0;
            left: 0;
            bottom: 0;

            width: 255px;

            background: #123b68;

            color: white;

            padding: 25px 15px;

            box-shadow:
                4px 0 15px rgba(0, 0, 0, 0.10);

            z-index: 1000;

            overflow-y: auto;
        }


        .logo-zone {

            text-align: center;

            padding-bottom: 25px;

            border-bottom:
                1px solid rgba(255, 255, 255, 0.18);

            margin-bottom: 25px;
        }


        .logo-zone img {

            width: 92px;
            height: 92px;

            object-fit: cover;

            border-radius: 50%;

            background: white;

            padding: 5px;

            margin-bottom: 12px;

            box-shadow:
                0 4px 12px rgba(0, 0, 0, 0.20);
        }


        .logo-zone h2 {

            font-size: 20px;

            margin-bottom: 3px;

            letter-spacing: 0.5px;
        }


        .logo-zone p {

            font-size: 12px;

            color: rgba(255, 255, 255, 0.75);
        }


        /* =====================================================
           MENU
           ===================================================== */

        .menu {

            display: flex;

            flex-direction: column;

            gap: 7px;
        }


        .menu a {

            display: flex;

            align-items: center;

            gap: 12px;

            color: white;

            padding: 13px 15px;

            border-radius: 8px;

            font-size: 14px;

            transition: 0.2s ease;
        }


        .menu a:hover {

            background:
                rgba(255, 255, 255, 0.10);

            transform: translateX(2px);
        }


        .menu a.active {

            background: white;

            color: #123b68;

            font-weight: bold;

            box-shadow:
                0 3px 8px rgba(0, 0, 0, 0.12);
        }


        .menu-icon {

            width: 22px;

            text-align: center;

            font-size: 16px;
        }


        /* =====================================================
           CONTENU PRINCIPAL
           ===================================================== */

        .page {

            margin-left: 255px;

            min-height: 100vh;

            display: flex;

            flex-direction: column;
        }


        /* =====================================================
           HEADER
           ===================================================== */

        .topbar {

            background: white;

            min-height: 82px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 15px 35px;

            border-bottom:
                1px solid #e3e7eb;

            position: sticky;

            top: 0;

            z-index: 500;
        }


        .ministere {

            display: flex;

            align-items: center;

            gap: 14px;
        }


        .ministere img {

            width: 52px;
            height: 52px;

            object-fit: cover;

            border-radius: 50%;

            border: 2px solid #e8edf2;
        }


        .ministere-text h1 {

            font-size: 17px;

            color: #123b68;

            margin-bottom: 2px;
        }


        .ministere-text p {

            font-size: 12px;

            color: #6c757d;
        }


        .admin-info {

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


        .admin-name {

            font-size: 13px;

            color: #263238;
        }


        .admin-name strong {

            display: block;

            color: #123b68;
        }


        .admin-name span {

            color: #7a858e;

            font-size: 11px;
        }


        /* =====================================================
           MAIN
           ===================================================== */

        main {

            flex: 1;

            padding: 35px;
        }


        .page-title {

            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 28px;
        }


        .page-title h2 {

            color: #123b68;

            font-size: 28px;

            margin-bottom: 5px;
        }


        .page-title p {

            color: #6c757d;

            font-size: 14px;
        }


        .btn-principal {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            background: #008c4a;

            color: white;

            padding: 12px 18px;

            border-radius: 8px;

            font-size: 14px;

            font-weight: bold;

            box-shadow:
                0 3px 8px rgba(0, 140, 74, 0.20);

            transition: 0.2s ease;

            white-space: nowrap;
        }


        .btn-principal:hover {

            background: #00763f;

            transform: translateY(-1px);
        }


        /* =====================================================
           STATISTIQUE
           ===================================================== */

        .resume {

            display: grid;

            grid-template-columns:
                repeat(3, minmax(180px, 1fr));

            gap: 18px;

            margin-bottom: 28px;
        }


        .resume-card {

            background: white;

            border: 1px solid #e3e8ed;

            border-radius: 12px;

            padding: 20px;

            box-shadow:
                0 3px 12px rgba(18, 59, 104, 0.06);
        }


        .resume-label {

            color: #71808d;

            font-size: 13px;

            margin-bottom: 6px;
        }


        .resume-value {

            color: #123b68;

            font-size: 27px;

            font-weight: bold;
        }


        .resume-card.ouverte .resume-value {

            color: #008c4a;
        }


        .resume-card.fermee .resume-value {

            color: #c0392b;
        }


        /* =====================================================
           LISTE DES OFFRES
           ===================================================== */

        .section-title {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 15px;
        }


        .section-title h3 {

            color: #123b68;

            font-size: 20px;
        }


        .section-title span {

            color: #7b8791;

            font-size: 13px;
        }


        .offres-grid {

            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 22px;
        }


        /* =====================================================
           CARTE OFFRE
           ===================================================== */

        .carte-offre {

            background: white;

            border: 1px solid #e2e7eb;

            border-radius: 13px;

            padding: 24px;

            box-shadow:
                0 4px 14px rgba(18, 59, 104, 0.07);

            display: flex;

            flex-direction: column;

            transition: 0.2s ease;
        }


        .carte-offre:hover {

            transform: translateY(-2px);

            box-shadow:
                0 7px 18px rgba(18, 59, 104, 0.10);
        }


        .offre-header {

            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            gap: 15px;

            margin-bottom: 16px;
        }


        .offre-titre {

            flex: 1;
        }


        .offre-titre h3 {

            color: #123b68;

            font-size: 19px;

            margin-bottom: 5px;
        }


        .offre-id {

            color: #7b8791;

            font-size: 12px;
        }


        /* =====================================================
           BADGES STATUT
           ===================================================== */

        .statut {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: bold;

            text-transform: uppercase;

            white-space: nowrap;
        }


        .statut-ouverte {

            background: #e5f6ed;

            color: #08783f;
        }


        .statut-fermee {

            background: #fbe9e7;

            color: #b42318;
        }


        .statut-brouillon {

            background: #fff4d6;

            color: #8a6100;
        }


        .statut-autre {

            background: #edf0f2;

            color: #56616a;
        }


        /* =====================================================
           DESCRIPTION
           ===================================================== */

        .offre-description {

            color: #53616d;

            font-size: 14px;

            margin-bottom: 18px;

            display: -webkit-box;

            -webkit-line-clamp: 4;

            -webkit-box-orient: vertical;

            overflow: hidden;
        }


        /* =====================================================
           INFORMATIONS
           ===================================================== */

        .infos-offre {

            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 10px;

            margin-bottom: 18px;
        }


        .info-item {

            background: #f6f8fa;

            border-radius: 8px;

            padding: 11px;
        }


        .info-label {

            display: block;

            color: #7a8791;

            font-size: 11px;

            margin-bottom: 3px;
        }


        .info-value {

            color: #263238;

            font-size: 13px;

            font-weight: bold;
        }


        /* =====================================================
           DOCUMENTS
           ===================================================== */

        .documents {

            padding: 11px 13px;

            background: #f5f8fb;

            border-left: 3px solid #123b68;

            border-radius: 5px;

            font-size: 12px;

            color: #52606b;

            margin-bottom: 15px;
        }


        .documents.demande {

            border-left-color: #008c4a;

            background: #edf9f3;
        }


        /* =====================================================
           CRITÈRES
           ===================================================== */

        .criteres {

            margin-bottom: 18px;

            padding-top: 5px;
        }


        .criteres strong {

            display: block;

            color: #123b68;

            font-size: 13px;

            margin-bottom: 5px;
        }


        .criteres p {

            color: #65727d;

            font-size: 12px;

            max-height: 65px;

            overflow: hidden;
        }


        /* =====================================================
           ACTIONS
           ===================================================== */

        .offre-actions {

            display: flex;

            flex-wrap: wrap;

            gap: 8px;

            padding-top: 16px;

            border-top: 1px solid #edf0f2;

            margin-top: auto;
        }


        .btn-action {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 9px 12px;

            border-radius: 7px;

            font-size: 12px;

            font-weight: bold;

            transition: 0.2s ease;
        }


        .btn-modifier {

            background: #eaf2fb;

            color: #123b68;
        }


        .btn-modifier:hover {

            background: #dceaf8;
        }


        .btn-publier {

            background: #e7f7ef;

            color: #08783f;
        }


        .btn-publier:hover {

            background: #d7f0e4;
        }


        .btn-fermer {

            background: #fff3df;

            color: #9a5b00;
        }


        .btn-fermer:hover {

            background: #ffe9c5;
        }


        .btn-supprimer {

            background: #fbe9e7;

            color: #b42318;
        }


        .btn-supprimer:hover {

            background: #f8dcd9;
        }


        /* =====================================================
           ÉTAT VIDE
           ===================================================== */

        .empty-state {

            background: white;

            border: 1px solid #e2e7eb;

            border-radius: 13px;

            padding: 55px 25px;

            text-align: center;

            box-shadow:
                0 4px 14px rgba(18, 59, 104, 0.06);
        }


        .empty-icon {

            width: 60px;
            height: 60px;

            margin: 0 auto 15px;

            border-radius: 50%;

            background: #eaf2fb;

            color: #123b68;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 25px;

            font-weight: bold;
        }


        .empty-state h3 {

            color: #123b68;

            margin-bottom: 7px;
        }


        .empty-state p {

            color: #71808d;

            font-size: 14px;

            margin-bottom: 20px;
        }


        /* =====================================================
           FOOTER
           ===================================================== */

        footer {

            background: #123b68;

            color: rgba(255, 255, 255, 0.85);

            text-align: center;

            padding: 18px;

            font-size: 12px;

            margin-top: 20px;
        }


        /* =====================================================
           RESPONSIVE
           ===================================================== */

        @media (max-width: 1100px) {

            .sidebar {

                width: 75px;

                padding: 20px 8px;
            }


            .logo-zone h2,
            .logo-zone p,
            .menu span {

                display: none;
            }


            .logo-zone {

                padding-bottom: 18px;
            }


            .logo-zone img {

                width: 52px;
                height: 52px;
            }


            .menu a {

                justify-content: center;

                padding: 13px 8px;
            }


            .menu-icon {

                font-size: 18px;
            }


            .page {

                margin-left: 75px;
            }


            .offres-grid {

                grid-template-columns: 1fr;
            }
        }


        @media (max-width: 800px) {

            .topbar {

                padding: 12px 20px;
            }


            .ministere-text {

                display: none;
            }


            main {

                padding: 25px 20px;
            }


            .resume {

                grid-template-columns: 1fr;
            }


            .page-title {

                flex-direction: column;
            }


            .btn-principal {

                width: 100%;
            }
        }


        @media (max-width: 550px) {

            .page {

                margin-left: 0;
            }


            .sidebar {

                position: relative;

                width: 100%;

                height: auto;

                padding: 10px;

                overflow: visible;
            }


            .logo-zone {

                display: flex;

                align-items: center;

                gap: 10px;

                border-bottom: none;

                margin-bottom: 8px;

                padding-bottom: 5px;
            }


            .logo-zone img {

                width: 45px;
                height: 45px;

                margin: 0;
            }


            .logo-zone h2,
            .logo-zone p {

                display: block;

                text-align: left;
            }


            .menu {

                display: grid;

                grid-template-columns:
                    repeat(4, 1fr);

                gap: 5px;
            }


            .menu a {

                padding: 8px 4px;

                flex-direction: column;

                gap: 2px;

                font-size: 9px;
            }


            .menu span {

                display: block;
            }


            .menu-icon {

                font-size: 15px;
            }


            .topbar {

                position: relative;

                padding: 12px 15px;
            }


            .admin-name {

                display: none;
            }


            main {

                padding: 20px 15px;
            }


            .page-title h2 {

                font-size: 23px;
            }


            .infos-offre {

                grid-template-columns: 1fr;
            }


            .offre-header {

                flex-direction: column;
            }


            .offre-actions {

                flex-direction: column;
            }


            .btn-action {

                width: 100%;
            }
        }

    </style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
     ===================================================== -->

<aside class="sidebar">


    <div class="logo-zone">

        <img
            src="../images/dglp.jpg"
            alt="Logo DGLP"
        >

        <div>

            <h2>DGLP</h2>

            <p>Administration</p>

        </div>

    </div>


    <nav class="menu">


        <a href="dashboard.php">

            <span class="menu-icon">⌂</span>

            <span>
                Tableau de bord
            </span>

        </a>


        <a
            href="offres.php"
            class="active"
        >

            <span class="menu-icon">▣</span>

            <span>
                Offres
            </span>

        </a>


        <a href="candidatures.php">

            <span class="menu-icon">☷</span>

            <span>
                Candidatures
            </span>

        </a>


        <a href="classement.php">

            <span class="menu-icon">★</span>

            <span>
                Classement
            </span>

        </a>


        <a href="decision.php">

            <span class="menu-icon">✓</span>

            <span>
                Décisions
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
     PAGE
     ===================================================== -->

<div class="page">


    <!-- =================================================
         HEADER
         ================================================= -->

    <header class="topbar">


        <div class="ministere">

            <img
                src="../images/ministere.jpg"
                alt="Ministère"
            >

            <div class="ministere-text">

                <h1>
                    Ministère du Commerce,
                    des PME/PMI et de l'Entrepreneuriat
                </h1>

                <p>
                    Direction Générale de la Lutte contre la Pauvreté
                </p>

            </div>

        </div>


        <div class="admin-info">

            <div class="admin-avatar">

                <?= htmlspecialchars(
                    strtoupper(
                        substr($prenomAdmin, 0, 1)
                    )
                ) ?>

                <?= htmlspecialchars(
                    strtoupper(
                        substr($nomAdmin, 0, 1)
                    )
                ) ?>

            </div>


            <div class="admin-name">

                <strong>

                    <?= htmlspecialchars($prenomAdmin) ?>

                    <?= htmlspecialchars($nomAdmin) ?>

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

    <main>


        <div class="page-title">


            <div>

                <h2>
                    Gestion des offres
                </h2>

                <p>
                    Créez, consultez et gérez les offres de services
                    proposées par la DGLP.
                </p>

            </div>


            <a
                href="ajouter-offre.php"
                class="btn-principal"
            >
                + Créer une offre
            </a>


        </div>


        <!-- =================================================
             RÉSUMÉ
             ================================================= -->

        <?php

        $totalOffres = count($offres);

        $offresOuvertes = 0;

        $offresFermees = 0;

        foreach ($offres as $offreResume) {

            if ($offreResume["statut"] === "ouverte") {
                $offresOuvertes++;
            }

            if ($offreResume["statut"] === "fermee") {
                $offresFermees++;
            }
        }

        ?>


        <section class="resume">


            <div class="resume-card">

                <div class="resume-label">
                    Total des offres
                </div>

                <div class="resume-value">
                    <?= $totalOffres ?>
                </div>

            </div>


            <div class="resume-card ouverte">

                <div class="resume-label">
                    Offres ouvertes
                </div>

                <div class="resume-value">
                    <?= $offresOuvertes ?>
                </div>

            </div>


            <div class="resume-card fermee">

                <div class="resume-label">
                    Offres fermées
                </div>

                <div class="resume-value">
                    <?= $offresFermees ?>
                </div>

            </div>


        </section>


        <!-- =================================================
             TITRE LISTE
             ================================================= -->

        <div class="section-title">

            <h3>
                Liste des offres
            </h3>

            <span>
                <?= $totalOffres ?>
                offre<?= $totalOffres > 1 ? "s" : "" ?>
            </span>

        </div>


        <!-- =================================================
             LISTE DES OFFRES
             ================================================= -->

        <?php if (empty($offres)): ?>


            <div class="empty-state">


                <div class="empty-icon">
                    +
                </div>


                <h3>
                    Aucune offre disponible
                </h3>


                <p>
                    Aucune offre n'a encore été créée
                    dans la plateforme.
                </p>


                <a
                    href="ajouter-offre.php"
                    class="btn-principal"
                >
                    + Créer la première offre
                </a>


            </div>


        <?php else: ?>


            <section class="offres-grid">


                <?php foreach ($offres as $offre): ?>


                    <article class="carte-offre">


                        <!-- =================================
                             EN-TÊTE OFFRE
                             ================================= -->

                        <div class="offre-header">


                            <div class="offre-titre">

                                <h3>

                                    <?= htmlspecialchars(
                                        $offre["nom"]
                                    ) ?>

                                </h3>

                                <div class="offre-id">

                                    Offre
                                    #<?= (int)$offre["id_offre"] ?>

                                </div>

                            </div>


                            <span class="statut
                                <?= htmlspecialchars(
                                    classeStatutOffre(
                                        $offre["statut"]
                                    )
                                ) ?>"
                            >

                                <?= htmlspecialchars(
                                    $offre["statut"]
                                ) ?>

                            </span>


                        </div>


                        <!-- =================================
                             DESCRIPTION
                             ================================= -->

                        <div class="offre-description">

                            <?= nl2br(
                                htmlspecialchars(
                                    $offre["description"]
                                )
                            ) ?>

                        </div>


                        <!-- =================================
                             INFORMATIONS
                             ================================= -->

                        <div class="infos-offre">


                            <div class="info-item">

                                <span class="info-label">
                                    Date de création
                                </span>

                                <span class="info-value">

                                    <?= htmlspecialchars(
                                        $offre["date_creation"]
                                    ) ?>

                                </span>

                            </div>


                            <div class="info-item">

                                <span class="info-label">
                                    Date limite
                                </span>

                                <span class="info-value">

                                    <?= htmlspecialchars(
                                        $offre[
                                            "date_limite_candidature"
                                        ]
                                    ) ?>

                                </span>

                            </div>


                            <div class="info-item">

                                <span class="info-label">
                                    Nombre de places
                                </span>

                                <span class="info-value">

                                    <?= (int)$offre[
                                        "nombre_place"
                                    ] ?>

                                    place<?= (
                                        (int)$offre[
                                            "nombre_place"
                                        ] > 1
                                    ) ? "s" : "" ?>

                                </span>

                            </div>


                            <div class="info-item">

                                <span class="info-label">
                                    Montant
                                </span>

                                <span class="info-value">

                                    <?php if (
                                        $offre["montant"] !== null
                                        && $offre["montant"] !== ""
                                    ): ?>

                                        <?= htmlspecialchars(
                                            number_format(
                                                (float)$offre["montant"],
                                                0,
                                                ",",
                                                " "
                                            )
                                        ) ?>

                                        FCFA

                                    <?php else: ?>

                                        Non renseigné

                                    <?php endif; ?>

                                </span>

                            </div>


                        </div>


                        <!-- =================================
                             DOCUMENTS
                             ================================= -->

                        <?php if (
                            (int)$offre[
                                "document_supplementaire"
                            ] === 1
                        ): ?>


                            <div class="documents demande">

                                <strong>
                                    Documents complémentaires :
                                </strong>

                                Des documents complémentaires
                                sont demandés aux candidats.

                            </div>


                        <?php else: ?>


                            <div class="documents">

                                <strong>
                                    Documents complémentaires :
                                </strong>

                                Aucun document complémentaire
                                n'est demandé.

                            </div>


                        <?php endif; ?>


                        <!-- =================================
                             CRITÈRES
                             ================================= -->

                        <?php if (
                            !empty($offre["critere"])
                        ): ?>


                            <div class="criteres">

                                <strong>
                                    Critères d'éligibilité
                                </strong>

                                <p>

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $offre["critere"]
                                        )
                                    ) ?>

                                </p>

                            </div>


                        <?php endif; ?>


                        <!-- =================================
                             ACTIONS
                             ================================= -->

                        <div class="offre-actions">


                            <a
                                href="modifier-offre.php?id=<?= (int)$offre["id_offre"] ?>"
                                class="btn-action btn-modifier"
                            >
                                Modifier
                            </a>


                            <?php if (
                                $offre["statut"] !== "ouverte"
                            ): ?>


                                <a
                                    href="publier-offre.php?id=<?= (int)$offre["id_offre"] ?>"
                                    class="btn-action btn-publier"
                                    onclick="return confirm('Voulez-vous publier cette offre ?');"
                                >
                                    Publier
                                </a>


                            <?php else: ?>


                                <a
                                    href="fermer-offre.php?id=<?= (int)$offre["id_offre"] ?>"
                                    class="btn-action btn-fermer"
                                    onclick="return confirm('Voulez-vous fermer cette offre ?');"
                                >
                                    Fermer
                                </a>


                            <?php endif; ?>


                            <a
                                href="supprimer-offre.php?id=<?= (int)$offre["id_offre"] ?>"
                                class="btn-action btn-supprimer"
                                onclick="return confirm('Voulez-vous vraiment supprimer cette offre ?');"
                            >
                                Supprimer
                            </a>


                        </div>


                    </article>


                <?php endforeach; ?>


            </section>


        <?php endif; ?>


    </main>


    <!-- =================================================
         FOOTER
         ================================================= -->

    <footer>

        © 2026 Direction Générale de la Lutte contre la Pauvreté

    </footer>


</div>


</body>

</html>