<?php

session_start();
require_once "../../database.php";

/*
|--------------------------------------------------------------------------
| VÉRIFICATION DE LA SESSION
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION["id_candidat"])) {
    header("Location: connexion.php");
    exit;
}

$id_candidat = $_SESSION["id_candidat"];


/*
|--------------------------------------------------------------------------
| INFORMATIONS DU CANDIDAT
|--------------------------------------------------------------------------
*/
$requete = $pdo->prepare(
    "SELECT nom, prenom, ville, province, date_naissance, telephone
     FROM candidat
     WHERE id_candidat = ?"
);

$requete->execute([$id_candidat]);

$candidat = $requete->fetch(PDO::FETCH_ASSOC);

if (!$candidat) {
    session_destroy();
    header("Location: connexion.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| DOSSIER DU CANDIDAT
|--------------------------------------------------------------------------
*/
$requete = $pdo->prepare(
    "SELECT statut
     FROM dossier
     WHERE id_candidat = ?"
);

$requete->execute([$id_candidat]);

$dossier = $requete->fetch(PDO::FETCH_ASSOC);

$statut_dossier = $dossier
    ? $dossier["statut"]
    : "incomplet";


/*
|--------------------------------------------------------------------------
| NOMBRE DE CANDIDATURES
|--------------------------------------------------------------------------
*/
$requete = $pdo->prepare(
    "SELECT COUNT(*)
     FROM candidature
     WHERE id_candidat = ?"
);

$requete->execute([$id_candidat]);

$nombre_candidatures = $requete->fetchColumn();


/*
|--------------------------------------------------------------------------
| NOMBRE D'OFFRES DISPONIBLES
|--------------------------------------------------------------------------
*/
$requete = $pdo->query(
    "SELECT COUNT(*)
     FROM offre
     WHERE statut = 'ouverte'
     AND date_limite_candidature >= CURDATE()"
);

$nombre_offres = $requete->fetchColumn();


/*
|--------------------------------------------------------------------------
| DERNIÈRES CANDIDATURES
|--------------------------------------------------------------------------
*/
$requete = $pdo->prepare(
    "SELECT
        c.id_candidature AS numero_candidature,
        c.date_candidature,
        c.statut,
        o.nom,
        o.description
     FROM candidature c
     INNER JOIN offre o
        ON c.id_offre = o.id_offre
     WHERE c.id_candidat = ?
     ORDER BY c.date_candidature DESC
     LIMIT 5"
);

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

    <title>DGLP - Espace candidat</title>


    <style>

        /* =========================================================
           RESET
        ========================================================= */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        /* =========================================================
           BODY
        ========================================================= */

        body {

            font-family: Arial, sans-serif;

            color: #3e362c;

            line-height: 1.6;

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

            top: 0;
            left: 0;

            width: 255px;
            height: 100vh;

            background: #123b68;

            color: white;

            box-shadow: 3px 0 15px rgba(0, 0, 0, 0.15);

            z-index: 1000;

            display: flex;

            flex-direction: column;

            overflow-y: auto;
        }


        /* LOGO */

        .sidebar-logo {

            text-align: center;

            padding: 25px 15px 15px;
        }


        .sidebar-logo img {

            width: 105px;
            height: 105px;

            object-fit: cover;

            border-radius: 50%;

            border: 4px solid white;

            background: white;

            box-shadow:
                0 4px 12px
                rgba(0, 0, 0, 0.20);
        }


        /* TITRE */

        .sidebar-title {

            text-align: center;

            padding: 0 15px 25px;

            border-bottom:
                1px solid
                rgba(255, 255, 255, 0.20);
        }


        .sidebar-title h2 {

            font-size: 24px;

            letter-spacing: 1px;

            margin-bottom: 3px;
        }


        .sidebar-title p {

            font-size: 13px;

            opacity: 0.85;
        }


        /* MENU */

        .sidebar-menu {

            padding: 20px 12px;

            flex: 1;
        }


        .sidebar-menu a {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 13px 15px;

            margin-bottom: 7px;

            border-radius: 7px;

            font-size: 14px;

            transition: 0.3s;
        }


        .sidebar-menu a:hover {

            background:
                rgba(255, 255, 255, 0.13);

            transform: translateX(3px);
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


        /* =========================================================
           MAIN
        ========================================================= */

        .main-content {

            margin-left: 255px;

            min-height: 100vh;

            display: flex;

            flex-direction: column;
        }


        /* =========================================================
           HEADER
        ========================================================= */

        .top-header {

            height: 85px;

            background: rgba(255, 255, 255, 0.96);

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 30px;

            border-bottom:
                1px solid #e3e6e9;

            box-shadow:
                0 2px 8px
                rgba(0, 0, 0, 0.06);

            position: sticky;

            top: 0;

            z-index: 500;
        }


        .ministry-info {

            display: flex;

            align-items: center;

            gap: 15px;
        }


        .ministry-info img {

            width: 55px;
            height: 55px;

            object-fit: contain;
        }


        .ministry-text h3 {

            color: #123b68;

            font-size: 17px;

            margin-bottom: 2px;
        }


        .ministry-text p {

            color: #666;

            font-size: 12px;
        }


        /* PROFIL CANDIDAT */

        .candidate-profile {

            display: flex;

            align-items: center;

            gap: 10px;

            color: #123b68;

            font-weight: bold;

            font-size: 14px;
        }


        .candidate-avatar {

            width: 38px;
            height: 38px;

            border-radius: 50%;

            background: #123b68;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: bold;

            font-size: 16px;
        }


        /* =========================================================
           CONTENU
        ========================================================= */

        .content {

            padding: 30px;

            flex: 1;
        }


        /* TITRE */

        .page-title {

            margin-bottom: 25px;
        }


        .page-title h1 {

            color: #123b68;

            font-size: 28px;

            margin-bottom: 5px;
        }


        .page-title p {

            color: #666;

            font-size: 14px;
        }


        /* =========================================================
           CARTES STATISTIQUES
        ========================================================= */

        .stats-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;

            margin-bottom: 30px;
        }


        .stat-card {

            background: white;

            border-radius: 10px;

            padding: 22px;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, 0.08);

            border-left:
                5px solid #123b68;

            transition: 0.3s;
        }


        .stat-card:hover {

            transform: translateY(-3px);

            box-shadow:
                0 6px 18px
                rgba(0, 0, 0, 0.12);
        }


        .stat-card.dossier {

            border-left-color: #008c4a;
        }


        .stat-card.candidatures {

            border-left-color: #123b68;
        }


        .stat-card.offres {

            border-left-color: #d28b00;
        }


        .stat-card-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 12px;
        }


        .stat-card h3 {

            color: #555;

            font-size: 15px;

            font-weight: normal;
        }


        .stat-icon {

            width: 42px;
            height: 42px;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #eef3f8;

            color: #123b68;

            font-size: 19px;

            font-weight: bold;
        }


        .stat-card.dossier .stat-icon {

            background: #e8f6ef;

            color: #008c4a;
        }


        .stat-card.offres .stat-icon {

            background: #fff5df;

            color: #d28b00;
        }


        .stat-value {

            font-size: 27px;

            font-weight: bold;

            color: #123b68;

            margin-bottom: 4px;
        }


        .stat-description {

            font-size: 12px;

            color: #777;

            margin-bottom: 15px;
        }


        .stat-link {

            display: inline-block;

            color: #123b68;

            font-size: 13px;

            font-weight: bold;

            transition: 0.3s;
        }


        .stat-link:hover {

            color: #008c4a;
        }


        /* =========================================================
           PANNEAUX
        ========================================================= */

        .panel {

            background: white;

            border-radius: 10px;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, 0.08);

            margin-bottom: 25px;

            overflow: hidden;
        }


        .panel-header {

            padding: 18px 22px;

            border-bottom:
                1px solid #e8e8e8;

            display: flex;

            align-items: center;

            justify-content: space-between;
        }


        .panel-header h2 {

            color: #123b68;

            font-size: 18px;
        }


        .panel-header p {

            color: #777;

            font-size: 12px;

            margin-top: 3px;
        }


        .panel-body {

            padding: 22px;
        }


        /* =========================================================
           TABLEAU
        ========================================================= */

        .table-container {

            width: 100%;

            overflow-x: auto;
        }


        .candidatures {

            width: 100%;

            border-collapse: collapse;

            min-width: 700px;
        }


        .candidatures th {

            background: #123b68;

            color: white;

            padding: 13px;

            text-align: left;

            font-size: 13px;
        }


        .candidatures td {

            padding: 13px;

            border-bottom:
                1px solid #e8e8e8;

            font-size: 13px;

            color: #555;
        }


        .candidatures tbody tr:hover {

            background: #f7f9fb;
        }


        /* =========================================================
           BADGES STATUT
        ========================================================= */

        .badge {

            display: inline-block;

            padding: 5px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: bold;

            text-transform: capitalize;
        }


        .badge-incomplet,
        .badge-a-completer {

            background: #fff3cd;

            color: #856404;
        }


        .badge-complet,
        .badge-eligible {

            background: #d4edda;

            color: #155724;
        }


        .badge-en-attente {

            background: #e2e3e5;

            color: #383d41;
        }


        .badge-refuse,
        .badge-non-eligible {

            background: #f8d7da;

            color: #721c24;
        }


        /* =========================================================
           BOUTONS
        ========================================================= */

        .btn {

            display: inline-block;

            padding: 10px 16px;

            border-radius: 6px;

            font-size: 13px;

            font-weight: bold;

            transition: 0.3s;
        }


        .btn-primary {

            background: #123b68;

            color: white;
        }


        .btn-primary:hover {

            background: #0d2d50;
        }


        .btn-success {

            background: #008c4a;

            color: white;
        }


        .btn-success:hover {

            background: #006f3b;
        }


        /* =========================================================
           MESSAGE AUCUNE CANDIDATURE
        ========================================================= */

        .empty-state {

            text-align: center;

            padding: 35px 20px;

            color: #777;
        }


        .empty-state-icon {

            font-size: 35px;

            margin-bottom: 10px;
        }


        .empty-state p {

            margin-bottom: 18px;
        }


        /* =========================================================
           INFORMATIONS RAPIDES
        ========================================================= */

        .quick-section {

            display: grid;

            grid-template-columns:
                2fr 1fr;

            gap: 20px;

            margin-top: 25px;
        }


        .welcome-card {

            background:
                linear-gradient(
                    135deg,
                    #123b68,
                    #1b578f
                );

            color: white;

            border-radius: 10px;

            padding: 25px;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, 0.10);
        }


        .welcome-card h2 {

            font-size: 21px;

            margin-bottom: 8px;
        }


        .welcome-card p {

            font-size: 13px;

            opacity: 0.9;

            margin-bottom: 18px;
        }


        .welcome-card .btn {

            background: white;

            color: #123b68;
        }


        .welcome-card .btn:hover {

            background: #f0f0f0;
        }


        .info-card {

            background: white;

            border-radius: 10px;

            padding: 22px;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, 0.08);
        }


        .info-card h3 {

            color: #123b68;

            margin-bottom: 12px;

            font-size: 17px;
        }


        .info-card p {

            color: #666;

            font-size: 13px;

            margin-bottom: 12px;
        }


        /* =========================================================
           FOOTER
        ========================================================= */

        .footer {

            background: #123b68;

            color: white;

            text-align: center;

            padding: 15px;

            font-size: 12px;

            margin-top: auto;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1200px) {

            .stats-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .quick-section {

                grid-template-columns: 1fr;
            }
        }


        @media (max-width: 900px) {

            .sidebar {

                width: 75px;
            }


            .sidebar-logo img {

                width: 50px;
                height: 50px;
            }


            .sidebar-title h2 {

                font-size: 14px;
            }


            .sidebar-title p {

                display: none;
            }


            .sidebar-menu a {

                justify-content: center;

                padding: 13px 5px;
            }


            .sidebar-menu a span:not(.menu-icon) {

                display: none;
            }


            .main-content {

                margin-left: 75px;
            }


            .top-header {

                padding: 0 18px;
            }


            .ministry-text {

                display: none;
            }


            .candidate-profile span {

                display: none;
            }
        }


        @media (max-width: 700px) {

            .stats-grid {

                grid-template-columns: 1fr;
            }


            .content {

                padding: 20px 15px;
            }


            .top-header {

                height: 70px;
            }


            .page-title h1 {

                font-size: 23px;
            }


            .candidate-profile {

                gap: 5px;
            }


            .panel-header {

                padding: 15px;
            }


            .panel-body {

                padding: 15px;
            }


            .welcome-card {

                padding: 20px;
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

    </div>


    <div class="sidebar-title">

        <h2>DGLP</h2>

        <p>Espace candidat</p>

    </div>


    <nav class="sidebar-menu">


        <a
            href="espacecandidat.php"
            class="active"
        >

            <span class="menu-icon">⌂</span>

            <span>Tableau de bord</span>

        </a>


        <a href="offres.php">

            <span class="menu-icon">▣</span>

            <span>Offres disponibles</span>

        </a>


        <a href="dossier.php">

            <span class="menu-icon">▤</span>

            <span>Mon dossier</span>

        </a>


        <a href="suivi-candidature.php">

            <span class="menu-icon">✓</span>

            <span>Mes candidatures</span>

        </a>


        <a href="deconnexion.php">

            <span class="menu-icon">↪</span>

            <span>Déconnexion</span>

        </a>


    </nav>


</aside>


<!-- =========================================================
     CONTENU PRINCIPAL
========================================================= -->

<div class="main-content">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="top-header">


        <div class="ministry-info">


            <img
                src="../images/ministere.jpg"
                alt="Ministère"
            >


            <div class="ministry-text">

                <h3>
                    Ministère du Commerce,
                    des PME/PMI et de l'Entrepreneuriat
                </h3>

                <p>
                    Direction Générale de la Lutte contre la Pauvreté
                </p>

            </div>


        </div>


        <div class="candidate-profile">


            <div class="candidate-avatar">

                <?= strtoupper(
                    substr(
                        htmlspecialchars($candidat["prenom"]),
                        0,
                        1
                    )
                ) ?>

            </div>


            <span>

                <?= htmlspecialchars($candidat["prenom"]) ?>

                <?= htmlspecialchars($candidat["nom"]) ?>

            </span>


        </div>


    </header>


    <!-- =====================================================
         CONTENU
    ====================================================== -->

    <main class="content">


        <!-- TITRE -->

        <section class="page-title">

            <h1>
                Tableau de bord candidat
            </h1>

            <p>
                Bienvenue dans votre espace personnel.
                Retrouvez ici votre dossier, vos candidatures
                et les offres disponibles.
            </p>

        </section>


        <!-- =================================================
             CARTES STATISTIQUES
        ================================================== -->

        <section class="stats-grid">


            <!-- DOSSIER -->

            <article class="stat-card dossier">


                <div class="stat-card-header">

                    <h3>
                        Mon dossier
                    </h3>

                    <div class="stat-icon">
                        ✓
                    </div>

                </div>


                <div class="stat-value">

                    <?php

                    if ($statut_dossier === "complet") {

                        echo "Complet";

                    } else {

                        echo "Incomplet";

                    }

                    ?>

                </div>


                <p class="stat-description">

                    État actuel de votre dossier candidat.

                </p>


                <a
                    href="dossier.php"
                    class="stat-link"
                >

                    Consulter mon dossier →

                </a>


            </article>


            <!-- CANDIDATURES -->

            <article class="stat-card candidatures">


                <div class="stat-card-header">

                    <h3>
                        Mes candidatures
                    </h3>

                    <div class="stat-icon">
                        #
                    </div>

                </div>


                <div class="stat-value">

                    <?= htmlspecialchars(
                        $nombre_candidatures
                    ) ?>

                </div>


                <p class="stat-description">

                    Candidature(s) enregistrée(s).

                </p>


                <a
                    href="suivi-candidature.php"
                    class="stat-link"
                >

                    Voir mes candidatures →

                </a>


            </article>


            <!-- OFFRES -->

            <article class="stat-card offres">


                <div class="stat-card-header">

                    <h3>
                        Offres disponibles
                    </h3>

                    <div class="stat-icon">
                        +
                    </div>

                </div>


                <div class="stat-value">

                    <?= htmlspecialchars(
                        $nombre_offres
                    ) ?>

                </div>


                <p class="stat-description">

                    Offre(s) actuellement ouverte(s).

                </p>


                <a
                    href="offres.php"
                    class="stat-link"
                >

                    Consulter les offres →

                </a>


            </article>


        </section>


        <!-- =================================================
             DERNIÈRES CANDIDATURES
        ================================================== -->

        <section class="panel">


            <div class="panel-header">

                <div>

                    <h2>
                        Mes dernières candidatures
                    </h2>

                    <p>
                        Consultez rapidement l'état de vos dernières candidatures.
                    </p>

                </div>


                <a
                    href="suivi-candidature.php"
                    class="btn btn-primary"
                >

                    Voir tout

                </a>

            </div>


            <div class="panel-body">


                <?php if (empty($candidatures)): ?>


                    <div class="empty-state">


                        <div class="empty-state-icon">
                            📄
                        </div>


                        <p>
                            Vous n'avez encore aucune candidature.
                        </p>


                        <a
                            href="offres.php"
                            class="btn btn-primary"
                        >

                            Consulter les offres

                        </a>


                    </div>


                <?php else: ?>


                    <div class="table-container">


                        <table class="candidatures">


                            <thead>

                                <tr>

                                    <th>
                                        Numéro
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

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach (
                                    $candidatures
                                    as $candidature
                                ): ?>


                                    <tr>


                                        <td>

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
                                                    "numero_candidature"
                                                ],
                                                4,
                                                "0",
                                                STR_PAD_LEFT
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $candidature[
                                                    "description"
                                                ]
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $candidature[
                                                    "date_candidature"
                                                ]
                                            ) ?>

                                        </td>


                                        <td>


                                            <?php

                                            $statut =
                                                strtolower(
                                                    trim(
                                                        $candidature[
                                                            "statut"
                                                        ]
                                                    )
                                                );

                                            $classe_badge =
                                                "badge-en-attente";


                                            if (
                                                $statut ===
                                                "à compléter"
                                                ||
                                                $statut ===
                                                "a compléter"
                                            ) {

                                                $classe_badge =
                                                    "badge-a-completer";

                                            } elseif (
                                                $statut ===
                                                "éligible"
                                                ||
                                                $statut ===
                                                "eligible"
                                            ) {

                                                $classe_badge =
                                                    "badge-eligible";

                                            } elseif (
                                                $statut ===
                                                "non éligible"
                                                ||
                                                $statut ===
                                                "non eligible"
                                            ) {

                                                $classe_badge =
                                                    "badge-non-eligible";

                                            } elseif (
                                                $statut ===
                                                "refusé"
                                                ||
                                                $statut ===
                                                "refuse"
                                            ) {

                                                $classe_badge =
                                                    "badge-refuse";

                                            }

                                            ?>


                                            <span
                                                class="badge
                                                <?= $classe_badge ?>"
                                            >

                                                <?= htmlspecialchars(
                                                    $candidature[
                                                        "statut"
                                                    ]
                                                ) ?>

                                            </span>


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
             INFORMATIONS RAPIDES
        ================================================== -->

        <section class="quick-section">


            <article class="welcome-card">


                <h2>
                    Besoin de déposer une candidature ?
                </h2>


                <p>

                    Consultez les offres actuellement disponibles
                    et choisissez celle qui correspond à votre profil.

                </p>


                <a
                    href="offres.php"
                    class="btn"
                >

                    Consulter les offres

                </a>


            </article>


            <article class="info-card">


                <h3>
                    Mon dossier
                </h3>


                <p>

                    Pensez à compléter toutes les informations
                    nécessaires avant de déposer une candidature.

                </p>


                <a
                    href="dossier.php"
                    class="btn btn-success"
                >

                    Vérifier mon dossier

                </a>


            </article>


        </section>


    </main>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <footer class="footer">

        <p>

            © 2026 Direction Générale de la Lutte contre la Pauvreté

        </p>

    </footer>


</div>


</body>

</html>