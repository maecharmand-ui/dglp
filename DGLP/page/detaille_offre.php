<?php

session_start();

require_once "../../database.php";

/* =========================================================
   VÉRIFIER L'IDENTIFIANT DE L'OFFRE
========================================================= */

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: offres.php");
    exit;
}

$id_offre = (int) $_GET["id"];

/* =========================================================
   RÉCUPÉRER L'OFFRE
========================================================= */

$sql = "SELECT 
            id_offre,
            nom,
            description,
            date_creation,
            date_limite_candidature,
            nombre_place,
            critere,
            montant,
            document_supplementaire,
            statut
        FROM offre
        WHERE id_offre = ?
        AND statut = 'ouverte'
        AND date_limite_candidature >= CURDATE()";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_offre]);

$offre = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================================================
   SI L'OFFRE N'EXISTE PAS
========================================================= */

if (!$offre) {
    echo "Offre introuvable ou candidature fermée.";
    exit;
}

/* =========================================================
   RÉCUPÉRER LES PIÈCES COMPLÉMENTAIRES
========================================================= */

$sqlPieces = "SELECT 
                id_piece,
                nom_piece,
                obligatoire
              FROM piece_complementaire
              WHERE id_offre = ?
              ORDER BY id_piece ASC";

$stmtPieces = $pdo->prepare($sqlPieces);
$stmtPieces->execute([$id_offre]);

