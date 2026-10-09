<?php

session_start();

require_once "../../database.php";


/*
|--------------------------------------------------------------------------
| VÉRIFIER QUE LE CANDIDAT EST CONNECTÉ
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["id_candidat"])) {
    header("Location: connexion.php");
    exit;
}

$id_candidat = (int) $_SESSION["id_candidat"];


/*
|--------------------------------------------------------------------------
| INFORMATIONS DU CANDIDAT
|--------------------------------------------------------------------------
*/

$requete = $pdo->prepare("
    SELECT
        nom,
        prenom
    FROM candidat
    WHERE id_candidat = ?
");

$requete->execute([$id_candidat]);

$candidat = $requete->fetch(PDO::FETCH_ASSOC);

if (!$candidat) {
    session_destroy();
    header("Location: connexion.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| RÉCUPÉRER LES CANDIDATURES
|--------------------------------------------------------------------------
*/

$requete = $pdo->prepare("
    SELECT
        c.id_candidature,
        c.numero_candidature,
        c.date_candidature,
        c.statut,
        o.id_offre,
        o.nom,
        o.description,
        o.date_limite_candidature
    FROM candidature c
    INNER JOIN offre o
        ON c.id_offre = o.id_offre
    WHERE c.id_candidat = ?
    ORDER BY c.date_candidature DESC
");

$requete->execute([$id_candidat]);

$candidatures = $requete->fetchAll(PDO::FETCH_ASSOC);

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
        Mes candidatures - DGLP
    </title>


    <style>

        /* =========================================================
           RESET
        ========================================================= */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {
            font-family: Arial, Helvetica, sans-serif;

            background:
                linear-gradient(
                    rgba(244, 246, 248, 0.94),
                    rgba(244, 246, 248, 0.94)
                ),
                url("../images/dglp.jpg")
                center center / 420px auto
                no-repeat fixed;

            background-color: #f4f6f8;

            color: #263238;

            line-height: 1.6;
        }


        a {
            text-decoration: none;
            color: inherit;
        }


        /* =========================================================
           SIDEBAR
        ========================================================= */

        .sidebar {
            position: fixed;

            left: 0;
            top: 0;

            width: 255px;
            height: 100vh;

            background: #123b68;

            color: white;

            padding: 25px 15px;

            box-shadow:
                3px 0 15px rgba(0, 0, 0, 0.12);

            z-index: 1000;

            overflow-y: auto;
        }


        .sidebar-logo {
            text-align: center;

            margin-bottom: 28px;
        }


        .sidebar-logo img {
            width: 105px;
            height: 105px;

            object-fit: contain;

            border-radius: 50%;

            background: white;

            padding: 5px;

            border: 3px solid
                rgba(255, 255, 255, 0.85);
        }


        .sidebar-title {
            text-align: center;

            margin-top: 12px;
        }


        .sidebar-title h2 {
            font-size: 25px;

            letter-spacing: 1px;
        }


        .sidebar-title p {
            font-size: 13px;

            opacity: 0.85;

            margin-top: 3px;
        }


        .sidebar-menu {
            margin-top: 30px;
        }


        .sidebar-menu a {
            display: flex;

            align-items: center;

            gap: 12px;

            padding: 13px 15px;

            margin-bottom: 8px;

            border-radius: 8px;

            color: white;

            font-size: 14px;

            transition: 0.25s ease;
        }


        .sidebar-menu a:hover {
            background:
                rgba(255, 255, 255, 0.12);

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


        /* =========================================================
           CONTENU PRINCIPAL
        ========================================================= */

        .main {
            margin-left: 255px;

            min-height: 100vh;
        }


        /* =========================================================
           HEADER
        ========================================================= */

        .top-header {
            height: 82px;

            background:
                rgba(255, 255, 255, 0.97);

            border-bottom:
                1px solid #e1e6ea;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 35px;

            position: sticky;

            top: 0;

            z-index: 900;
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


        .ministere-text h3 {
            color: #123b68;

            font-size: 15px;

            font-weight: 700;
        }


        .ministere-text p {
            color: #6b747c;

            font-size: 12px;

            margin-top: 2px;
        }


        .profil-candidat {
            display: flex;

            align-items: center;

            gap: 10px;

            color: #123b68;

            font-weight: bold;

            font-size: 14px;
        }


        .avatar {
            width: 40px;
            height: 40px;

            border-radius: 50%;

            background: #123b68;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: bold;
        }


        /* =========================================================
           CONTENU
        ========================================================= */

        .content {
            padding: 35px;

            max-width: 1250px;

            margin: auto;
        }


        .page-title {
            color: #123b68;

            font-size: 30px;

            margin-bottom: 8px;
        }


        .page-subtitle {
            color: #6b747c;

            font-size: 14px;

            margin-bottom: 28px;
        }


        /* =========================================================
           STATISTIQUE
        ========================================================= */

        .resume-card {
            display: flex;

            align-items: center;

            justify-content: space-between;

            background: white;

            border-radius: 12px;

            padding: 20px 24px;

            margin-bottom: 25px;

            box-shadow:
                0 4px 18px rgba(0, 0, 0, 0.07);

            border: 1px solid #e8ecef;
        }


        .resume-text h3 {
            color: #123b68;

            font-size: 15px;

            margin-bottom: 4px;
        }


        .resume-text p {
            color: #7a858d;

            font-size: 13px;
        }


        .resume-number {
            width: 50px;
            height: 50px;

            border-radius: 50%;

            background: #eaf1f8;

            color: #123b68;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 20px;

            font-weight: bold;
        }


        /* =========================================================
           CARTE CANDIDATURE
        ========================================================= */

        .candidature-card {
            background:
                rgba(255, 255, 255, 0.97);

            border-radius: 12px;

            padding: 25px;

            margin-bottom: 22px;

            box-shadow:
                0 4px 18px rgba(0, 0, 0, 0.07);

            border: 1px solid #e8ecef;

            transition: 0.25s ease;
        }


        .candidature-card:hover {
            transform: translateY(-2px);

            box-shadow:
                0 7px 22px rgba(0, 0, 0, 0.10);
        }


        .candidature-header {
            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            gap: 20px;

            padding-bottom: 17px;

            margin-bottom: 18px;

            border-bottom:
                1px solid #edf0f2;
        }


        .candidature-title {
            color: #123b68;

            font-size: 19px;

            margin-bottom: 5px;
        }


        .reference {
            color: #008c4a;

            font-size: 13px;

            font-weight: bold;
        }


        /* =========================================================
           STATUTS
        ========================================================= */

        .statut {
            display: inline-flex;

            align-items: center;

            padding: 7px 13px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: bold;

            text-transform: uppercase;

            white-space: nowrap;
        }


        .statut-attente {
            background: #fff4df;

            color: #a15c00;
        }


        .statut-complet {
            background: #e8f7ef;

            color: #08783f;
        }


        .statut-rejete,
        .statut-non-eligible {
            background: #fdecec;

            color: #b42318;
        }


        .statut-eligible,
        .statut-accepte,
        .statut-valide {
            background: #e8f7ef;

            color: #08783f;
        }


        .statut-default {
            background: #edf1f4;

            color: #56616a;
        }


        /* =========================================================
           INFORMATIONS
        ========================================================= */

        .infos-candidature {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 15px 25px;

            margin-bottom: 20px;
        }


        .information {
            padding: 11px 0;

            border-bottom:
                1px solid #edf0f2;

            font-size: 13px;
        }


        .information strong {
            display: block;

            color: #7a858d;

            font-size: 11px;

            margin-bottom: 3px;

            text-transform: uppercase;

            letter-spacing: 0.3px;
        }


        .information span {
            color: #263238;

            font-weight: 600;
        }


        /* =========================================================
           DESCRIPTION
        ========================================================= */

        .description {
            background: #f8fafb;

            border-left:
                4px solid #123b68;

            padding: 15px 17px;

            border-radius: 6px;

            color: #56616a;

            font-size: 13px;

            margin-bottom: 20px;
        }


        .description strong {
            display: block;

            color: #123b68;

            margin-bottom: 6px;
        }


        /* =========================================================
           ACTIONS
        ========================================================= */

        .actions {
            display: flex;

            justify-content: flex-end;

            padding-top: 5px;
        }


        .btn {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            background: #123b68;

            color: white;

            padding: 11px 18px;

            border-radius: 7px;

            font-size: 13px;

            font-weight: bold;

            transition: 0.25s ease;
        }


        .btn:hover {
            background: #0d2d50;

            transform: translateY(-1px);

            box-shadow:
                0 4px 10px
                rgba(18, 59, 104, 0.20);
        }


        /* =========================================================
           AUCUNE CANDIDATURE
        ========================================================= */

        .aucune-candidature {
            background:
                rgba(255, 255, 255, 0.97);

            border-radius: 12px;

            padding: 55px 30px;

            text-align: center;

            box-shadow:
                0 4px 18px
                rgba(0, 0, 0, 0.07);

            border: 1px solid #e8ecef;
        }


        .empty-icon {
            width: 65px;
            height: 65px;

            margin: 0 auto 18px;

            border-radius: 50%;

            background: #eaf1f8;

            color: #123b68;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 27px;
        }


        .aucune-candidature h3 {
            color: #123b68;

            font-size: 20px;

            margin-bottom: 8px;
        }


        .aucune-candidature p {
            color: #7a858d;

            font-size: 14px;

            margin-bottom: 22px;
        }


        /* =========================================================
           FOOTER
        ========================================================= */

        .footer {
            margin-left: 255px;

            background: #123b68;

            color: white;

            text-align: center;

            padding: 18px;

            font-size: 12px;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 900px) {

            .sidebar {
                width: 75px;

                padding: 20px 8px;
            }


            .sidebar-title h2 {
                font-size: 17px;
            }


            .sidebar-title p,
            .sidebar-menu span {
                display: none;
            }


            .sidebar-logo img {
                width: 52px;
                height: 52px;
            }


            .sidebar-menu a {
                justify-content: center;

                padding: 13px 5px;
            }


            .menu-icon {
                font-size: 19px;
            }


            .main {
                margin-left: 75px;
            }


            .footer {
                margin-left: 75px;
            }


            .top-header {
                padding: 0 20px;
            }


            .ministere-text {
                display: none;
            }

        }


        @media (max-width: 700px) {

            .content {
                padding: 25px 15px;
            }


            .page-title {
                font-size: 24px;
            }


            .infos-candidature {
                grid-template-columns: 1fr;
            }


            .candidature-header {
                flex-direction: column;
            }


            .actions {
                justify-content: stretch;
            }


            .actions .btn {
                width: 100%;
            }


            .resume-card {
                padding: 18px;
            }

        }


        @media (max-width: 500px) {

            .profil-candidat span {
                display: none;
            }


            .top-header {
                padding: 0 12px;
            }


            .candidature-card {
                padding: 18px;
            }


            .aucune-candidature {
                padding: 40px 20px;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">

    <div class="sidebar-logo">

        <img
            src="../images/dglp.jpg"
            alt="Logo DGLP"
        >

        <div class="sidebar-title">

            <h2>DGLP</h2>

            <p>Espace candidat</p>

        </div>

    </div>


    <nav class="sidebar-menu">

        <a href="espacecandidat.php">

            <span class="menu-icon">⌂</span>

            <span>
                Tableau de bord
            </span>

        </a>


        <a href="offres.php">

            <span class="menu-icon">▣</span>

            <span>
                Offres disponibles
            </span>

        </a>


        <a href="dossier.php">

            <span class="menu-icon">▤</span>

            <span>
                Mon dossier
            </span>

        </a>


        <a
            href="suivi-candidature.php"
            class="active"
        >

            <span class="menu-icon">✓</span>

            <span>
                Mes candidatures
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


<!-- =========================================================
     CONTENU PRINCIPAL
========================================================= -->

<div class="main">


    <!-- HEADER -->

    <header class="top-header">

        <div class="ministere">

            <img
                src="../images/ministere.jpg"
                alt="Logo du ministère"
            >

            <div class="ministere-text">

                <h3>
                    Ministère du Commerce, des PME/PMI et de l'Entrepreneuriat
                </h3>

                <p>
                    Direction Générale de la Lutte contre la Pauvreté
                </p>

            </div>

        </div>


        <div class="profil-candidat">

            <div class="avatar">

                <?= strtoupper(
                    substr(
                        $candidat["prenom"],
                        0,
                        1
                    )
                ) ?>

            </div>

            <span>

                <?= htmlspecialchars(
                    $candidat["prenom"]
                ) ?>

                <?= htmlspecialchars(
                    $candidat["nom"]
                ) ?>

            </span>

        </div>

    </header>


    <!-- =====================================================
         CONTENU
    ====================================================== -->

    <main class="content">

        <h1 class="page-title">
            Mes candidatures
        </h1>


        <p class="page-subtitle">
            Consultez l'état d'avancement de vos candidatures
            transmises à la Direction Générale de la Lutte contre
            la Pauvreté.
        </p>


        <!-- RÉSUMÉ -->

        <div class="resume-card">

            <div class="resume-text">

                <h3>
                    Total de mes candidatures
                </h3>

                <p>
                    Candidatures enregistrées dans votre espace.
                </p>

            </div>


            <div class="resume-number">

                <?= count($candidatures) ?>

            </div>

        </div>


        <?php if (empty($candidatures)): ?>


            <!-- =================================================
                 AUCUNE CANDIDATURE
            ================================================== -->

            <div class="aucune-candidature">

                <div class="empty-icon">
                    ✓
                </div>


                <h3>
                    Aucune candidature
                </h3>


                <p>
                    Vous n'avez encore déposé aucune candidature.
                </p>


                <div class="actions">

                    <a
                        href="offres.php"
                        class="btn"
                    >
                        Consulter les offres
                    </a>

                </div>

            </div>


        <?php else: ?>


            <?php foreach ($candidatures as $candidature): ?>


                <?php

                /*
                |--------------------------------------------------------------------------
                | STYLE DU STATUT
                |--------------------------------------------------------------------------
                */

                $statut = strtolower(
                    trim(
                        $candidature["statut"]
                    )
                );


                if (
                    $statut === "en attente" ||
                    $statut === "a compléter" ||
                    $statut === "à compléter"
                ) {

                    $classe_statut = "statut-attente";

                } elseif (
                    $statut === "complet" ||
                    $statut === "eligible" ||
                    $statut === "éligible" ||
                    $statut === "accepte" ||
                    $statut === "accepté" ||
                    $statut === "valide" ||
                    $statut === "validé"
                ) {

                    $classe_statut = "statut-eligible";

                } elseif (
                    $statut === "rejete" ||
                    $statut === "rejeté" ||
                    $statut === "non eligible" ||
                    $statut === "non éligible"
                ) {

                    $classe_statut = "statut-rejete";

                } else {

                    $classe_statut = "statut-default";
                }

                ?>


                <!-- =================================================
                     CARTE CANDIDATURE
                ================================================== -->

                <article class="candidature-card">


                    <div class="candidature-header">

                        <div>

                            <h2 class="candidature-title">

                                <?= htmlspecialchars(
                                    $candidature["nom"]
                                ) ?>

                            </h2>


                            <p class="reference">

                                N° de candidature :

                                <?= htmlspecialchars(
                                    $candidature["numero_candidature"]
                                ) ?>

                            </p>

                        </div>


                        <span
                            class="statut <?= $classe_statut ?>"
                        >

                            <?= htmlspecialchars(
                                $candidature["statut"]
                            ) ?>

                        </span>

                    </div>


                    <!-- INFORMATIONS -->

                    <div class="infos-candidature">


                        <div class="information">

                            <strong>
                                Date de candidature
                            </strong>

                            <span>

                                <?= htmlspecialchars(
                                    $candidature["date_candidature"]
                                ) ?>

                            </span>

                        </div>


                        <div class="information">

                            <strong>
                                Date limite de l'offre
                            </strong>

                            <span>

                                <?= htmlspecialchars(
                                    $candidature["date_limite_candidature"]
                                ) ?>

                            </span>

                        </div>


                        <div class="information">

                            <strong>
                                Référence de l'offre
                            </strong>

                            <span>

                                OFFRE-<?= htmlspecialchars(
                                    $candidature["id_offre"]
                                ) ?>

                            </span>

                        </div>


                    </div>


                    <!-- DESCRIPTION -->

                    <div class="description">

                        <strong>
                            Description de l'offre
                        </strong>


                        <?= nl2br(
                            htmlspecialchars(
                                $candidature["description"]
                            )
                        ) ?>

                    </div>


                    <!-- ACTION -->

                    <div class="actions">

                        <a
                            href="accuse-reception.php?id=<?= urlencode(
                                $candidature["id_candidature"]
                            ) ?>"
                            class="btn"
                        >
                            Voir l'accusé de réception
                        </a>

                    </div>


                </article>


            <?php endforeach; ?>


        <?php endif; ?>

    </main>

</div>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="footer">

    <p>
        © 2026 Direction Générale de la Lutte contre la Pauvreté
    </p>

</footer>


</body>

</html>