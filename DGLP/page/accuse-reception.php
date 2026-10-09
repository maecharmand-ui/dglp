<?php

session_start();
require_once "../../database.php";

/* =========================================================
   Vérifier que le candidat est connecté
========================================================= */

if (!isset($_SESSION["id_candidat"])) {
    header("Location: connexion.php");
    exit;
}

/* =========================================================
   Vérifier l'identifiant de la candidature
========================================================= */

if (!isset($_GET["id"]) || !ctype_digit($_GET["id"])) {
    header("Location: espacecandidat.php");
    exit;
}

$id_candidature = (int) $_GET["id"];
$id_candidat = (int) $_SESSION["id_candidat"];

/* =========================================================
   Récupérer les informations du candidat
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
   Récupérer les informations de la candidature
========================================================= */

$requete = $pdo->prepare("
    SELECT
        c.id_candidature,
        c.numero_candidature,
        c.date_candidature,
        c.statut,
        o.id_offre,
        o.nom,
        o.description
    FROM candidature c
    INNER JOIN offre o
        ON c.id_offre = o.id_offre
    WHERE c.id_candidature = ?
    AND c.id_candidat = ?
");

$requete->execute([
    $id_candidature,
    $id_candidat
]);

$candidature = $requete->fetch(PDO::FETCH_ASSOC);

/* =========================================================
   Vérifier que la candidature existe
========================================================= */

if (!$candidature) {
    header("Location: espacecandidat.php");
    exit;
}

/* =========================================================
   Préparer les informations d'affichage
========================================================= */

$prenom = htmlspecialchars($candidat["prenom"] ?? "");
$nom = htmlspecialchars($candidat["nom"] ?? "");

$initialePrenom = !empty($candidat["prenom"])
    ? strtoupper(substr($candidat["prenom"], 0, 1))
    : "";

$initialeNom = !empty($candidat["nom"])
    ? strtoupper(substr($candidat["nom"], 0, 1))
    : "";

$initiales = $initialePrenom . $initialeNom;

$numeroCandidature = htmlspecialchars(
    $candidature["numero_candidature"] ?? ""
);

$nomOffre = htmlspecialchars(
    $candidature["nom"] ?? "Offre de service"
);

$descriptionOffre = htmlspecialchars(
    $candidature["description"] ?? ""
);

$dateCandidature = !empty($candidature["date_candidature"])
    ? date("d/m/Y", strtotime($candidature["date_candidature"]))
    : "-";

$statut = strtolower(trim($candidature["statut"] ?? ""));

/* =========================================================
   Classe CSS du statut
========================================================= */

$classeStatut = "statut-attente";

if (
    in_array(
        $statut,
        [
            "soumise",
            "complet",
            "eligible",
            "éligible",
            "accepte",
            "accepté",
            "valide",
            "validé"
        ],
        true
    )
) {
    $classeStatut = "statut-eligible";
}

if (
    in_array(
        $statut,
        [
            "rejete",
            "rejeté",
            "non eligible",
            "non éligible"
        ],
        true
    )
) {
    $classeStatut = "statut-rejete";
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

    <title>DGLP - Accusé de réception</title>

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
                url("../images/dglp.jpg") center center / 420px auto
                no-repeat fixed;

            background-color: #f4f6f8;
            color: #263238;
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

            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.12);

            z-index: 1000;

            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            text-align: center;
            padding: 25px 15px 20px;

            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .sidebar-logo {
            width: 105px;
            height: 105px;

            object-fit: cover;

            border-radius: 50%;

            background: white;

            padding: 5px;

            margin-bottom: 12px;
        }

        .sidebar-header h2 {
            font-size: 23px;
            margin-bottom: 3px;
        }

        .sidebar-header p {
            font-size: 13px;
            opacity: 0.85;
        }

        /* =====================================================
           MENU
        ===================================================== */

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

            border-radius: 8px;

            font-size: 14px;

            transition: 0.25s;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.12);
            transform: translateX(3px);
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
           MAIN
        ===================================================== */

        .main {
            margin-left: 255px;
            min-height: 100vh;

            display: flex;
            flex-direction: column;
        }

        /* =====================================================
           HEADER
        ===================================================== */

        .top-header {
            min-height: 82px;

            background: rgba(255, 255, 255, 0.96);

            border-bottom: 1px solid #e3e7eb;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 12px 35px;

            position: sticky;
            top: 0;

            z-index: 900;
        }

        .ministere {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .ministere img {
            width: 52px;
            height: 52px;

            object-fit: contain;
        }

        .ministere h1 {
            font-size: 17px;
            color: #123b68;
            margin-bottom: 2px;
        }

        .ministere p {
            font-size: 12px;
            color: #68737d;
        }

        .candidate-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .candidate-avatar {
            width: 42px;
            height: 42px;

            border-radius: 50%;

            background: #008c4a;
            color: white;

            display: flex;
            align-items: center;
            justify-content: center;

            font-weight: bold;
            font-size: 14px;
        }

        .candidate-user strong {
            display: block;
            font-size: 14px;
            color: #123b68;
        }

        .candidate-user span {
            display: block;
            font-size: 11px;
            color: #777;
        }

        /* =====================================================
           CONTENU
        ===================================================== */

        .content {
            width: 100%;
            max-width: 1100px;

            margin: 0 auto;

            padding: 35px;
            flex: 1;
        }

        .page-title {
            margin-bottom: 25px;
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

        /* =====================================================
           ACCUSÉ
        ===================================================== */

        .receipt-wrapper {
            max-width: 850px;
            margin: 20px auto;
        }

        .receipt-card {
            background: white;

            border-radius: 14px;

            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);

            overflow: hidden;

            border: 1px solid #e3e7eb;
        }

        .receipt-header {
            background: #123b68;
            color: white;

            padding: 35px 30px;

            text-align: center;
        }

        .success-icon {
            width: 70px;
            height: 70px;

            border-radius: 50%;

            background: white;
            color: #008c4a;

            display: flex;
            align-items: center;
            justify-content: center;

            margin: 0 auto 15px;

            font-size: 38px;
            font-weight: bold;
        }

        .receipt-header h3 {
            font-size: 25px;
            margin-bottom: 8px;
        }

        .receipt-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        /* =====================================================
           NUMÉRO CANDIDATURE
        ===================================================== */

        .numero-box {
            margin: 30px;

            background: #f0f8f4;

            border: 2px dashed #008c4a;

            border-radius: 10px;

            padding: 22px;

            text-align: center;
        }

        .numero-box span {
            display: block;

            color: #667;
            font-size: 13px;

            margin-bottom: 7px;
        }

        .numero-box strong {
            display: block;

            color: #008c4a;

            font-size: 25px;

            letter-spacing: 1px;
        }

        .numero-box small {
            display: block;

            margin-top: 8px;

            color: #6c757d;
            font-size: 12px;
        }

        /* =====================================================
           INFORMATIONS
        ===================================================== */

        .receipt-content {
            padding: 0 30px 30px;
        }

        .section-title {
            color: #123b68;

            font-size: 17px;

            margin-bottom: 15px;

            padding-bottom: 8px;

            border-bottom: 2px solid #eef1f4;
        }

        .info-grid {
            display: grid;

            grid-template-columns: repeat(2, 1fr);

            gap: 15px;

            margin-bottom: 25px;
        }

        .info-item {
            background: #f7f9fa;

            border-radius: 8px;

            padding: 15px;
        }

        .info-item label {
            display: block;

            color: #78838d;

            font-size: 12px;

            margin-bottom: 4px;
        }

        .info-item strong {
            color: #263238;

            font-size: 14px;
        }

        /* =====================================================
           STATUT
        ===================================================== */

        .statut {
            display: inline-block;

            padding: 6px 13px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

            text-transform: capitalize;
        }

        .statut-attente {
            background: #fff4d6;
            color: #946c00;
        }

        .statut-eligible {
            background: #e4f7ed;
            color: #008c4a;
        }

        .statut-rejete {
            background: #fde8e8;
            color: #b42318;
        }

        /* =====================================================
           DESCRIPTION
        ===================================================== */

        .description {
            background: #f7f9fa;

            border-left: 4px solid #123b68;

            padding: 18px;

            border-radius: 6px;

            color: #555;

            font-size: 14px;

            margin-bottom: 25px;
        }

        /* =====================================================
           MESSAGE
        ===================================================== */

        .confirmation-message {
            background: #eef7ff;

            border-left: 4px solid #123b68;

            border-radius: 7px;

            padding: 17px;

            margin-bottom: 25px;

            color: #46515a;

            font-size: 14px;
        }

        .confirmation-message strong {
            color: #123b68;
        }

        /* =====================================================
           BOUTONS
        ===================================================== */

        .actions {
            display: flex;

            justify-content: center;

            gap: 12px;

            flex-wrap: wrap;

            padding-top: 5px;
        }

        .btn {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 12px 20px;

            border-radius: 7px;

            font-size: 14px;

            font-weight: bold;

            transition: 0.25s;

            border: none;

            cursor: pointer;
        }

        .btn-primary {
            background: #123b68;
            color: white;
        }

        .btn-primary:hover {
            background: #0d2d50;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #008c4a;
            color: white;
        }

        .btn-secondary:hover {
            background: #00743e;
            transform: translateY(-1px);
        }

        /* =====================================================
           FOOTER
        ===================================================== */

        footer {
            background: #123b68;

            color: white;

            text-align: center;

            padding: 16px;

            font-size: 12px;

            margin-top: auto;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 900px) {

            .sidebar {
                width: 75px;
            }

            .sidebar-header h2,
            .sidebar-header p,
            .sidebar-menu span {
                display: none;
            }

            .sidebar-header {
                padding: 15px 5px;
            }

            .sidebar-logo {
                width: 50px;
                height: 50px;
            }

            .sidebar-menu a {
                justify-content: center;
                padding: 14px 5px;
            }

            .menu-icon {
                width: auto;
                font-size: 20px;
            }

            .main {
                margin-left: 75px;
            }

            .top-header {
                padding: 12px 20px;
            }

            .content {
                padding: 25px 20px;
            }

        }

        @media (max-width: 650px) {

            .top-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .candidate-user {
                align-self: flex-end;
            }

            .ministere h1 {
                font-size: 14px;
            }

            .ministere p {
                font-size: 10px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .receipt-header {
                padding: 25px 20px;
            }

            .receipt-header h3 {
                font-size: 21px;
            }

            .numero-box {
                margin: 20px;
            }

            .receipt-content {
                padding: 0 20px 25px;
            }

            .numero-box strong {
                font-size: 19px;
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

    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="sidebar">

        <div class="sidebar-header">

            <img
                src="../images/dglp.jpg"
                alt="Logo DGLP"
                class="sidebar-logo"
            >

            <h2>DGLP</h2>

            <p>Espace candidat</p>

        </div>

        <nav class="sidebar-menu">

            <a href="espacecandidat.php">

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

            <a
                href="suivi-candidature.php"
                class="active"
            >

                <span class="menu-icon">✓</span>

                <span>Mes candidatures</span>

            </a>

            <a href="deconnexion.php">

                <span class="menu-icon">↪</span>

                <span>Déconnexion</span>

            </a>

        </nav>

    </aside>


    <!-- =====================================================
         CONTENU PRINCIPAL
    ====================================================== -->

    <div class="main">

        <!-- =================================================
             HEADER
        ================================================== -->

        <header class="top-header">

            <div class="ministere">

                <img
                    src="../images/ministere.jpg"
                    alt="Ministère"
                >

                <div>

                    <h1>
                        Ministère du Commerce, des PME/PMI
                        et de l'Entrepreneuriat
                    </h1>

                    <p>
                        Direction Générale de la Lutte contre la Pauvreté
                    </p>

                </div>

            </div>


            <div class="candidate-user">

                <div class="candidate-avatar">
                    <?= htmlspecialchars($initiales) ?>
                </div>

                <div>

                    <strong>
                        <?= $prenom . " " . $nom ?>
                    </strong>

                    <span>
                        Candidat
                    </span>

                </div>

            </div>

        </header>


        <!-- =================================================
             CONTENU
        ================================================== -->

        <main class="content">

            <div class="page-title">

                <h2>
                    Accusé de réception
                </h2>

                <p>
                    Confirmation de l'enregistrement de votre candidature
                </p>

            </div>


            <div class="receipt-wrapper">

                <div class="receipt-card">

                    <!-- ================================
                         EN-TÊTE DE L'ACCUSÉ
                    ================================= -->

                    <div class="receipt-header">

                        <div class="success-icon">
                            ✓
                        </div>

                        <h3>
                            Candidature enregistrée
                        </h3>

                        <p>
                            Votre candidature a bien été prise en compte.
                        </p>

                    </div>


                    <!-- ================================
                         NUMÉRO DE CANDIDATURE
                    ================================= -->

                    <div class="numero-box">

                        <span>
                            Votre numéro de candidature
                        </span>

                        <strong>
                            <?= $numeroCandidature ?>
                        </strong>

                        <small>
                            Veuillez conserver précieusement ce numéro
                            pour suivre l'évolution de votre candidature.
                        </small>

                    </div>


                    <div class="receipt-content">

                        <!-- ================================
                             INFORMATIONS
                        ================================= -->

                        <h4 class="section-title">
                            Informations de la candidature
                        </h4>

                        <div class="info-grid">

                            <div class="info-item">

                                <label>
                                    Offre de service
                                </label>

                                <strong>
                                    <?= $nomOffre ?>
                                </strong>

                            </div>


                            <div class="info-item">

                                <label>
                                    Référence de l'offre
                                </label>

                                <strong>
                                    OFFRE-<?= (int) $candidature["id_offre"] ?>
                                </strong>

                            </div>


                            <div class="info-item">

                                <label>
                                    Date de candidature
                                </label>

                                <strong>
                                    <?= $dateCandidature ?>
                                </strong>

                            </div>


                            <div class="info-item">

                                <label>
                                    Statut actuel
                                </label>

                                <strong>

                                    <span class="statut <?= $classeStatut ?>">
                                        <?= htmlspecialchars($candidature["statut"]) ?>
                                    </span>

                                </strong>

                            </div>

                        </div>


                        <!-- ================================
                             DESCRIPTION
                        ================================= -->

                        <?php if (!empty($descriptionOffre)): ?>

                            <h4 class="section-title">
                                Description de l'offre
                            </h4>

                            <div class="description">

                                <?= nl2br($descriptionOffre) ?>

                            </div>

                        <?php endif; ?>


                        <!-- ================================
                             MESSAGE
                        ================================= -->

                        <div class="confirmation-message">

                            <strong>
                                Confirmation :
                            </strong>

                            votre candidature a été enregistrée
                            avec succès dans le système de la Direction
                            Générale de la Lutte contre la Pauvreté.

                            <br><br>

                            Vous pouvez consulter à tout moment
                            l'état d'avancement de votre candidature
                            depuis la rubrique
                            <strong>« Mes candidatures »</strong>.

                        </div>


                        <!-- ================================
                             ACTIONS
                        ================================= -->

                        <div class="actions">

                            <a
                                href="suivi-candidature.php"
                                class="btn btn-primary"
                            >
                                ← Mes candidatures
                            </a>

                            <a
                                href="espacecandidat.php"
                                class="btn btn-secondary"
                            >
                                Tableau de bord
                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </main>


        <!-- =================================================
             FOOTER
        ================================================== -->

        <footer>

            © 2026 Direction Générale de la Lutte contre la Pauvreté -
            Tous droits réservés.

        </footer>

    </div>

</body>

</html>