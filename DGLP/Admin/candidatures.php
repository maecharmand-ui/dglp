<?php

session_start();
require_once "../../database.php";

/* Vérifier que l'administrateur est connecté */
if (!isset($_SESSION["id_admin"])) {
    header("Location: connexion.php");
    exit;
}

/* =========================================================
   RÉCUPÉRER LES CANDIDATURES
========================================================= */

$requete = $pdo->query("
    SELECT
        c.id_candidature,
        c.numero_candidature,
        c.date_candidature,
        c.statut,

        ca.nom AS nom_candidat,
        ca.prenom AS prenom_candidat,

        o.id_offre,
        o.nom AS nom_offre,
        o.description AS description_offre

    FROM candidature c

    INNER JOIN candidat ca
        ON c.id_candidat = ca.id_candidat

    INNER JOIN offre o
        ON c.id_offre = o.id_offre

    ORDER BY c.date_candidature DESC
");

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

    <title>Candidatures - DGLP Administration</title>

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
                    rgba(244, 246, 248, 0.95),
                    rgba(244, 246, 248, 0.95)
                ),
                url("../images/dglp.jpg") center center / 420px auto no-repeat fixed;
            background-color: #f4f6f8;
            color: #263238;
            line-height: 1.6;
        }

        a {
            text-decoration: none;
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
            color: #ffffff;
            display: flex;
            flex-direction: column;
            z-index: 1000;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.12);
        }

        .sidebar-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.15);
        }

        .sidebar-logo {
            width: 82px;
            height: 82px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ffffff;
            margin-bottom: 12px;
        }

        .sidebar-header h2 {
            font-size: 21px;
            margin-bottom: 3px;
        }

        .sidebar-header p {
            font-size: 12px;
            color: #d8e5f0;
        }

        .menu {
            padding: 20px 12px;
            flex: 1;
        }

        .menu-title {
            color: #a9bfd3;
            text-transform: uppercase;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            padding: 0 14px 10px;
        }

        .menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            padding: 13px 14px;
            margin-bottom: 5px;
            border-radius: 7px;
            font-size: 14px;
            transition: 0.25s ease;
        }

        .menu a:hover {
            background: rgba(255,255,255,0.10);
        }

        .menu a.active {
            background: #ffffff;
            color: #123b68;
            font-weight: 700;
        }

        .menu-icon {
            width: 25px;
            text-align: center;
            font-size: 16px;
        }

        .sidebar-footer {
            padding: 15px 20px;
            border-top: 1px solid rgba(255,255,255,0.15);
            font-size: 11px;
            color: #b8ccdc;
            text-align: center;
        }


        /* =========================================================
           CONTENU PRINCIPAL
        ========================================================= */

        .main-content {
            margin-left: 255px;
            min-height: 100vh;
        }


        /* =========================================================
           HEADER
        ========================================================= */

        .top-header {
            height: 78px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 35px;
            border-bottom: 1px solid #e1e6eb;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 900;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .ministere-logo {
            width: 45px;
            height: 45px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #123b68;
        }

        .header-title h1 {
            font-size: 17px;
            color: #123b68;
        }

        .header-title p {
            color: #7b8791;
            font-size: 12px;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            background: #e8f1f8;
            color: #123b68;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }

        .admin-text strong {
            display: block;
            color: #263238;
            font-size: 13px;
        }

        .admin-text span {
            color: #7b8791;
            font-size: 11px;
        }


        /* =========================================================
           PAGE
        ========================================================= */

        .page {
            padding: 35px;
            max-width: 1450px;
            margin: 0 auto;
        }

        .page-title {
            margin-bottom: 25px;
        }

        .page-title .badge {
            display: inline-block;
            background: #e8f1f8;
            color: #123b68;
            padding: 6px 13px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .page-title h2 {
            color: #123b68;
            font-size: 29px;
            margin-bottom: 4px;
        }

        .page-title p {
            color: #71808c;
            font-size: 14px;
        }


        /* =========================================================
           STATISTIQUE
        ========================================================= */

        .resume {
            display: flex;
            align-items: center;
            gap: 15px;
            background: #ffffff;
            border: 1px solid #e0e6eb;
            border-radius: 12px;
            padding: 18px 22px;
            margin-bottom: 22px;
            box-shadow: 0 5px 18px rgba(18,59,104,0.05);
        }

        .resume-icon {
            width: 45px;
            height: 45px;
            border-radius: 9px;
            background: #eaf3ee;
            color: #008c4a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            font-weight: bold;
        }

        .resume-text strong {
            display: block;
            color: #123b68;
            font-size: 22px;
            line-height: 1.2;
        }

        .resume-text span {
            color: #7b8791;
            font-size: 12px;
        }


        /* =========================================================
           TABLEAU
        ========================================================= */

        .table-card {
            background: #ffffff;
            border: 1px solid #e0e6eb;
            border-radius: 13px;
            box-shadow: 0 7px 22px rgba(18,59,104,0.07);
            overflow: hidden;
        }

        .table-header {
            padding: 21px 25px;
            border-bottom: 1px solid #e7ebef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            color: #123b68;
            font-size: 18px;
        }

        .table-header p {
            color: #7d8993;
            font-size: 12px;
            margin-top: 2px;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
        }

        .candidatures {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }

        .candidatures th {
            background: #f4f7f9;
            color: #536574;
            padding: 14px 15px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 1px solid #dfe5ea;
            white-space: nowrap;
        }

        .candidatures td {
            padding: 15px;
            border-bottom: 1px solid #edf0f2;
            color: #465762;
            font-size: 13px;
            vertical-align: middle;
        }

        .candidatures tbody tr {
            transition: 0.2s ease;
        }

        .candidatures tbody tr:hover {
            background: #f8fafb;
        }

        .numero {
            color: #123b68;
            font-weight: 700;
            white-space: nowrap;
        }

        .candidat {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .candidat-avatar {
            width: 35px;
            height: 35px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #e8f1f8;
            color: #123b68;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 12px;
        }

        .candidat-nom strong {
            display: block;
            color: #263238;
            font-size: 13px;
        }

        .candidat-nom span {
            color: #8a969f;
            font-size: 11px;
        }

        .offre-nom {
            color: #123b68;
            font-weight: 700;
            max-width: 240px;
        }

        .date {
            white-space: nowrap;
            color: #667783;
        }


        /* =========================================================
           STATUTS
        ========================================================= */

        .statut {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .statut-attente,
        .statut-en-attente,
        .statut-a-completer,
        .statut-à-completer,
        .statut-en_cours {
            background: #fff5df;
            color: #996300;
        }

        .statut-soumise,
        .statut-Soumise,
        .statut-complet,
        .statut-eligible,
        .statut-éligible,
        .statut-accepte,
        .statut-accepté,
        .statut-valide,
        .statut-validé {
            background: #eaf7ef;
            color: #08753e;
        }

        .statut-rejete,
        .statut-rejeté,
        .statut-non_eligible,
        .statut-non-éligible {
            background: #fff0f0;
            color: #b42d2d;
        }


        /* =========================================================
           BOUTON EXAMINER
        ========================================================= */

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #123b68;
            color: #ffffff;
            padding: 8px 13px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            transition: 0.25s ease;
            white-space: nowrap;
        }

        .action-btn:hover {
            background: #0d2d50;
            transform: translateY(-1px);
        }


        /* =========================================================
           AUCUNE CANDIDATURE
        ========================================================= */

        .aucune-candidature {
            padding: 60px 30px;
            background: #ffffff;
            text-align: center;
        }

        .aucune-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: #edf3f8;
            color: #123b68;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            font-weight: bold;
        }

        .aucune-candidature h3 {
            color: #123b68;
            margin-bottom: 5px;
            font-size: 18px;
        }

        .aucune-candidature p {
            color: #7c8993;
            font-size: 13px;
        }


        /* =========================================================
           FOOTER
        ========================================================= */

        footer {
            margin-left: 255px;
            background: #123b68;
            color: #ffffff;
            text-align: center;
            padding: 20px;
            font-size: 12px;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1000px) {

            .sidebar {
                width: 75px;
            }

            .sidebar-header h2,
            .sidebar-header p,
            .menu-title,
            .menu-text,
            .sidebar-footer {
                display: none;
            }

            .sidebar-header {
                padding: 18px 8px;
            }

            .sidebar-logo {
                width: 48px;
                height: 48px;
            }

            .menu {
                padding: 15px 8px;
            }

            .menu a {
                justify-content: center;
                padding: 13px 5px;
            }

            .menu-icon {
                width: auto;
            }

            .main-content {
                margin-left: 75px;
            }

            footer {
                margin-left: 75px;
            }
        }


        @media (max-width: 700px) {

            .top-header {
                height: auto;
                padding: 15px 18px;
            }

            .header-title {
                display: none;
            }

            .page {
                padding: 25px 15px 40px;
            }

            .page-title h2 {
                font-size: 24px;
            }

            .resume {
                padding: 15px;
            }

            .admin-text {
                display: none;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">

    <div class="sidebar-header">

        <img
            src="../images/dglp.jpg"
            alt="Logo DGLP"
            class="sidebar-logo"
        >

        <h2>DGLP</h2>

        <p>Administration</p>

    </div>


    <nav class="menu">

        <div class="menu-title">
            Administration
        </div>


        <a href="dashboard.php">

            <span class="menu-icon">▣</span>

            <span class="menu-text">
                Tableau de bord
            </span>

        </a>


        <a href="offres.php">

            <span class="menu-icon">▤</span>

            <span class="menu-text">
                Offres
            </span>

        </a>


        <a href="candidatures.php" class="active">

            <span class="menu-icon">☷</span>

            <span class="menu-text">
                Candidatures
            </span>

        </a>


        <a href="classement.php">

            <span class="menu-icon">★</span>

            <span class="menu-text">
                Classement
            </span>

        </a>


        <a href="historique.php">

            <span class="menu-icon">◷</span>

            <span class="menu-text">
                Historique
            </span>

        </a>


        <a href="deconnexion.php">

            <span class="menu-icon">↪</span>

            <span class="menu-text">
                Déconnexion
            </span>

        </a>

    </nav>


    <div class="sidebar-footer">

        Direction Générale de la Lutte contre la Pauvreté

    </div>

</aside>


<!-- =========================================================
     CONTENU PRINCIPAL
========================================================= -->

<div class="main-content">


    <!-- HEADER -->

    <header class="top-header">

        <div class="header-left">

            <img
                src="../images/ministere.jpg"
                alt="Ministère"
                class="ministere-logo"
            >

            <div class="header-title">

                <h1>
                    Ministère du Commerce, des PME/PMI et de l'Entrepreneuriat
                </h1>

                <p>
                    Direction Générale de la Lutte contre la Pauvreté
                </p>

            </div>

        </div>


        <div class="admin-info">

            <div class="admin-avatar">

                <?= strtoupper(
                    substr(
                        $_SESSION["prenom_admin"] ?? "A",
                        0,
                        1
                    )
                ) ?>

            </div>


            <div class="admin-text">

                <strong>

                    <?= htmlspecialchars(
                        $_SESSION["prenom_admin"] ?? "Administrateur"
                    ) ?>

                    <?= htmlspecialchars(
                        $_SESSION["nom_admin"] ?? ""
                    ) ?>

                </strong>

                <span>
                    Gestion des candidatures
                </span>

            </div>

        </div>

    </header>


    <!-- PAGE -->

    <main class="page">


        <div class="page-title">

            <span class="badge">
                Gestion des candidatures
            </span>

            <h2>
                Candidatures reçues
            </h2>

            <p>
                Consultez les candidatures transmises et examinez
                les dossiers des candidats.
            </p>

        </div>


        <!-- RESUME -->

        <div class="resume">

            <div class="resume-icon">
                ☷
            </div>

            <div class="resume-text">

                <strong>
                    <?= count($candidatures) ?>
                </strong>

                <span>
                    candidature<?= count($candidatures) > 1 ? "s" : "" ?>
                    enregistrée<?= count($candidatures) > 1 ? "s" : "" ?>
                </span>

            </div>

        </div>


        <!-- TABLEAU -->

        <section class="table-card">


            <div class="table-header">

                <div>

                    <h3>
                        Liste des candidatures
                    </h3>

                    <p>
                        Les candidatures sont classées de la plus récente à la plus ancienne.
                    </p>

                </div>

            </div>


            <?php if (empty($candidatures)): ?>


                <div class="aucune-candidature">

                    <div class="aucune-icon">
                        ☷
                    </div>

                    <h3>
                        Aucune candidature
                    </h3>

                    <p>
                        Aucune candidature n'a encore été enregistrée.
                    </p>

                </div>


            <?php else: ?>


                <div class="table-container">

                    <table class="candidatures">


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


                        <?php foreach ($candidatures as $candidature): ?>


                            <?php

                            $prenom = $candidature["prenom_candidat"] ?? "";
                            $nom = $candidature["nom_candidat"] ?? "";

                            $initiales = strtoupper(
                                substr($prenom, 0, 1) .
                                substr($nom, 0, 1)
                            );

                            $statutOriginal = $candidature["statut"];

                            /*
                             * Transformation du statut en classe CSS.
                             */
                            $classeStatut = strtolower(
                                str_replace(
                                    [" ", "à", "é", "è", "ê", "ë", "ï", "î", "ô", "ù"],
                                    ["-", "a", "e", "e", "e", "e", "i", "i", "o", "u"],
                                    $statutOriginal
                                )
                            );

                            ?>


                            <tr>


                                <!-- NUMERO -->

                                <td>

                                    <span class="numero">

                                        <?= htmlspecialchars(
                                            $candidature["numero_candidature"]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- CANDIDAT -->

                                <td>

                                    <div class="candidat">

                                        <div class="candidat-avatar">

                                            <?= htmlspecialchars(
                                                $initiales
                                            ) ?>

                                        </div>


                                        <div class="candidat-nom">

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $prenom
                                                ) ?>

                                                <?= htmlspecialchars(
                                                    $nom
                                                ) ?>

                                            </strong>

                                            <span>
                                                Candidat
                                            </span>

                                        </div>

                                    </div>

                                </td>


                                <!-- OFFRE -->

                                <td>

                                    <div class="offre-nom">

                                        <?= htmlspecialchars(
                                            $candidature["nom_offre"]
                                        ) ?>

                                    </div>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <span class="date">

                                        <?= htmlspecialchars(
                                            $candidature["date_candidature"]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- STATUT -->

                                <td>

                                    <span
                                        class="statut statut-<?= htmlspecialchars(
                                            $classeStatut
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $statutOriginal
                                        ) ?>

                                    </span>

                                </td>


                                <!-- ACTION -->

                                <td>

                                    <a
                                        href="examiner-candidature.php?id=<?= (int) $candidature["id_candidature"] ?>"
                                        class="action-btn"
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

</div>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    <p>
        © 2026 Direction Générale de la Lutte contre la Pauvreté -
        Administration
    </p>

</footer>


</body>

</html>