$pieces = $stmtPieces->fetchAll(PDO::FETCH_ASSOC);

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
        <?= htmlspecialchars($offre["nom"] ?: "Détail de l'offre") ?>
        - DGLP
    </title>

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

            color: #263746;

            line-height: 1.6;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* =====================================================
           HEADER
        ===================================================== */

        .top-header {
            height: 78px;

            background: white;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 10px 40px;

            border-bottom:
                1px solid #e1e5e8;

            box-shadow:
                0 2px 8px
                rgba(0, 0, 0, 0.06);

            position: sticky;

            top: 0;

            z-index: 1000;
        }

        .brand {
            display: flex;

            align-items: center;

            gap: 13px;
        }

        .brand-logo {
            width: 50px;
            height: 50px;

            object-fit: cover;

            border-radius: 50%;

            border: 2px solid #123b68;

            padding: 2px;

            background: white;
        }

        .brand-text h2 {
            color: #123b68;

            font-size: 21px;

            letter-spacing: 0.5px;
        }

        .brand-text p {
            color: #6c7883;

            font-size: 11px;
        }

        /* =====================================================
           NAVIGATION
        ===================================================== */

        nav {
            display: flex;

            align-items: center;

            gap: 7px;
        }

        nav a {
            color: #33485a;

            font-size: 14px;

            font-weight: 600;

            padding: 9px 13px;

            border-radius: 7px;

            transition: 0.25s;
        }

        nav a:hover {
            background: #eef4f9;

            color: #123b68;
        }

        nav a.connexion {
            background: #123b68;

            color: white;
        }

        nav a.connexion:hover {
            background: #0d2d50;
        }

        nav a.espace {
            background: #008c4a;

            color: white;
        }

        nav a.espace:hover {
            background: #00733d;
        }

        /* =====================================================
           CONTENU
        ===================================================== */

        main {
            max-width: 1100px;

            margin: 0 auto;

            padding: 35px 25px 50px;
        }

        /* =====================================================
           HERO
        ===================================================== */

        .hero {
            background:
                linear-gradient(
                    135deg,
                    #123b68,
                    #1b527f
                );

            color: white;

            border-radius: 14px;

            padding: 38px;

            margin-bottom: 25px;

            box-shadow:
                0 7px 20px
                rgba(18, 59, 104, 0.18);
        }

        .hero .badge {
            display: inline-block;

            background:
                rgba(255, 255, 255, 0.15);

            border:
                1px solid
                rgba(255, 255, 255, 0.25);

            padding: 6px 12px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

            margin-bottom: 14px;
        }

        .hero h1 {
            font-size: 31px;

            margin-bottom: 10px;

            line-height: 1.3;
        }

        .hero p {
            font-size: 15px;

            color:
                rgba(255, 255, 255, 0.88);

            max-width: 750px;
        }

        /* =====================================================
           CARTES
        ===================================================== */

        .card {
            background: white;

            border:
                1px solid #e1e6ea;

            border-radius: 12px;

            padding: 27px;

            margin-bottom: 22px;

            box-shadow:
                0 5px 18px
                rgba(0, 0, 0, 0.055);
        }

        .card h2 {
            color: #123b68;

            font-size: 21px;

            padding-bottom: 13px;

            margin-bottom: 18px;

            border-bottom:
                1px solid #edf0f2;
        }

        .description {
            color: #4e5d69;

            font-size: 15px;
        }

        /* =====================================================
           INFORMATIONS
        ===================================================== */

        .info-grid {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 15px;
        }

        .info-box {
            background: #f7f9fa;

            border:
                1px solid #e5e9ec;

            border-radius: 9px;

            padding: 17px;
        }

        .info-label {
            display: block;

            color: #6c7883;

            font-size: 12px;

            margin-bottom: 5px;
        }

        .info-value {
            color: #123b68;

            font-weight: bold;

            font-size: 15px;
        }

        .info-value.green {
            color: #008c4a;
        }

        /* =====================================================
           CRITÈRES
        ===================================================== */

        .criteria {
            background: #f7f9fa;

            border-left:
                4px solid #123b68;

            border-radius: 8px;

            padding: 18px;

            color: #4e5d69;

            white-space: normal;
        }

        /* =====================================================
           DOCUMENTS
        ===================================================== */

        .documents-list {
            display: flex;

            flex-direction: column;

            gap: 10px;

            list-style: none;
        }

        .document-item {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            padding: 14px 16px;

            background: #f7f9fa;

            border:
                1px solid #e3e8eb;

            border-radius: 8px;
        }

        .document-name {
            display: flex;

            align-items: center;

            gap: 10px;

            color: #344856;

            font-size: 14px;

            font-weight: 600;
        }

        .document-icon {
            width: 34px;
            height: 34px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #eef4f9;

            color: #123b68;

            border-radius: 7px;

            font-size: 16px;
        }

        .document-status {
            font-size: 11px;

            font-weight: bold;

            padding: 5px 9px;

            border-radius: 15px;

            white-space: nowrap;
        }

        .obligatoire {
            background: #fff0f0;

            color: #b42318;
        }

        .facultatif {
            background: #eef8f3;

            color: #087443;
        }

        .no-document {
            background: #f7f9fa;

            border:
                1px solid #e4e8eb;

            padding: 18px;

            border-radius: 8px;

            color: #687681;

            font-size: 14px;
        }

        .document-warning {
            background: #fff8e6;

            border:
                1px solid #f2d58a;

            border-left:
                5px solid #d99a00;

            color: #755400;

            padding: 14px 17px;

            border-radius: 8px;

            margin-bottom: 17px;

            font-size: 13px;
        }

        /* =====================================================
           ACTIONS
        ===================================================== */

        .actions-card {
            background: white;

            border:
                1px solid #e1e6ea;

            border-radius: 12px;

            padding: 25px;

            box-shadow:
                0 5px 18px
                rgba(0, 0, 0, 0.055);
        }

        .actions-card h2 {
            color: #123b68;

            font-size: 19px;

            margin-bottom: 9px;
        }

        .actions-card p {
            color: #687681;

            font-size: 14px;

            margin-bottom: 20px;
        }

        .actions {
            display: flex;

            flex-wrap: wrap;

            gap: 12px;
        }

        .btn {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 12px 21px;

            border-radius: 7px;

            font-size: 14px;

            font-weight: bold;

            transition: 0.25s;

            border: none;

            cursor: pointer;
        }

        .btn-primary {
            background: #008c4a;

            color: white;
        }

        .btn-primary:hover {
            background: #00733d;

            transform:
                translateY(-1px);
        }

        .btn-secondary {
            background: #123b68;

            color: white;
        }

        .btn-secondary:hover {
            background: #0d2d50;

            transform:
                translateY(-1px);
        }

        .btn-light {
            background: #eef1f4;

            color: #40505e;

            border:
                1px solid #d9e0e5;
        }

        .btn-light:hover {
            background: #e2e7eb;
        }

        .login-message {
            background: #eef4f9;

            border-left:
                4px solid #123b68;

            color: #415362;

            padding: 14px 16px;

            border-radius: 7px;

            margin-bottom: 18px;

            font-size: 14px;
        }

        /* =====================================================
           FOOTER
        ===================================================== */

        footer {
            background: #123b68;

            color: white;

            text-align: center;

            padding: 19px;

            font-size: 12px;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 800px) {

            .top-header {
                padding: 10px 20px;
            }

            .brand-text p {
                display: none;
            }

            nav {
                gap: 2px;
            }

            nav a {
                padding: 8px 9px;

                font-size: 12px;
            }

            .hero {
                padding: 28px;
            }

            .hero h1 {
                font-size: 26px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {

            .top-header {
                height: auto;

                min-height: 70px;

                flex-wrap: wrap;

                gap: 10px;
            }

            .brand-text h2 {
                font-size: 18px;
            }

            nav {
                width: 100%;

                overflow-x: auto;

                padding-bottom: 3px;
            }

            nav a {
                white-space: nowrap;
            }

            main {
                padding:
                    20px 14px 35px;
            }

            .hero {
                padding: 24px 20px;
            }

            .hero h1 {
                font-size: 23px;
            }

            .card,
            .actions-card {
                padding: 20px;
            }

            .document-item {
                align-items: flex-start;

                flex-direction: column;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }

    </style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header class="top-header">

    <div class="brand">

        <img
            src="../images/dglp.jpg"
            alt="Logo DGLP"
            class="brand-logo"
        >

        <div class="brand-text">

            <h2>
                DGLP
            </h2>

            <p>
                Direction Générale de la Lutte contre la Pauvreté
            </p>

        </div>

    </div>


    <nav>

        <a href="../index.html">
            Accueil
        </a>

        <a
            href="offres.php"
            class="active"
        >
            Offres
        </a>


        <?php if (isset($_SESSION["id_candidat"])): ?>

            <a
                href="espacecandidat.php"
                class="espace"
            >
                Espace candidat
            </a>

            <a href="deconnexion.php">
                Déconnexion
            </a>

        <?php else: ?>

            <a
                href="connexion.php"
                class="connexion"
            >
                Connexion
            </a>

        <?php endif; ?>

    </nav>

</header>


<!-- =========================================================
     CONTENU PRINCIPAL
========================================================= -->

<main>


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="hero">

        <span class="badge">
            OFFRE-<?= (int) $offre["id_offre"] ?>
        </span>


        <h1>

            <?= htmlspecialchars(
                $offre["nom"] ?: "Détail de l'offre"
            ) ?>

        </h1>


        <p>
            Consultez les informations de cette offre
            avant de déposer votre candidature.
        </p>

    </section>


    <!-- =====================================================
         DESCRIPTION
    ====================================================== -->

    <section class="card">

        <h2>
            Description de l'offre
        </h2>


        <div class="description">

            <?= nl2br(
                htmlspecialchars(
                    $offre["description"]
                )
            ) ?>

        </div>

    </section>


    <!-- =====================================================
         INFORMATIONS
    ====================================================== -->

    <section class="card">

        <h2>
            Informations sur l'offre
        </h2>


        <div class="info-grid">


            <div class="info-box">

                <span class="info-label">
                    Date de création
                </span>

                <span class="info-value">

                    <?= htmlspecialchars(
                        $offre["date_creation"]
                    ) ?>

                </span>

            </div>


            <div class="info-box">

                <span class="info-label">
                    Date limite de candidature
                </span>

                <span class="info-value">

                    <?= htmlspecialchars(
                        $offre[
                            "date_limite_candidature"
                        ]
                    ) ?>

                </span>

            </div>


            <div class="info-box">

                <span class="info-label">
                    Nombre de places
                </span>

                <span class="info-value">

                    <?= htmlspecialchars(
                        $offre["nombre_place"]
                    ) ?>

                </span>

            </div>


            <?php if ($offre["montant"] !== null): ?>

                <div class="info-box">

                    <span class="info-label">
                        Montant
                    </span>

                    <span class="info-value">

                        <?= number_format(
                            (float) $offre["montant"],
                            0,
                            ",",
                            " "
                        ) ?>

                        FCFA

                    </span>

                </div>

            <?php endif; ?>


            <div class="info-box">

                <span class="info-label">
                    Statut
                </span>

                <span class="info-value green">
                    Offre ouverte
                </span>

            </div>

        </div>

    </section>


    <!-- =====================================================
         CRITÈRES
    ====================================================== -->

    <?php if (!empty($offre["critere"])): ?>

        <section class="card">

            <h2>
                Critères d'éligibilité
            </h2>


            <div class="criteria">

                <?= nl2br(
                    htmlspecialchars(
                        $offre["critere"]
                    )
                ) ?>

            </div>

        </section>

    <?php endif; ?>


    <!-- =====================================================
         DOCUMENTS
    ====================================================== -->

    <section class="card">

        <h2>
            Documents supplémentaires
        </h2>


        <?php if ($offre["document_supplementaire"] == 1): ?>


            <?php if (count($pieces) > 0): ?>

                <div class="document-warning">

                    <strong>
                        Attention :
                    </strong>

                    les pièces marquées « Obligatoire »
                    devront être déposées lors de votre
                    candidature.

                </div>


                <ul class="documents-list">

                    <?php foreach ($pieces as $piece): ?>

                        <li class="document-item">

                            <div class="document-name">

                                <span class="document-icon">
                                    📄
                                </span>

                                <?= htmlspecialchars(
                                    $piece["nom_piece"]
                                ) ?>

                            </div>


                            <?php if ($piece["obligatoire"] == 1): ?>

                                <span
                                    class="document-status obligatoire"
                                >
                                    Obligatoire
                                </span>

                            <?php else: ?>

                                <span
                                    class="document-status facultatif"
                                >
                                    Facultatif
                                </span>

                            <?php endif; ?>

                        </li>

                    <?php endforeach; ?>

                </ul>


            <?php else: ?>

                <div class="no-document">

                    Des documents supplémentaires
                    sont demandés pour cette offre.

                </div>

            <?php endif; ?>


        <?php else: ?>


            <div class="no-document">

                Aucun document supplémentaire demandé
                pour cette offre.

            </div>


        <?php endif; ?>

    </section>


    <!-- =====================================================
         ACTIONS
    ====================================================== -->

    <section class="actions-card">

        <h2>
            Vous souhaitez postuler ?
        </h2>


        <p>
            Commencez votre candidature en ligne.
            Vous pourrez ensuite compléter votre dossier
            et déposer les pièces justificatives demandées.
        </p>


        <?php if (isset($_SESSION["id_candidat"])): ?>


            <div class="actions">

                <a
                    href="postuler.php?id=<?= (int) $offre["id_offre"] ?>"
                    class="btn btn-primary"
                >
                    Postuler à cette offre
                </a>


                <a
                    href="offres.php"
                    class="btn btn-light"
                >
                    Retour aux offres
                </a>

            </div>


        <?php else: ?>


            <div class="login-message">

                Vous devez être connecté à votre compte candidat
                pour pouvoir postuler à cette offre.

            </div>


            <div class="actions">

                <a
                    href="connexion.php"
                    class="btn btn-secondary"
                >
                    Se connecter
                </a>


                <a
                    href="offres.php"
                    class="btn btn-light"
                >
                    Retour aux offres
                </a>

            </div>


        <?php endif; ?>

    </section>

</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    <p>
        © 2026 Direction Générale de la Lutte contre la Pauvreté.
        Tous droits réservés.
    </p>

</footer>


</body>

</html>