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
   STATISTIQUES
========================================================= */

/* Nombre total d'offres */
$requete = $pdo->query("
    SELECT COUNT(*)
    FROM offre
");

$total_offres = (int) $requete->fetchColumn();


/* Nombre total de candidatures */
$requete = $pdo->query("
    SELECT COUNT(*)
    FROM candidature
");

$total_candidatures = (int) $requete->fetchColumn();


/* Nombre de candidatures en attente */
$requete = $pdo->query("
    SELECT COUNT(*)
    FROM candidature
    WHERE statut = 'en attente'
");

$a_examiner = (int) $requete->fetchColumn();


/* Nombre de candidatures éligibles */
$requete = $pdo->query("
    SELECT COUNT(*)
    FROM controle_eligibilite
    WHERE statut = 'Éligible'
");

$eligibles = (int) $requete->fetchColumn();


/* Nombre de candidatures non éligibles */
$requete = $pdo->query("
    SELECT COUNT(*)
    FROM controle_eligibilite
    WHERE statut = 'Non éligible'
");

$non_eligibles = (int) $requete->fetchColumn();


/* =========================================================
   DERNIÈRES CANDIDATURES
========================================================= */

$requete = $pdo->query("
    SELECT
        c.id_candidature,
        c.date_candidature,
        c.statut,

        ca.nom AS nom_candidat,
        ca.prenom AS prenom_candidat,

        o.description AS offre

    FROM candidature c

    INNER JOIN candidat ca
        ON c.id_candidat = ca.id_candidat

    INNER JOIN offre o
        ON c.id_offre = o.id_offre

    WHERE c.statut = 'en attente'

    ORDER BY c.date_candidature DESC

    LIMIT 10
");

$candidatures_recentes = $requete->fetchAll(PDO::FETCH_ASSOC);

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
        Tableau de bord - DGLP
    </title>


    <!-- =====================================================
         CSS DU DASHBOARD
    ====================================================== -->

    <style>

        /* =====================================================
           RESET
        ====================================================== */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f4f6f8;
            color: #263238;
        }


        a {
            text-decoration: none;
        }


        /* =====================================================
           CONTENEUR PRINCIPAL
        ====================================================== */

        .admin-container {
            display: flex;
            min-height: 100vh;
        }


        /* =====================================================
           MENU LATÉRAL
        ====================================================== */

        .sidebar {
            width: 255px;
            min-height: 100vh;

            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;

            background-color: #123b68;
            color: white;

            display: flex;
            flex-direction: column;

            box-shadow:
                3px 0 15px rgba(0, 0, 0, 0.08);

            z-index: 100;
        }


        /* =====================================================
           LOGO DGLP
        ====================================================== */

        .sidebar-logo {
            padding: 25px 20px 20px;

            text-align: center;

            border-bottom:
                1px solid rgba(255, 255, 255, 0.15);
        }


        .sidebar-logo img {
            width: 105px;
            height: 105px;

            object-fit: contain;

            border-radius: 50%;

            background-color: white;

            padding: 5px;
        }


        .sidebar-logo h2 {
            margin-top: 12px;

            color: white;

            font-size: 22px;

            letter-spacing: 1px;
        }


        .sidebar-logo p {
            margin-top: 4px;

            color: white;

            font-size: 13px;

            opacity: 0.75;
        }


        /* =====================================================
           MENU
        ====================================================== */

        .sidebar-menu {
            padding: 20px 12px;

            flex: 1;
        }


        .menu-item {
            display: flex;

            align-items: center;

            gap: 14px;

            padding: 13px 16px;

            margin-bottom: 7px;

            color: white;

            border-radius: 8px;

            font-size: 14px;

            transition: 0.25s;
        }


        .menu-item:hover {
            background-color:
                rgba(255, 255, 255, 0.12);

            transform: translateX(3px);

            color: white;
        }


        .menu-item.active {
            background-color: white;

            color: #123b68;

            font-weight: bold;
        }


        .menu-item.active:hover {
            color: #123b68;
        }


        .menu-icon {
            width: 25px;

            text-align: center;

            font-size: 20px;
        }


        .menu-separator {
            height: 1px;

            background-color:
                rgba(255, 255, 255, 0.15);

            margin: 20px 10px;
        }


        .menu-item.logout {
            color: #ffe0e0;
        }


        /* =====================================================
           PIED DU MENU
        ====================================================== */

        .sidebar-footer {
            padding: 18px;

            text-align: center;

            border-top:
                1px solid rgba(255, 255, 255, 0.15);

            font-size: 11px;

            opacity: 0.75;
        }


        .sidebar-footer strong {
            display: block;

            margin-top: 5px;
        }


        /* =====================================================
           CONTENU PRINCIPAL
        ====================================================== */

        .main-content {
            margin-left: 255px;

            width:
                calc(100% - 255px);

            min-height: 100vh;
        }


        /* =====================================================
           HEADER
        ====================================================== */

        .top-header {
            min-height: 105px;

            background-color: white;

            border-bottom:
                1px solid #e5e7eb;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 12px 35px;

            gap: 20px;
        }


        /* =====================================================
           MINISTÈRE
        ====================================================== */

        .ministere {
            display: flex;

            align-items: center;

            gap: 20px;
        }


        .ministere img {
            width: 105px;
            height: 105px;

            object-fit: contain;
        }


        .ministere-text {
            border-left:
                1px solid #d8dde3;

            padding-left: 15px;
        }


        .ministere-text span {
            font-size: 9px;

            font-weight: bold;

            color: #687586;

            letter-spacing: 1px;
        }


        .ministere-text h1 {
            margin: 3px 0;

            font-size: 15px;

            color: #183b68;

            text-transform: uppercase;

            max-width: 550px;

            line-height: 1.3;
        }


        .ministere-text p {
            margin-top: 3px;

            font-size: 12px;

            color: #008c4a;

            font-weight: bold;
        }


        /* =====================================================
           PROFIL ADMINISTRATEUR
        ====================================================== */

        .admin-profile {
            display: flex;

            align-items: center;

            gap: 10px;
        }


        .profile-icon {
            width: 42px;
            height: 42px;

            border-radius: 50%;

            background-color: #008c4a;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: bold;
        }


        .profile-info {
            display: flex;

            flex-direction: column;
        }


        .profile-info strong {
            font-size: 13px;

            color: #263238;
        }


        .profile-info span {
            font-size: 11px;

            color: #7a8491;

            margin-top: 3px;
        }


        /* =====================================================
           CONTENU DU DASHBOARD
        ====================================================== */

        .dashboard-content {
            padding: 30px 35px;
        }


        .page-title {
            margin-bottom: 25px;
        }


        .page-title h2 {
            font-size: 27px;

            color: #183b68;

            margin-bottom: 5px;
        }


        .page-title p {
            color: #737e8c;

            font-size: 13px;
        }


        /* =====================================================
           CARTES STATISTIQUES
        ====================================================== */

        .stats-grid {
            display: grid;

            grid-template-columns:
                repeat(5, 1fr);

            gap: 16px;

            margin-bottom: 28px;
        }


        .stat-card {
            background-color: white;

            border-radius: 12px;

            padding: 20px;

            display: flex;

            align-items: center;

            gap: 15px;

            border:
                1px solid #e8ebef;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, 0.04);

            transition: 0.25s;
        }


        .stat-card:hover {
            transform: translateY(-3px);

            box-shadow:
                0 7px 20px
                rgba(0, 0, 0, 0.08);
        }


        .stat-icon {
            width: 48px;
            height: 48px;

            flex-shrink: 0;

            border-radius: 10px;

            background-color: #eaf1f8;

            color: #123b68;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 23px;

            font-weight: bold;
        }


        .stat-information {
            display: flex;

            flex-direction: column;
        }


        .stat-information span {
            color: #697586;

            font-size: 12px;
        }


        .stat-information strong {
            font-size: 26px;

            color: #183b68;

            margin-top: 3px;
        }


        .stat-information small {
            color: #9aa2ad;

            font-size: 9px;

            margin-top: 2px;
        }


        /* ÉLIGIBLES */

        .stat-card.eligible .stat-icon {
            background-color: #e5f5ec;

            color: #008c4a;
        }


        /* NON ÉLIGIBLES */

        .stat-card.non-eligible .stat-icon {
            background-color: #fdeaea;

            color: #c0392b;
        }


        /* =====================================================
           PANEL CANDIDATURES
        ====================================================== */

        .dashboard-panel {
            background-color: white;

            border-radius: 12px;

            border:
                1px solid #e7eaee;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, 0.04);

            overflow: hidden;
        }


        .panel-header {
            padding: 22px 25px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            border-bottom:
                1px solid #edf0f2;
        }


        .panel-header h3 {
            font-size: 17px;

            color: #183b68;

            margin-bottom: 5px;
        }


        .panel-header p {
            color: #7b8491;

            font-size: 12px;
        }


        /* =====================================================
           BOUTON VOIR TOUTES
        ====================================================== */

        .btn-view-all {
            background-color: #123b68;

            color: white;

            padding: 9px 15px;

            border-radius: 7px;

            font-size: 12px;

            transition: 0.2s;
        }


        .btn-view-all:hover {
            background-color: #0d2d50;

            color: white;
        }


        /* =====================================================
           TABLEAU
        ====================================================== */

        .table-container {
            width: 100%;

            overflow-x: auto;
        }


        .candidatures-table {
            width: 100%;

            border-collapse: collapse;

            background-color: white;
        }


        .candidatures-table th {
            background-color: #f7f9fb;

            padding: 14px 20px;

            text-align: left;

            font-size: 11px;

            color: #687586;

            text-transform: uppercase;

            letter-spacing: 0.4px;
        }


        .candidatures-table td {
            padding: 15px 20px;

            border-top:
                1px solid #edf0f2;

            font-size: 12px;

            color: #4b5563;
        }


        .candidatures-table tbody tr {
            transition: 0.2s;
        }


        .candidatures-table tbody tr:hover {
            background-color: #f9fbfc;
        }


        /* =====================================================
           NUMÉRO CANDIDATURE
        ====================================================== */

        .numero-candidature {
            color: #183b68;

            font-size: 11px;
        }


        /* =====================================================
           NOM DU CANDIDAT
        ====================================================== */

        .candidate-name {
            display: flex;

            align-items: center;

            gap: 10px;
        }


        .candidate-avatar {
            width: 32px;
            height: 32px;

            border-radius: 50%;

            background-color: #008c4a;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 11px;

            font-weight: bold;
        }


        .offre-name {
            display: block;

            max-width: 220px;
        }


        /* =====================================================
           STATUT
        ====================================================== */

        .status {
            display: inline-block;

            padding: 5px 10px;

            border-radius: 20px;

            font-size: 10px;

            font-weight: 600;
        }


        .status.pending {
            background-color: #fff4d8;

            color: #a66b00;
        }


        /* =====================================================
           BOUTON EXAMINER
        ====================================================== */

        .btn-examiner {
            display: inline-block;

            background-color: #eaf1f8;

            color: #123b68;

            padding: 7px 12px;

            border-radius: 6px;

            font-size: 11px;

            font-weight: 600;

            transition: 0.2s;
        }


        .btn-examiner:hover {
            background-color: #123b68;

            color: white;
        }


        /* =====================================================
           AUCUNE CANDIDATURE
        ====================================================== */

        .empty-state {
            text-align: center;

            padding: 55px 20px;
        }


        .empty-icon {
            width: 55px;
            height: 55px;

            margin: auto;

            border-radius: 50%;

            background-color: #e5f5ec;

            color: #008c4a;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 25px;
        }


        .empty-state h4 {
            margin-top: 15px;

            color: #183b68;
        }


        .empty-state p {
            margin-top: 6px;

            color: #7b8491;

            font-size: 12px;
        }


        /* =====================================================
           FOOTER
        ====================================================== */

        .admin-footer {
            display: flex;

            justify-content: space-between;

            padding: 20px 35px;

            margin-top: 10px;

            color: #8b949e;

            font-size: 11px;
        }


        /* =====================================================
           RESPONSIVE
        ====================================================== */

        @media (max-width: 1200px) {

            .stats-grid {
                grid-template-columns:
                    repeat(3, 1fr);
            }

        }


        @media (max-width: 900px) {

            .sidebar {
                width: 75px;
            }


            .sidebar-logo h2,
            .sidebar-logo p,
            .menu-item span:not(.menu-icon),
            .sidebar-footer {
                display: none;
            }


            .sidebar-logo img {
                width: 50px;
                height: 50px;
            }


            .menu-item {
                justify-content: center;

                padding: 14px 5px;
            }


            .main-content {
                margin-left: 75px;

                width:
                    calc(100% - 75px);
            }


            .ministere-text {
                display: none;
            }

        }


        @media (max-width: 700px) {

            .top-header {
                padding: 10px 15px;
            }


            .admin-profile .profile-info {
                display: none;
            }


            .dashboard-content {
                padding: 20px 15px;
            }


            .stats-grid {
                grid-template-columns: 1fr;
            }


            .panel-header {
                align-items: flex-start;

                gap: 15px;

                flex-direction: column;
            }


            .admin-footer {
                flex-direction: column;

                gap: 5px;

                padding: 20px 15px;
            }

        }

    </style>

</head>


<body>


<div class="admin-container">


    <!-- =====================================================
         MENU LATÉRAL
    ====================================================== -->

    <aside class="sidebar">


        <!-- LOGO DGLP -->

        <div class="sidebar-logo">

            <img
                src="../images/dglp.jpg"
                alt="Logo de la Direction Générale de la Lutte contre la Pauvreté"
            >

            <h2>DGLP</h2>

            <p>
                Administration
            </p>

        </div>


        <!-- MENU -->

        <nav class="sidebar-menu">


            <a
                href="dashboard.php"
                class="menu-item active"
            >

                <span class="menu-icon">
                    ⌂
                </span>

                <span>
                    Tableau de bord
                </span>

            </a>


            <a
                href="offres.php"
                class="menu-item"
            >

                <span class="menu-icon">
                    ▣
                </span>

                <span>
                    Offres
                </span>

            </a>


            <a
                href="candidatures.php"
                class="menu-item"
            >

                <span class="menu-icon">
                    ♙
                </span>

                <span>
                    Candidatures
                </span>

            </a>


            <a
                href="classement.php"
                class="menu-item"
            >

                <span class="menu-icon">
                    ▤
                </span>

                <span>
                    Classement
                </span>

            </a>


            <a
                href="historique.php"
                class="menu-item"
            >

                <span class="menu-icon">
                    ◷
                </span>

                <span>
                    Historique
                </span>

            </a>


            <div class="menu-separator"></div>


            <a
                href="deconnexion.php"
                class="menu-item logout"
            >

                <span class="menu-icon">
                    ⇥
                </span>

                <span>
                    Déconnexion
                </span>

            </a>


        </nav>


        <!-- FOOTER MENU -->

        <div class="sidebar-footer">

            <p>
                Plateforme administrative
            </p>

            <strong>
                DGLP © 2026
            </strong>

        </div>


    </aside>



    <!-- =====================================================
         CONTENU PRINCIPAL
    ====================================================== -->

    <div class="main-content">


        <!-- =================================================
             EN-TÊTE
        ================================================== -->

        <header class="top-header">


            <!-- MINISTÈRE -->

            <div class="ministere">


                <img
                    src="../images/ministere.jpg"
                    alt="Logo du Ministère de l'Entrepreneuriat, du Commerce et des PME/PMI"
                >


                <div class="ministere-text">

                    <span>
                        RÉPUBLIQUE GABONAISE
                    </span>


                    <h1>
                        Ministère de l'Entrepreneuriat,
                        du Commerce et des PME/PMI
                    </h1>


                    <p>
                        Direction Générale de la Lutte contre la Pauvreté
                    </p>

                </div>

            </div>



            <!-- ADMINISTRATEUR -->

            <div class="admin-profile">


                <div class="profile-icon">

                    <?= strtoupper(
                        substr(
                            $_SESSION["prenom_admin"],
                            0,
                            1
                        )
                    ) ?>

                </div>


                <div class="profile-info">

                    <strong>

                        <?= htmlspecialchars(
                            $_SESSION["prenom_admin"]
                        ) ?>

                        <?= htmlspecialchars(
                            $_SESSION["nom_admin"]
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
        ================================================== -->

        <main class="dashboard-content">


            <!-- TITRE -->

            <div class="page-title">

                <h2>
                    Tableau de bord
                </h2>

                <p>
                    Vue générale de la gestion des candidatures
                    et des offres de services.
                </p>

            </div>



            <!-- =================================================
                 STATISTIQUES
            ================================================== -->

            <section class="stats-grid">


                <!-- OFFRES -->

                <article class="stat-card">

                    <div class="stat-icon">
                        ▣
                    </div>


                    <div class="stat-information">

                        <span>
                            Offres
                        </span>

                        <strong>
                            <?= $total_offres ?>
                        </strong>

                        <small>
                            Offres enregistrées
                        </small>

                    </div>

                </article>



                <!-- CANDIDATURES -->

                <article class="stat-card">

                    <div class="stat-icon">
                        ♙
                    </div>


                    <div class="stat-information">

                        <span>
                            Candidatures
                        </span>

                        <strong>
                            <?= $total_candidatures ?>
                        </strong>

                        <small>
                            Candidatures enregistrées
                        </small>

                    </div>

                </article>



                <!-- À EXAMINER -->

                <article class="stat-card">

                    <div class="stat-icon">
                        !
                    </div>


                    <div class="stat-information">

                        <span>
                            À examiner
                        </span>

                        <strong>
                            <?= $a_examiner ?>
                        </strong>

                        <small>
                            En attente de traitement
                        </small>

                    </div>

                </article>



                <!-- ÉLIGIBLES -->

                <article class="stat-card eligible">

                    <div class="stat-icon">
                        ✓
                    </div>


                    <div class="stat-information">

                        <span>
                            Éligibles
                        </span>

                        <strong>
                            <?= $eligibles ?>
                        </strong>

                        <small>
                            Candidatures éligibles
                        </small>

                    </div>

                </article>



                <!-- NON ÉLIGIBLES -->

                <article class="stat-card non-eligible">

                    <div class="stat-icon">
                        ×
                    </div>


                    <div class="stat-information">

                        <span>
                            Non éligibles
                        </span>

                        <strong>
                            <?= $non_eligibles ?>
                        </strong>

                        <small>
                            Candidatures non éligibles
                        </small>

                    </div>

                </article>


            </section>



            <!-- =================================================
                 DERNIÈRES CANDIDATURES
            ================================================== -->

            <section class="dashboard-panel">


                <div class="panel-header">


                    <div>

                        <h3>
                            Dernières candidatures à examiner
                        </h3>

                        <p>
                            Les candidatures nécessitant une
                            vérification ou une décision administrative.
                        </p>

                    </div>


                    <a
                        href="candidatures.php"
                        class="btn-view-all"
                    >
                        Voir toutes
                    </a>


                </div>



                <?php if (empty($candidatures_recentes)): ?>


                    <!-- AUCUNE CANDIDATURE -->

                    <div class="empty-state">


                        <div class="empty-icon">
                            ✓
                        </div>


                        <h4>
                            Aucune candidature à examiner
                        </h4>


                        <p>
                            Toutes les candidatures ont actuellement
                            été traitées.
                        </p>


                    </div>


                <?php else: ?>


                    <!-- TABLEAU -->

                    <div class="table-container">


                        <table class="candidatures-table">


                            <thead>

                                <tr>

                                    <th>
                                        N° candidature
                                    </th>

                                    <th>
                                        Candidat
                                    </th>

                                    <th>
                                        Offre
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Statut
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach (
                                $candidatures_recentes
                                as $candidature
                            ): ?>


                                <tr>


                                    <!-- NUMÉRO -->

                                    <td>

                                        <strong
                                            class="numero-candidature"
                                        >

                                            DGLP-

                                            <?= date(
                                                "Y",
                                                strtotime(
                                                    $candidature[
                                                        "date_candidature"
                                                    ]
                                                )
                                            ) ?>

                                            -

                                            <?= str_pad(
                                                $candidature[
                                                    "id_candidature"
                                                ],
                                                4,
                                                "0",
                                                STR_PAD_LEFT
                                            ) ?>

                                        </strong>

                                    </td>



                                    <!-- CANDIDAT -->

                                    <td>

                                        <div class="candidate-name">


                                            <div
                                                class="candidate-avatar"
                                            >

                                                <?= strtoupper(
                                                    substr(
                                                        $candidature[
                                                            "prenom_candidat"
                                                        ],
                                                        0,
                                                        1
                                                    )
                                                ) ?>

                                            </div>


                                            <span>

                                                <?= htmlspecialchars(
                                                    $candidature[
                                                        "prenom_candidat"
                                                    ]
                                                ) ?>

                                                <?= htmlspecialchars(
                                                    $candidature[
                                                        "nom_candidat"
                                                    ]
                                                ) ?>

                                            </span>


                                        </div>

                                    </td>



                                    <!-- OFFRE -->

                                    <td>

                                        <span class="offre-name">

                                            <?= htmlspecialchars(
                                                $candidature[
                                                    "offre"
                                                ]
                                            ) ?>

                                        </span>

                                    </td>



                                    <!-- DATE -->

                                    <td>

                                        <?= htmlspecialchars(
                                            $candidature[
                                                "date_candidature"
                                            ]
                                        ) ?>

                                    </td>



                                    <!-- STATUT -->

                                    <td>

                                        <span
                                            class="status pending"
                                        >

                                            <?= htmlspecialchars(
                                                $candidature[
                                                    "statut"
                                                ]
                                            ) ?>

                                        </span>

                                    </td>



                                    <!-- ACTION -->

                                    <td>

                                        <a
                                            href="examiner-candidature.php?id=<?= (int) $candidature["id_candidature"] ?>"
                                            class="btn-examiner"
                                        >
                                            Examiner
                                        </a>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                            </tbody>


                        </table>


                    </div>


                <?php endif; ?>


            </section>


        </main>



        <!-- =================================================
             FOOTER
        ================================================== -->

        <footer class="admin-footer">

            <p>
                © 2026 Direction Générale de la Lutte contre la Pauvreté
            </p>

            <span>
                Plateforme de gestion des candidatures
            </span>

        </footer>


    </div>


</div>


</body>

</html>