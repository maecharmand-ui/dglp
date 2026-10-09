<?php

session_start();
require_once "../../database.php";

/* =========================================================
   VÉRIFIER LA CONNEXION DE L'ADMINISTRATEUR
   ========================================================= */

if (!isset($_SESSION["id_admin"])) {
    header("Location: connexion.php");
    exit;
}


/* =========================================================
   RÉCUPÉRER L'HISTORIQUE
   ========================================================= */

$requete = $pdo->query("
    SELECT
        h.id_historique,
        h.action,
        h.description,
        h.date_action,

        a.nom AS nom_admin,
        a.prenom AS prenom_admin,

        c.id_candidature,
        c.numero_candidature,

        ca.nom AS nom_candidat,
        ca.prenom AS prenom_candidat

    FROM historique h

    LEFT JOIN admin a
        ON h.id_admin = a.id_admin

    LEFT JOIN candidature c
        ON h.id_candidature = c.id_candidature

    LEFT JOIN candidat ca
        ON c.id_candidat = ca.id_candidat

    ORDER BY h.date_action DESC
");

$historiques = $requete->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   INFORMATIONS ADMINISTRATEUR
   ========================================================= */

$prenomAdmin = $_SESSION["prenom_admin"] ?? "";
$nomAdmin = $_SESSION["nom_admin"] ?? "";

$initiales = "";

if ($prenomAdmin !== "") {
    $initiales .= strtoupper(substr($prenomAdmin, 0, 1));
}

if ($nomAdmin !== "") {
    $initiales .= strtoupper(substr($nomAdmin, 0, 1));
}

if ($initiales === "") {
    $initiales = "AD";
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

    <title>Historique - DGLP</title>

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
           BASE
        ===================================================== */

        body {
            font-family: Arial, Helvetica, sans-serif;
            background:
                linear-gradient(
                    rgba(244, 246, 248, 0.94),
                    rgba(244, 246, 248, 0.94)
                ),
                url("../images/dglp.jpg") center center / 420px auto no-repeat fixed;
            background-color: #f4f6f8;
            color: #26384a;
            line-height: 1.6;
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
            width: 255px;
            height: 100vh;
            background: #123b68;
            color: white;
            padding: 22px 16px;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.12);
            z-index: 1000;
            overflow-y: auto;
        }


        /* LOGO */

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
            padding: 5px;
            margin-bottom: 10px;
        }

        .sidebar-logo h2 {
            font-size: 20px;
            margin-bottom: 3px;
        }

        .sidebar-logo span {
            font-size: 12px;
            opacity: 0.8;
        }


        /* MENU */

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 14px;
            transition: 0.2s ease;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        .sidebar-menu a.active {
            background: white;
            color: #123b68;
            font-weight: bold;
        }

        .menu-icon {
            width: 22px;
            text-align: center;
            font-size: 17px;
        }


        /* DÉCONNEXION */

        .sidebar-menu .logout {
            margin-top: 15px;
            background: rgba(220, 53, 69, 0.15);
        }

        .sidebar-menu .logout:hover {
            background: #c82333;
            color: white;
        }


        /* =====================================================
           ZONE PRINCIPALE
        ===================================================== */

        .main-content {
            margin-left: 255px;
            min-height: 100vh;
        }


        /* =====================================================
           HEADER
        ===================================================== */

        .top-header {
            height: 78px;
            background: white;
            border-bottom: 1px solid #e1e6eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 900;
        }


        /* MINISTÈRE */

        .ministere {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .ministere img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .ministere-text strong {
            display: block;
            color: #123b68;
            font-size: 14px;
        }

        .ministere-text span {
            display: block;
            color: #718096;
            font-size: 12px;
        }


        /* ADMIN */

        .admin-profile {
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

        .admin-info strong {
            display: block;
            color: #26384a;
            font-size: 13px;
        }

        .admin-info span {
            display: block;
            color: #7a8793;
            font-size: 11px;
        }


        /* =====================================================
           CONTENU
        ===================================================== */

        .content {
            padding: 32px;
        }


        /* TITRE */

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            color: #123b68;
            font-size: 30px;
            margin-bottom: 5px;
        }

        .page-header p {
            color: #718096;
            font-size: 14px;
        }


        /* =====================================================
           STATISTIQUE
        ===================================================== */

        .history-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: white;
            border: 1px solid #e1e6eb;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(18, 59, 104, 0.06);
        }

        .summary-card .icon {
            width: 42px;
            height: 42px;
            border-radius: 9px;
            background: #eaf2f9;
            color: #123b68;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 12px;
        }

        .summary-card strong {
            display: block;
            font-size: 25px;
            color: #123b68;
        }

        .summary-card span {
            color: #718096;
            font-size: 13px;
        }


        /* =====================================================
           CARTE HISTORIQUE
        ===================================================== */

        .history-card {
            background: white;
            border: 1px solid #e1e6eb;
            border-radius: 14px;
            box-shadow: 0 5px 16px rgba(18, 59, 104, 0.07);
            overflow: hidden;
        }


        /* ENTÊTE DE CARTE */

        .history-card-header {
            padding: 22px 24px;
            border-bottom: 1px solid #e8edf2;
        }

        .history-card-header h2 {
            color: #123b68;
            font-size: 20px;
            margin-bottom: 4px;
        }

        .history-card-header p {
            color: #718096;
            font-size: 13px;
        }


        /* =====================================================
           TABLEAU
        ===================================================== */

        .table-container {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1050px;
        }

        thead {
            background: #f4f7fa;
        }

        th {
            color: #123b68;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            text-align: left;
            padding: 15px 16px;
            border-bottom: 1px solid #dfe6ed;
            white-space: nowrap;
        }

        td {
            padding: 15px 16px;
            border-bottom: 1px solid #edf1f4;
            font-size: 13px;
            color: #435466;
            vertical-align: top;
        }

        tbody tr:hover {
            background: #f9fbfc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }


        /* =====================================================
           DATE
        ===================================================== */

        .date-cell {
            color: #123b68;
            font-weight: 600;
            white-space: nowrap;
        }


        /* =====================================================
           ADMINISTRATEUR
        ===================================================== */

        .admin-cell {
            display: flex;
            align-items: center;
            gap: 9px;
            min-width: 150px;
        }

        .small-avatar {
            width: 34px;
            height: 34px;
            min-width: 34px;
            border-radius: 50%;
            background: #123b68;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
        }

        .admin-name {
            font-weight: 600;
            color: #35495e;
        }


        /* =====================================================
           ACTION
        ===================================================== */

        .action-badge {
            display: inline-block;
            background: #eaf2f9;
            color: #123b68;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }


        /* =====================================================
           CANDIDATURE
        ===================================================== */

        .candidature-number {
            display: inline-block;
            background: #f1f5f8;
            color: #123b68;
            border: 1px solid #dce4eb;
            border-radius: 6px;
            padding: 5px 8px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }


        /* =====================================================
           CANDIDAT
        ===================================================== */

        .candidate-name {
            font-weight: 600;
            color: #35495e;
            white-space: nowrap;
        }


        /* =====================================================
           DESCRIPTION
        ===================================================== */

        .description-cell {
            max-width: 360px;
            min-width: 250px;
            color: #667788;
            line-height: 1.5;
        }


        /* =====================================================
           ÉTAT VIDE
        ===================================================== */

        .empty-state {
            padding: 55px 25px;
            text-align: center;
        }

        .empty-icon {
            width: 65px;
            height: 65px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: #edf3f8;
            color: #123b68;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .empty-state h3 {
            color: #123b68;
            margin-bottom: 6px;
        }

        .empty-state p {
            color: #718096;
            font-size: 13px;
        }


        /* =====================================================
           PIED DE PAGE
        ===================================================== */

        footer {
            margin-left: 255px;
            background: #123b68;
            color: white;
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
            .sidebar-logo span,
            .sidebar-menu .menu-text {
                display: none;
            }

            .sidebar-logo img {
                width: 52px;
                height: 52px;
            }

            .sidebar-menu a {
                justify-content: center;
                padding: 13px 8px;
            }

            .main-content {
                margin-left: 75px;
            }

            footer {
                margin-left: 75px;
            }

            .history-summary {
                grid-template-columns: 1fr 1fr;
            }
        }


        @media (max-width: 700px) {

            .top-header {
                padding: 0 16px;
                height: 70px;
            }

            .ministere-text {
                display: none;
            }

            .admin-info {
                display: none;
            }

            .content {
                padding: 20px 15px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .history-summary {
                grid-template-columns: 1fr;
            }
        }


        @media (max-width: 480px) {

            .sidebar {
                width: 60px;
            }

            .main-content {
                margin-left: 60px;
            }

            footer {
                margin-left: 60px;
            }

            .sidebar-logo img {
                width: 42px;
                height: 42px;
            }

            .top-header {
                padding: 0 10px;
            }

            .ministere img {
                width: 40px;
                height: 40px;
            }

            .admin-avatar {
                width: 36px;
                height: 36px;
            }

            .content {
                padding: 15px 10px;
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

        <span>Administration</span>

    </div>


    <nav class="sidebar-menu">

        <a href="dashboard.php">

            <span class="menu-icon">▦</span>

            <span class="menu-text">
                Tableau de bord
            </span>

        </a>


        <a href="offres.php">

            <span class="menu-icon">▣</span>

            <span class="menu-text">
                Offres
            </span>

        </a>


        <a href="candidatures.php">

            <span class="menu-icon">◉</span>

            <span class="menu-text">
                Candidatures
            </span>

        </a>


        <a href="classement.php">

            <span class="menu-icon">≡</span>

            <span class="menu-text">
                Classement
            </span>

        </a>


        <a href="decision.php">

            <span class="menu-icon">✓</span>

            <span class="menu-text">
                Décisions
            </span>

        </a>


        <a
            href="historique.php"
            class="active"
        >

            <span class="menu-icon">◷</span>

            <span class="menu-text">
                Historique
            </span>

        </a>


        <a
            href="deconnexion.php"
            class="logout"
        >

            <span class="menu-icon">↪</span>

            <span class="menu-text">
                Déconnexion
            </span>

        </a>

    </nav>

</aside>



<!-- =====================================================
     ZONE PRINCIPALE
     ===================================================== -->

<div class="main-content">


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


        <div class="admin-profile">

            <div class="admin-avatar">

                <?= htmlspecialchars($initiales) ?>

            </div>


            <div class="admin-info">

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

    <main class="content">


        <!-- =================================================
             TITRE
             ================================================= -->

        <div class="page-header">

            <h1>
                Historique des actions
            </h1>

            <p>
                Consultez l'ensemble des actions effectuées
                par les administrateurs dans le traitement
                des candidatures.
            </p>

        </div>



        <!-- =================================================
             STATISTIQUES
             ================================================= -->

        <section class="history-summary">


            <div class="summary-card">

                <div class="icon">
                    ◷
                </div>

                <strong>
                    <?= count($historiques) ?>
                </strong>

                <span>
                    Actions enregistrées
                </span>

            </div>


            <div class="summary-card">

                <div class="icon">
                    ✓
                </div>

                <strong>

                    <?php

                    $actionsUniques = [];

                    foreach ($historiques as $historique) {

                        $actionsUniques[] =
                            $historique["action"];
                    }

                    echo count(
                        array_unique($actionsUniques)
                    );

                    ?>

                </strong>

                <span>
                    Types d'actions
                </span>

            </div>


            <div class="summary-card">

                <div class="icon">
                    ◉
                </div>

                <strong>

                    <?php

                    $candidaturesHistorique = [];

                    foreach ($historiques as $historique) {

                        if (
                            !empty(
                                $historique["id_candidature"]
                            )
                        ) {

                            $candidaturesHistorique[] =
                                $historique["id_candidature"];
                        }
                    }

                    echo count(
                        array_unique(
                            $candidaturesHistorique
                        )
                    );

                    ?>

                </strong>

                <span>
                    Candidatures concernées
                </span>

            </div>


        </section>



        <!-- =================================================
             HISTORIQUE
             ================================================= -->

        <section class="history-card">


            <div class="history-card-header">

                <h2>
                    Journal des actions
                </h2>

                <p>
                    Toutes les opérations importantes effectuées
                    dans l'administration sont enregistrées ici.
                </p>

            </div>



            <?php if (empty($historiques)): ?>


                <!-- ÉTAT VIDE -->

                <div class="empty-state">

                    <div class="empty-icon">
                        ◷
                    </div>

                    <h3>
                        Aucun historique
                    </h3>

                    <p>
                        Aucune action administrative
                        n'est encore enregistrée.
                    </p>

                </div>


            <?php else: ?>


                <!-- TABLEAU -->

                <div class="table-container">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Administrateur
                                </th>

                                <th>
                                    Action
                                </th>

                                <th>
                                    Candidature
                                </th>

                                <th>
                                    Candidat
                                </th>

                                <th>
                                    Description
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($historiques as $historique): ?>


                            <tr>


                                <!-- DATE -->

                                <td class="date-cell">

                                    <?= htmlspecialchars(
                                        $historique["date_action"]
                                    ) ?>

                                </td>



                                <!-- ADMINISTRATEUR -->

                                <td>

                                    <?php if (
                                        $historique["nom_admin"] !== null
                                    ): ?>

                                        <?php

                                        $initialesHistorique = "";

                                        if (
                                            !empty(
                                                $historique["prenom_admin"]
                                            )
                                        ) {
                                            $initialesHistorique .=
                                                strtoupper(
                                                    substr(
                                                        $historique["prenom_admin"],
                                                        0,
                                                        1
                                                    )
                                                );
                                        }

                                        if (
                                            !empty(
                                                $historique["nom_admin"]
                                            )
                                        ) {
                                            $initialesHistorique .=
                                                strtoupper(
                                                    substr(
                                                        $historique["nom_admin"],
                                                        0,
                                                        1
                                                    )
                                                );
                                        }

                                        ?>

                                        <div class="admin-cell">

                                            <div class="small-avatar">

                                                <?= htmlspecialchars(
                                                    $initialesHistorique
                                                ) ?>

                                            </div>

                                            <span class="admin-name">

                                                <?= htmlspecialchars(
                                                    $historique["prenom_admin"]
                                                ) ?>

                                                <?= htmlspecialchars(
                                                    $historique["nom_admin"]
                                                ) ?>

                                            </span>

                                        </div>

                                    <?php else: ?>

                                        Administrateur inconnu

                                    <?php endif; ?>

                                </td>



                                <!-- ACTION -->

                                <td>

                                    <span class="action-badge">

                                        <?= htmlspecialchars(
                                            $historique["action"]
                                        ) ?>

                                    </span>

                                </td>



                                <!-- CANDIDATURE -->

                                <td>

                                    <?php if (
                                        $historique["numero_candidature"]
                                        !== null
                                    ): ?>

                                        <span class="candidature-number">

                                            <?= htmlspecialchars(
                                                $historique[
                                                    "numero_candidature"
                                                ]
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        -

                                    <?php endif; ?>

                                </td>



                                <!-- CANDIDAT -->

                                <td>

                                    <?php if (
                                        $historique["nom_candidat"]
                                        !== null
                                    ): ?>

                                        <span class="candidate-name">

                                            <?= htmlspecialchars(
                                                $historique[
                                                    "prenom_candidat"
                                                ]
                                            ) ?>

                                            <?= htmlspecialchars(
                                                $historique[
                                                    "nom_candidat"
                                                ]
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        -

                                    <?php endif; ?>

                                </td>



                                <!-- DESCRIPTION -->

                                <td class="description-cell">

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $historique[
                                                "description"
                                            ] ?? ""
                                        )
                                    ) ?>

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



<!-- =====================================================
     PIED DE PAGE
     ===================================================== -->

<footer>

    <p>
        © 2026 Direction Générale de la Lutte contre la Pauvreté
    </p>

</footer>


</body>

</html>