<?php

session_start();
require_once "../../database.php";

/* =========================================================
   VÉRIFIER QUE LE CANDIDAT EST CONNECTÉ
========================================================= */

if (!isset($_SESSION["id_candidat"])) {
    header("Location: connexion.php");
    exit;
}

$id_candidat = (int) $_SESSION["id_candidat"];

/* =========================================================
   RÉCUPÉRER LES INFORMATIONS DU CANDIDAT
========================================================= */

$requeteCandidat = $pdo->prepare("
    SELECT
        nom,
        prenom
    FROM candidat
    WHERE id_candidat = ?
");

$requeteCandidat->execute([$id_candidat]);

$candidat = $requeteCandidat->fetch(PDO::FETCH_ASSOC);

if (!$candidat) {
    session_destroy();
    header("Location: connexion.php");
    exit;
}

/* =========================================================
   VÉRIFIER L'IDENTIFIANT DE L'OFFRE
========================================================= */

if (!isset($_GET["id"]) || !ctype_digit($_GET["id"])) {
    header("Location: offres.php");
    exit;
}

$id_offre = (int) $_GET["id"];

/* =========================================================
   RÉCUPÉRER L'OFFRE
========================================================= */

$requete = $pdo->prepare("
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
        statut
    FROM offre
    WHERE id_offre = ?
    AND statut = 'ouverte'
    AND date_limite_candidature >= CURDATE()
");

$requete->execute([$id_offre]);

$offre = $requete->fetch(PDO::FETCH_ASSOC);

/* =========================================================
   SI L'OFFRE N'EXISTE PAS OU EST FERMÉE
========================================================= */

if (!$offre) {
    header("Location: offres.php?erreur=offre_indisponible");
    exit;
}

/* =========================================================
   VÉRIFIER SI LE CANDIDAT A DÉJÀ POSTULÉ
========================================================= */

$requeteCandidature = $pdo->prepare("
    SELECT
        id_candidature,
        numero_candidature,
        statut
    FROM candidature
    WHERE id_candidat = ?
    AND id_offre = ?
    LIMIT 1
");

$requeteCandidature->execute([
    $id_candidat,
    $id_offre
]);

$candidatureExistante = $requeteCandidature->fetch(PDO::FETCH_ASSOC);

/* =========================================================
   TRAITEMENT DE LA CANDIDATURE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* Éviter une double candidature */
    if ($candidatureExistante) {

        header(
            "Location: deposer-pieces.php?id=" .
            $candidatureExistante["id_candidature"]
        );

        exit;
    }

    try {

        $pdo->beginTransaction();

        /* Génération du numéro de candidature */

        $numero_candidature =
            "DGLP-" .
            date("Y") .
            "-" .
            strtoupper(bin2hex(random_bytes(4)));

        /* Création de la candidature */

        $insertion = $pdo->prepare("
            INSERT INTO candidature (
                numero_candidature,
                date_candidature,
                statut,
                id_candidat,
                id_offre
            )
            VALUES (
                ?,
                CURDATE(),
                'à compléter',
                ?,
                ?
            )
        ");

        $insertion->execute([
            $numero_candidature,
            $id_candidat,
            $id_offre
        ]);

        /* Récupérer l'identifiant de la candidature */

        $id_candidature = (int) $pdo->lastInsertId();

        $pdo->commit();

        /* Redirection vers le dépôt des pièces */

        header(
            "Location: deposer-pieces.php?id=" .
            $id_candidature
        );

        exit;

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $erreur = "Une erreur est survenue lors de l'enregistrement de votre candidature.";
    }
}

/* =========================================================
   INITIALLES DU CANDIDAT
========================================================= */

$initiales = "";

if (!empty($candidat["prenom"])) {
    $initiales .= strtoupper(
        substr($candidat["prenom"], 0, 1)
    );
}

if (!empty($candidat["nom"])) {
    $initiales .= strtoupper(
        substr($candidat["nom"], 0, 1)
    );
}

if (empty($initiales)) {
    $initiales = "C";
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
        Déposer une candidature - DGLP
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

            padding: 25px 15px;

            box-shadow:
                3px 0 12px rgba(0, 0, 0, 0.15);

            z-index: 1000;

            overflow-y: auto;
        }

        .sidebar-logo {
            text-align: center;

            margin-bottom: 30px;
        }

        .sidebar-logo img {
            width: 105px;
            height: 105px;

            object-fit: cover;

            border-radius: 50%;

            background: white;

            padding: 5px;

            border: 3px solid
                rgba(255, 255, 255, 0.9);
        }

        .sidebar-logo h2 {
            margin-top: 12px;

            font-size: 24px;

            letter-spacing: 1px;
        }

        .sidebar-logo p {
            font-size: 13px;

            opacity: 0.85;

            margin-top: 3px;
        }

        .menu {
            display: flex;

            flex-direction: column;

            gap: 7px;
        }

        .menu a {
            display: flex;

            align-items: center;

            gap: 12px;

            padding: 13px 15px;

            border-radius: 8px;

            font-size: 14px;

            transition: 0.25s;
        }

        .menu a:hover {
            background:
                rgba(255, 255, 255, 0.12);

            transform:
                translateX(3px);
        }

        .menu a.active {
            background: white;

            color: #123b68;

            font-weight: bold;
        }

        .menu-icon {
            width: 23px;

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
            height: 78px;

            background: white;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 10px 30px;

            border-bottom:
                1px solid #e1e5e8;

            box-shadow:
                0 2px 8px
                rgba(0, 0, 0, 0.05);

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
            width: 48px;
            height: 48px;

            object-fit: contain;
        }

        .ministere-text h3 {
            font-size: 15px;

            color: #123b68;

            margin-bottom: 2px;
        }

        .ministere-text p {
            font-size: 12px;

            color: #6b7782;
        }

        .candidate-info {
            display: flex;

            align-items: center;

            gap: 10px;
        }

        .candidate-name {
            font-size: 14px;

            font-weight: bold;

            color: #263746;
        }

        .avatar {
            width: 42px;
            height: 42px;

            background: #008c4a;

            color: white;

            border-radius: 50%;

            display: flex;

            justify-content: center;

            align-items: center;

            font-weight: bold;

            font-size: 14px;
        }

        /* =====================================================
           CONTENU
        ===================================================== */

        .page-content {
            padding: 35px;

            max-width: 1150px;

            margin: auto;
        }

        .page-header {
            margin-bottom: 28px;
        }

        .page-header h1 {
            color: #123b68;

            font-size: 29px;

            margin-bottom: 7px;
        }

        .page-header p {
            color: #6c7883;

            font-size: 14px;
        }

        /* =====================================================
           ERREUR
        ===================================================== */

        .message-erreur {
            background: #fff0f0;

            color: #b42318;

            border-left:
                5px solid #d92d20;

            padding: 15px 18px;

            border-radius: 8px;

            margin-bottom: 25px;

            font-size: 14px;
        }

        /* =====================================================
           CARTE OFFRE
        ===================================================== */

        .card {
            background: white;

            border:
                1px solid #e1e6ea;

            border-radius: 12px;

            box-shadow:
                0 5px 18px
                rgba(0, 0, 0, 0.06);

            padding: 28px;

            margin-bottom: 22px;
        }

        .card-title {
            display: flex;

            justify-content:
                space-between;

            align-items: center;

            gap: 15px;

            margin-bottom: 22px;

            padding-bottom: 15px;

            border-bottom:
                1px solid #edf0f2;
        }

        .card-title h2 {
            color: #123b68;

            font-size: 22px;
        }

        .reference {
            background: #eef4f9;

            color: #123b68;

            padding: 7px 12px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

            white-space: nowrap;
        }

        .description {
            color: #465461;

            margin-bottom: 25px;
        }

        .info-grid {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 15px;

            margin-top: 20px;
        }

        .info-box {
            background: #f7f9fa;

            border:
                1px solid #e5e9ec;

            border-radius: 9px;

            padding: 15px;
        }

        .info-box .label {
            display: block;

            color: #6c7883;

            font-size: 12px;

            margin-bottom: 4px;
        }

        .info-box .value {
            color: #123b68;

            font-weight: bold;

            font-size: 15px;
        }

        .criteria {
            margin-top: 20px;

            padding: 18px;

            background: #f7f9fa;

            border-radius: 9px;

            border-left:
                4px solid #123b68;

            color: #465461;
        }

        .criteria strong {
            color: #123b68;

            display: block;

            margin-bottom: 7px;
        }

        .documents-alert {
            margin-top: 20px;

            padding: 15px 18px;

            background: #fff8e6;

            border:
                1px solid #f2d58a;

            border-left:
                5px solid #d99a00;

            border-radius: 8px;

            color: #755400;

            font-size: 14px;
        }

        /* =====================================================
           CONFIRMATION
        ===================================================== */

        .confirmation {
            background: white;

            border:
                1px solid #e0e6eb;

            border-radius: 12px;

            padding: 28px;

            box-shadow:
                0 5px 18px
                rgba(0, 0, 0, 0.05);
        }

        .confirmation h3 {
            color: #123b68;

            font-size: 20px;

            margin-bottom: 15px;
        }

        .confirmation p {
            color: #596773;

            font-size: 14px;

            margin-bottom: 12px;
        }

        .important {
            background: #eef8f3;

            border-left:
                4px solid #008c4a;

            padding: 14px 16px;

            border-radius: 7px;

            color: #245c42;

            margin: 18px 0 22px;

            font-size: 14px;
        }

        /* =====================================================
           BOUTONS
        ===================================================== */

        .actions {
            display: flex;

            flex-wrap: wrap;

            gap: 12px;

            margin-top: 25px;
        }

        .btn {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 12px 20px;

            border-radius: 7px;

            border: none;

            cursor: pointer;

            font-size: 14px;

            font-weight: bold;

            transition: 0.25s;
        }

        .btn-primary {
            background: #123b68;

            color: white;
        }

        .btn-primary:hover {
            background: #0d2d50;

            transform:
                translateY(-1px);
        }

        .btn-secondary {
            background: #eef1f4;

            color: #40505e;

            border:
                1px solid #d9e0e5;
        }

        .btn-secondary:hover {
            background: #e2e7eb;
        }

        /* =====================================================
           CANDIDATURE EXISTANTE
        ===================================================== */

        .existing-card {
            background: white;

            border:
                1px solid #dfe5e9;

            border-top:
                5px solid #008c4a;

            border-radius: 12px;

            padding: 30px;

            box-shadow:
                0 5px 18px
                rgba(0, 0, 0, 0.06);
        }

        .existing-icon {
            width: 58px;
            height: 58px;

            background: #eaf7f0;

            color: #008c4a;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 27px;

            margin-bottom: 18px;
        }

        .existing-card h2 {
            color: #123b68;

            margin-bottom: 12px;

            font-size: 21px;
        }

        .existing-card p {
            color: #596773;

            font-size: 14px;

            margin-bottom: 8px;
        }

        .existing-card strong {
            color: #123b68;
        }

        .status {
            display: inline-block;

            margin: 8px 0 20px;

            padding: 6px 12px;

            background: #fff4d6;

            color: #806000;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;
        }

        /* =====================================================
           FOOTER
        ===================================================== */

        footer {
            background: #123b68;

            color: white;

            text-align: center;

            padding: 18px;

            font-size: 12px;

            margin-top: 35px;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 900px) {

            .sidebar {
                width: 75px;

                padding: 18px 10px;
            }

            .sidebar-logo h2,
            .sidebar-logo p,
            .menu-text {
                display: none;
            }

            .sidebar-logo img {
                width: 52px;
                height: 52px;
            }

            .menu a {
                justify-content: center;

                padding: 13px 5px;
            }

            .menu-icon {
                font-size: 19px;
            }

            .main {
                margin-left: 75px;
            }

            .top-header {
                padding: 10px 18px;
            }

            .ministere-text {
                display: none;
            }

            .page-content {
                padding: 25px 18px;
            }
        }

        @media (max-width: 650px) {

            .top-header {
                height: 70px;
            }

            .ministere img {
                width: 42px;
                height: 42px;
            }

            .candidate-name {
                display: none;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .card,
            .confirmation,
            .existing-card {
                padding: 20px;
            }

            .card-title {
                flex-direction: column;

                align-items: flex-start;
            }

            .info-grid {
                grid-template-columns: 1fr;
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
     SIDEBAR
========================================================= -->

<aside class="sidebar">

    <div class="sidebar-logo">

        <img
            src="../images/dglp.jpg"
            alt="Logo DGLP"
        >

        <h2>DGLP</h2>

        <p>Espace candidat</p>

    </div>


    <nav class="menu">

        <a href="espacecandidat.php">

            <span class="menu-icon">
                ⌂
            </span>

            <span class="menu-text">
                Tableau de bord
            </span>

        </a>


        <a
            href="offres.php"
            class="active"
        >

            <span class="menu-icon">
                ▣
            </span>

            <span class="menu-text">
                Offres disponibles
            </span>

        </a>


        <a href="dossier.php">

            <span class="menu-icon">
                ◉
            </span>

            <span class="menu-text">
                Mon dossier
            </span>

        </a>


        <a href="suivi-candidature.php">

            <span class="menu-icon">
                ☷
            </span>

            <span class="menu-text">
                Mes candidatures
            </span>

        </a>


        <a href="deconnexion.php">

            <span class="menu-icon">
                ↪
            </span>

            <span class="menu-text">
                Déconnexion
            </span>

        </a>

    </nav>

</aside>


<!-- =========================================================
     CONTENU PRINCIPAL
========================================================= -->

<div class="main">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="top-header">

        <div class="ministere">

            <img
                src="../images/ministere.jpg"
                alt="Logo du ministère"
            >

            <div class="ministere-text">

                <h3>
                    Ministère du Commerce, des PME/PMI
                    et de l'Entrepreneuriat
                </h3>

                <p>
                    Direction Générale de la Lutte contre la Pauvreté
                </p>

            </div>

        </div>


        <div class="candidate-info">

            <div class="candidate-name">

                <?= htmlspecialchars(
                    trim(
                        ($candidat["prenom"] ?? "") .
                        " " .
                        ($candidat["nom"] ?? "")
                    )
                ) ?>

            </div>


            <div class="avatar">

                <?= htmlspecialchars($initiales) ?>

            </div>

        </div>

    </header>


    <!-- =====================================================
         CONTENU DE LA PAGE
    ====================================================== -->

    <main class="page-content">


        <div class="page-header">

            <h1>
                Déposer une candidature
            </h1>

            <p>
                Vérifiez les informations de l'offre
                avant de commencer votre candidature.
            </p>

        </div>


        <!-- =================================================
             MESSAGE D'ERREUR
        ================================================== -->

        <?php if (isset($erreur)): ?>

            <div class="message-erreur">

                <?= htmlspecialchars($erreur) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             CANDIDATURE EXISTANTE
        ================================================== -->

        <?php if ($candidatureExistante): ?>


            <div class="existing-card">

                <div class="existing-icon">
                    ✓
                </div>


                <h2>
                    Vous avez déjà commencé une candidature
                    pour cette offre.
                </h2>


                <p>
                    Une candidature existe déjà pour cette offre.
                    Vous pouvez poursuivre le dépôt des pièces
                    justificatives.
                </p>


                <p>

                    <strong>
                        Numéro de candidature :
                    </strong>

                    <?= htmlspecialchars(
                        $candidatureExistante[
                            "numero_candidature"
                        ]
                    ) ?>

                </p>


                <span class="status">

                    Statut :
                    <?= htmlspecialchars(
                        $candidatureExistante["statut"]
                    ) ?>

                </span>


                <div class="actions">

                    <a
                        href="deposer-pieces.php?id=<?= (int) $candidatureExistante["id_candidature"] ?>"
                        class="btn btn-primary"
                    >
                        Continuer ma candidature
                    </a>


                    <a
                        href="offres.php"
                        class="btn btn-secondary"
                    >
                        Retour aux offres
                    </a>

                </div>

            </div>


        <?php else: ?>


            <!-- =================================================
                 INFORMATIONS DE L'OFFRE
            ================================================== -->

            <div class="card">


                <div class="card-title">

                    <h2>

                        <?= htmlspecialchars(
                            $offre["nom"]
                        ) ?>

                    </h2>


                    <span class="reference">

                        OFFRE-<?= (int) $offre["id_offre"] ?>

                    </span>

                </div>


                <div class="description">

                    <?= nl2br(
                        htmlspecialchars(
                            $offre["description"]
                        )
                    ) ?>

                </div>


                <div class="info-grid">


                    <div class="info-box">

                        <span class="label">
                            Date limite de candidature
                        </span>

                        <span class="value">

                            <?= htmlspecialchars(
                                $offre[
                                    "date_limite_candidature"
                                ]
                            ) ?>

                        </span>

                    </div>


                    <div class="info-box">

                        <span class="label">
                            Nombre de places
                        </span>

                        <span class="value">

                            <?= htmlspecialchars(
                                $offre["nombre_place"]
                            ) ?>

                        </span>

                    </div>


                    <?php if ($offre["montant"] !== null): ?>

                        <div class="info-box">

                            <span class="label">
                                Montant
                            </span>

                            <span class="value">

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

                        <span class="label">
                            Statut de l'offre
                        </span>

                        <span
                            class="value"
                            style="color:#008c4a;"
                        >
                            Offre ouverte
                        </span>

                    </div>

                </div>


                <?php if (!empty($offre["critere"])): ?>

                    <div class="criteria">

                        <strong>
                            Critères d'éligibilité
                        </strong>

                        <?= nl2br(
                            htmlspecialchars(
                                $offre["critere"]
                            )
                        ) ?>

                    </div>

                <?php endif; ?>


                <?php if ($offre["document_supplementaire"]): ?>

                    <div class="documents-alert">

                        <strong>
                            Attention :
                        </strong>

                        des documents supplémentaires peuvent
                        être requis pour cette offre.

                    </div>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 CONFIRMATION
            ================================================== -->

            <div class="confirmation">


                <h3>
                    Confirmation de votre candidature
                </h3>


                <p>
                    Vous êtes sur le point de commencer
                    une candidature pour cette offre de service.
                </p>


                <p>
                    Après confirmation, vous serez redirigé
                    vers l'espace de dépôt des pièces
                    justificatives.
                </p>


                <div class="important">

                    <strong>
                        Important :
                    </strong>

                    votre candidature ne sera pas considérée
                    comme définitivement transmise tant que
                    toutes les pièces justificatives obligatoires
                    n'auront pas été déposées.

                </div>


                <form method="POST">

                    <div class="actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Commencer ma candidature
                        </button>


                        <a
                            href="offres.php"
                            class="btn btn-secondary"
                        >
                            Annuler
                        </a>

                    </div>

                </form>

            </div>


        <?php endif; ?>

    </main>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <footer>

        © 2026 Direction Générale de la Lutte contre la Pauvreté.
        Tous droits réservés.

    </footer>

</div>

</body>

</html>