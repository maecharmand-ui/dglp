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
   VARIABLES
   ========================================================= */

$message = "";
$type_message = "";

/* =========================================================
   TRAITEMENT DE LA DÉCISION
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id_candidature = filter_input(
        INPUT_POST,
        "id_candidature",
        FILTER_VALIDATE_INT
    );

    $decision_finale = trim(
        $_POST["decision_finale"] ?? ""
    );

    $motif = trim(
        $_POST["motif"] ?? ""
    );

    /* =====================================================
       VÉRIFICATIONS
       ===================================================== */

    if (!$id_candidature) {

        $message = "Candidature invalide.";
        $type_message = "erreur";

    } elseif (
        !in_array(
            $decision_finale,
            [
                "Acceptée",
                "Refusée",
                "En attente"
            ],
            true
        )
    ) {

        $message = "Décision finale invalide.";
        $type_message = "erreur";

    } elseif ($motif === "") {

        $message =
            "Veuillez saisir le motif de la décision.";

        $type_message = "erreur";

    } else {

        try {

            /* =================================================
               DÉBUT DE LA TRANSACTION
               ================================================= */

            $pdo->beginTransaction();

            /* =================================================
               VÉRIFIER QUE LA CANDIDATURE EST CLASSÉE
               ================================================= */

            $verification = $pdo->prepare("
                SELECT
                    cl.id_classement,
                    cl.rang,
                    cl.score,
                    cl.statut,

                    c.id_candidature,
                    c.numero_candidature,

                    ca.nom,
                    ca.prenom,

                    o.nom AS nom_offre

                FROM classement cl

                INNER JOIN candidature c
                    ON cl.id_candidature = c.id_candidature

                INNER JOIN candidat ca
                    ON c.id_candidat = ca.id_candidat

                INNER JOIN offre o
                    ON c.id_offre = o.id_offre

                WHERE c.id_candidature = ?
            ");

            $verification->execute([
                $id_candidature
            ]);

            $candidature = $verification->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$candidature) {

                $pdo->rollBack();

                $message =
                    "Cette candidature n'est pas encore classée.";

                $type_message = "erreur";

            } else {

                /* =================================================
                   RÉCUPÉRER LE CONTRÔLE D'ÉLIGIBILITÉ
                   ================================================= */

                $requeteControle = $pdo->prepare("
                    SELECT
                        id_controle
                    FROM controle_eligibilite
                    WHERE id_candidature = ?
                    LIMIT 1
                ");

                $requeteControle->execute([
                    $id_candidature
                ]);

                $controle = $requeteControle->fetch(
                    PDO::FETCH_ASSOC
                );

                if (!$controle) {

                    $pdo->rollBack();

                    $message =
                        "Aucun contrôle d'éligibilité n'est associé à cette candidature.";

                    $type_message = "erreur";

                } else {

                    /* =================================================
                       VÉRIFIER SI UNE DÉCISION EXISTE DÉJÀ
                       ================================================= */

                    $requeteDecision = $pdo->prepare("
                        SELECT
                            id_decision,
                            decision_finale,
                            motif
                        FROM decision
                        WHERE id_controle = ?
                        LIMIT 1
                    ");

                    $requeteDecision->execute([
                        $controle["id_controle"]
                    ]);

                    $decisionExistante =
                        $requeteDecision->fetch(
                            PDO::FETCH_ASSOC
                        );

                    /* =================================================
                       ENREGISTRER OU MODIFIER LA DÉCISION
                       ================================================= */

                    if ($decisionExistante) {

                        $requete = $pdo->prepare("
                            UPDATE decision
                            SET
                                id_admin = ?,
                                decision_finale = ?,
                                motif = ?,
                                date_decision = NOW()
                            WHERE id_decision = ?
                        ");

                        $requete->execute([
                            $_SESSION["id_admin"],
                            $decision_finale,
                            $motif,
                            $decisionExistante["id_decision"]
                        ]);

                        $type_action =
                            "Décision finale modifiée";

                    } else {

                        $requete = $pdo->prepare("
                            INSERT INTO decision (
                                id_controle,
                                id_admin,
                                decision_finale,
                                motif,
                                date_decision
                            )
                            VALUES (?, ?, ?, ?, NOW())
                        ");

                        $requete->execute([
                            $controle["id_controle"],
                            $_SESSION["id_admin"],
                            $decision_finale,
                            $motif
                        ]);

                        $type_action =
                            "Décision finale enregistrée";
                    }

                    /* =================================================
                       DÉTERMINER LE STATUT DE LA CANDIDATURE
                       ================================================= */

                    $nouveauStatut = "en attente";

                    if ($decision_finale === "Acceptée") {

                        $nouveauStatut = "acceptée";

                    } elseif ($decision_finale === "Refusée") {

                        $nouveauStatut = "refusée";
                    }

                    /* =================================================
                       METTRE À JOUR LE STATUT DE LA CANDIDATURE
                       ================================================= */

                    $miseAJour = $pdo->prepare("
                        UPDATE candidature
                        SET statut = ?
                        WHERE id_candidature = ?
                    ");

                    $miseAJour->execute([
                        $nouveauStatut,
                        $id_candidature
                    ]);

                    /* =================================================
                       HISTORIQUE
                       ================================================= */

                    $descriptionHistorique =
                        $type_action
                        . " pour la candidature "
                        . $candidature["numero_candidature"]
                        . ". Décision : "
                        . $decision_finale
                        . ". Motif : "
                        . $motif
                        . ". Nouveau statut de la candidature : "
                        . $nouveauStatut
                        . ".";

                    enregistrerHistorique(
                        $pdo,
                        (int) $_SESSION["id_admin"],
                        $id_candidature,
                        "Décision finale",
                        $descriptionHistorique
                    );

                    /* =================================================
                       VALIDER LA TRANSACTION
                       ================================================= */

                    $pdo->commit();

                    /* =================================================
                       MESSAGE DE SUCCÈS
                       ================================================= */

                    if ($decisionExistante) {

                        $message =
                            "La décision finale a été mise à jour et enregistrée dans l'historique.";

                    } else {

                        $message =
                            "La décision finale a été enregistrée et ajoutée à l'historique.";
                    }

                    $type_message = "succes";
                }
            }

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message =
                "Une erreur est survenue lors de l'enregistrement de la décision.";

            $type_message = "erreur";
        }
    }
}

/* =========================================================
   RÉCUPÉRER LES CANDIDATURES CLASSÉES
   ========================================================= */

$requete = $pdo->query("
    SELECT

        cl.id_classement,
        cl.id_candidature,
        cl.rang,
        cl.score,
        cl.statut AS statut_classement,

        c.numero_candidature,
        c.date_candidature,
        c.statut AS statut_candidature,

        ca.nom AS nom_candidat,
        ca.prenom AS prenom_candidat,

        o.nom AS nom_offre,

        ce.id_controle,

        d.id_decision,
        d.decision_finale,
        d.motif,
        d.date_decision

    FROM classement cl

    INNER JOIN candidature c
        ON cl.id_candidature = c.id_candidature

    INNER JOIN candidat ca
        ON c.id_candidat = ca.id_candidat

    INNER JOIN offre o
        ON c.id_offre = o.id_offre

    INNER JOIN controle_eligibilite ce
        ON c.id_candidature = ce.id_candidature

    LEFT JOIN decision d
        ON ce.id_controle = d.id_controle

    ORDER BY
        o.nom ASC,
        cl.rang ASC,
        cl.score DESC
");

$candidatures = $requete->fetchAll(
    PDO::FETCH_ASSOC
);


/* =========================================================
   STATISTIQUES
   ========================================================= */

$totalClassees = count($candidatures);

$totalAcceptees = 0;
$totalRefusees = 0;
$totalAttente = 0;
$totalDecisions = 0;

foreach ($candidatures as $item) {

    if (!empty($item["id_decision"])) {
        $totalDecisions++;
    }

    if ($item["decision_finale"] === "Acceptée") {
        $totalAcceptees++;
    }

    if ($item["decision_finale"] === "Refusée") {
        $totalRefusees++;
    }

    if (
        $item["decision_finale"] === "En attente"
        || empty($item["decision_finale"])
    ) {
        $totalAttente++;
    }
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
        Décisions finales - DGLP
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
            font-family: Arial, sans-serif;

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

            box-shadow:
                4px 0 15px rgba(0, 0, 0, 0.12);

            z-index: 1000;

            overflow-y: auto;
        }

        .sidebar-logo {
            text-align: center;

            padding-bottom: 22px;

            border-bottom:
                1px solid rgba(255,255,255,0.18);

            margin-bottom: 20px;
        }

        .sidebar-logo img {
            width: 92px;
            height: 92px;

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

        .sidebar-logo span {
            display: block;

            font-size: 12px;

            opacity: 0.82;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 7px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;

            gap: 12px;

            padding: 12px 13px;

            border-radius: 8px;

            font-size: 14px;

            transition: 0.2s ease;
        }

        .sidebar-menu a:hover {
            background:
                rgba(255,255,255,0.12);
        }

        .sidebar-menu a.active {
            background: white;
            color: #123b68;

            font-weight: bold;
        }

        .menu-icon {
            width: 24px;

            text-align: center;

            font-size: 16px;
        }

        .logout-link {
            margin-top: 20px;

            border-top:
                1px solid rgba(255,255,255,0.18);

            padding-top: 20px;
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

            border-bottom:
                1px solid #e1e6eb;

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
            width: 48px;
            height: 48px;

            object-fit: contain;
        }

        .ministere-text strong {
            display: block;

            color: #123b68;

            font-size: 15px;
        }

        .ministere-text span {
            display: block;

            color: #6b7785;

            font-size: 12px;
        }

        .admin-user {
            display: flex;

            align-items: center;

            gap: 10px;
        }

        .admin-avatar {
            width: 40px;
            height: 40px;

            border-radius: 50%;

            background: #008c4a;

            color: white;

            display: flex;

            align-items: center;
            justify-content: center;

            font-weight: bold;

            font-size: 14px;
        }

        .admin-user strong {
            display: block;

            color: #263238;

            font-size: 13px;
        }

        .admin-user span {
            display: block;

            color: #7a8793;

            font-size: 11px;
        }

        /* =====================================================
           CONTENU
        ===================================================== */

        .content {
            padding: 30px 35px 45px;

            max-width: 1500px;
        }

        .breadcrumb {
            color: #7c8995;

            font-size: 12px;

            margin-bottom: 7px;
        }

        .breadcrumb a {
            color: #123b68;

            font-weight: bold;
        }

        .page-title {
            margin-bottom: 25px;
        }

        .page-title h1 {
            color: #123b68;

            font-size: 28px;

            margin-bottom: 5px;
        }

        .page-title p {
            color: #697784;

            font-size: 14px;
        }

        /* =====================================================
           MESSAGES
        ===================================================== */

        .message {
            padding: 15px 18px;

            border-radius: 9px;

            margin-bottom: 22px;

            font-size: 14px;

            border-left: 5px solid;
        }

        .message.succes {
            background: #e8f7ef;

            color: #17633c;

            border-left-color: #008c4a;
        }

        .message.erreur {
            background: #fdecec;

            color: #8b2222;

            border-left-color: #c62828;
        }

        /* =====================================================
           STATISTIQUES
        ===================================================== */

        .stats {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 16px;

            margin-bottom: 25px;
        }

        .stat-card {
            background: white;

            border:
                1px solid #e2e7ec;

            border-radius: 11px;

            padding: 19px;

            box-shadow:
                0 5px 18px
                rgba(18,59,104,0.05);

            position: relative;

            overflow: hidden;
        }

        .stat-card::before {
            content: "";

            position: absolute;

            top: 0;
            left: 0;

            width: 4px;
            height: 100%;

            background: #123b68;
        }

        .stat-card.green::before {
            background: #008c4a;
        }

        .stat-card.red::before {
            background: #c62828;
        }

        .stat-card.orange::before {
            background: #d99400;
        }

        .stat-label {
            color: #71808d;

            font-size: 11px;

            text-transform: uppercase;

            font-weight: bold;

            margin-bottom: 5px;
        }

        .stat-number {
            color: #123b68;

            font-size: 27px;

            font-weight: bold;
        }

        .stat-card.green .stat-number {
            color: #008c4a;
        }

        .stat-card.red .stat-number {
            color: #b52b2b;
        }

        .stat-card.orange .stat-number {
            color: #a56c00;
        }

        /* =====================================================
           CARTE PRINCIPALE
        ===================================================== */

        .card {
            background: white;

            border:
                1px solid #e2e7ec;

            border-radius: 12px;

            padding: 24px;

            box-shadow:
                0 5px 18px
                rgba(18,59,104,0.06);
        }

        .card-header {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            margin-bottom: 20px;

            padding-bottom: 15px;

            border-bottom:
                1px solid #edf0f3;
        }

        .card-header h2 {
            color: #123b68;

            font-size: 19px;
        }

        .card-header p {
            color: #788692;

            font-size: 12px;

            margin-top: 3px;
        }

        /* =====================================================
           TABLEAU
        ===================================================== */

        .table-wrapper {
            width: 100%;

            overflow-x: auto;

            border:
                1px solid #e2e7ec;

            border-radius: 9px;
        }

        table {
            width: 100%;

            min-width: 1150px;

            border-collapse: collapse;
        }

        thead {
            background: #123b68;

            color: white;
        }

        th {
            padding: 13px 12px;

            text-align: left;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 0.3px;

            white-space: nowrap;
        }

        td {
            padding: 14px 12px;

            border-bottom:
                1px solid #edf0f3;

            vertical-align: top;

            font-size: 13px;

            color: #475560;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* =====================================================
           RANG
        ===================================================== */

        .rang {
            width: 35px;
            height: 35px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: #eaf1f8;

            color: #123b68;

            font-weight: bold;

            font-size: 13px;
        }

        /* =====================================================
           CANDIDAT
        ===================================================== */

        .candidate {
            display: flex;

            align-items: center;

            gap: 9px;

            min-width: 180px;
        }

        .candidate-avatar {
            width: 36px;
            height: 36px;

            flex-shrink: 0;

            border-radius: 50%;

            background: #123b68;

            color: white;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 11px;

            font-weight: bold;
        }

        .candidate-name strong {
            display: block;

            color: #263238;

            font-size: 13px;
        }

        .candidate-name span {
            display: block;

            color: #87939d;

            font-size: 10px;
        }

        /* =====================================================
           SCORE
        ===================================================== */

        .score {
            display: inline-block;

            background: #edf5fb;

            color: #123b68;

            padding: 6px 9px;

            border-radius: 6px;

            font-weight: bold;

            white-space: nowrap;
        }

        /* =====================================================
           BADGES
        ===================================================== */

        .badge {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 5px 10px;

            border-radius: 20px;

            font-size: 10px;

            font-weight: bold;

            white-space: nowrap;
        }

        .badge-green {
            background: #e7f7ef;

            color: #08733d;
        }

        .badge-red {
            background: #fdeaea;

            color: #a32323;
        }

        .badge-orange {
            background: #fff4df;

            color: #9a6500;
        }

        .badge-blue {
            background: #e8f1fa;

            color: #123b68;
        }

        .badge-gray {
            background: #edf0f2;

            color: #59636d;
        }

        /* =====================================================
           FORMULAIRE DÉCISION
        ===================================================== */

        .decision-form {
            min-width: 280px;

            background: #f8fafc;

            border:
                1px solid #e4e9ed;

            border-radius: 9px;

            padding: 13px;
        }

        .decision-form label {
            display: block;

            color: #45545f;

            font-size: 11px;

            font-weight: bold;

            margin-bottom: 5px;
        }

        .decision-form select,
        .decision-form textarea {
            width: 100%;

            border:
                1px solid #d6dde3;

            border-radius: 7px;

            background: white;

            padding: 9px;

            color: #263238;

            font-family: Arial, sans-serif;

            font-size: 12px;

            outline: none;

            margin-bottom: 10px;
        }

        .decision-form select:focus,
        .decision-form textarea:focus {
            border-color: #123b68;

            box-shadow:
                0 0 0 3px
                rgba(18,59,104,0.08);
        }

        .decision-form textarea {
            min-height: 70px;

            resize: vertical;
        }

        .decision-form button {
            width: 100%;

            border: none;

            border-radius: 7px;

            padding: 9px 12px;

            background: #123b68;

            color: white;

            font-size: 12px;

            font-weight: bold;

            cursor: pointer;

            transition: 0.2s;
        }

        .decision-form button:hover {
            background: #0d2d50;

            transform: translateY(-1px);
        }

        /* =====================================================
           MOTIF EXISTANT
        ===================================================== */

        .motif {
            max-width: 220px;

            color: #596773;

            font-size: 12px;

            line-height: 1.5;
        }

        /* =====================================================
           ÉTAT VIDE
        ===================================================== */

        .empty-state {
            text-align: center;

            padding: 55px 20px;

            color: #7a8792;
        }

        .empty-icon {
            width: 60px;
            height: 60px;

            border-radius: 50%;

            background: #edf2f6;

            color: #123b68;

            display: flex;

            align-items: center;
            justify-content: center;

            margin: 0 auto 15px;

            font-size: 25px;
        }

        .empty-state h3 {
            color: #123b68;

            margin-bottom: 6px;

            font-size: 17px;
        }

        .empty-state p {
            font-size: 13px;
        }

        /* =====================================================
           FOOTER
        ===================================================== */

        .footer {
            margin-left: 255px;

            background: #0d2d50;

            color:
                rgba(255,255,255,0.82);

            text-align: center;

            padding: 17px;

            font-size: 11px;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1150px) {

            .sidebar {
                width: 75px;

                padding: 15px 8px;
            }

            .sidebar-logo h2,
            .sidebar-logo span,
            .sidebar-menu span:not(.menu-icon) {
                display: none;
            }

            .sidebar-logo img {
                width: 48px;
                height: 48px;
            }

            .sidebar-menu a {
                justify-content: center;

                padding: 13px 5px;
            }

            .menu-icon {
                width: auto;
            }

            .main {
                margin-left: 75px;
            }

            .footer {
                margin-left: 75px;
            }

            .stats {
                grid-template-columns:
                    repeat(2, 1fr);
            }
        }

        @media (max-width: 760px) {

            .top-header {
                min-height: 75px;

                height: auto;

                padding: 12px 18px;
            }

            .ministere-text {
                display: none;
            }

            .content {
                padding:
                    22px 15px 35px;
            }

            .page-title h1 {
                font-size: 23px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 17px;
            }
        }

        @media (max-width: 480px) {

            .admin-user > div:last-child {
                display: none;
            }

            .top-header {
                padding: 11px 13px;
            }

            .page-title h1 {
                font-size: 21px;
            }

            .stat-number {
                font-size: 24px;
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

        <h2>
            DGLP
        </h2>

        <span>
            Administration
        </span>

    </div>


    <ul class="sidebar-menu">

        <li>

            <a href="dashboard.php">

                <span class="menu-icon">
                    ▣
                </span>

                <span>
                    Tableau de bord
                </span>

            </a>

        </li>


        <li>

            <a href="offres.php">

                <span class="menu-icon">
                    ▤
                </span>

                <span>
                    Offres
                </span>

            </a>

        </li>


        <li>

            <a href="candidatures.php">

                <span class="menu-icon">
                    ☷
                </span>

                <span>
                    Candidatures
                </span>

            </a>

        </li>


        <li>

            <a href="classement.php">

                <span class="menu-icon">
                    ★
                </span>

                <span>
                    Classement
                </span>

            </a>

        </li>


        <li>

            <a
                href="decision.php"
                class="active"
            >

                <span class="menu-icon">
                    ✓
                </span>

                <span>
                    Décisions
                </span>

            </a>

        </li>


        <li>

            <a href="historique.php">

                <span class="menu-icon">
                    ◷
                </span>

                <span>
                    Historique
                </span>

            </a>

        </li>


        <li class="logout-link">

            <a href="deconnexion.php">

                <span class="menu-icon">
                    ↪
                </span>

                <span>
                    Déconnexion
                </span>

            </a>

        </li>

    </ul>

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
                    Ministère du Commerce,
                    des PME/PMI et de l'Entrepreneuriat
                </strong>

                <span>
                    Direction Générale de la Lutte contre la Pauvreté
                </span>

            </div>

        </div>


        <div class="admin-user">

            <?php

            $nomAdmin =
                $_SESSION["nom_admin"] ?? "";

            $prenomAdmin =
                $_SESSION["prenom_admin"] ?? "";

            $initialesAdmin =
                strtoupper(
                    substr($prenomAdmin, 0, 1)
                    . substr($nomAdmin, 0, 1)
                );

            ?>

            <div class="admin-avatar">

                <?= htmlspecialchars(
                    $initialesAdmin
                ) ?>

            </div>

            <div>

                <strong>

                    <?= htmlspecialchars(
                        $prenomAdmin
                    ) ?>

                    <?= htmlspecialchars(
                        $nomAdmin
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

        <div class="page-title">

            <div class="breadcrumb">

                <a href="dashboard.php">
                    Administration
                </a>

                &nbsp; / &nbsp;

                Décisions finales

            </div>

            <h1>
                Décisions finales
            </h1>

            <p>
                Prenez la décision finale concernant les
                candidatures ayant été classées.
            </p>

        </div>


        <!-- =================================================
             MESSAGE
             ================================================= -->

        <?php if ($message !== ""): ?>

            <div
                class="message <?= htmlspecialchars(
                    $type_message
                ) ?>"
            >

                <?= htmlspecialchars(
                    $message
                ) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             STATISTIQUES
             ================================================= -->

        <div class="stats">

            <div class="stat-card">

                <div class="stat-label">
                    Candidatures classées
                </div>

                <div class="stat-number">
                    <?= $totalClassees ?>
                </div>

            </div>


            <div class="stat-card green">

                <div class="stat-label">
                    Décisions acceptées
                </div>

                <div class="stat-number">
                    <?= $totalAcceptees ?>
                </div>

            </div>


            <div class="stat-card red">

                <div class="stat-label">
                    Décisions refusées
                </div>

                <div class="stat-number">
                    <?= $totalRefusees ?>
                </div>

            </div>


            <div class="stat-card orange">

                <div class="stat-label">
                    En attente
                </div>

                <div class="stat-number">
                    <?= $totalAttente ?>
                </div>

            </div>

        </div>


        <!-- =================================================
             LISTE
             ================================================= -->

        <section class="card">

            <div class="card-header">

                <div>

                    <h2>
                        Candidatures classées
                    </h2>

                    <p>
                        Enregistrez ou modifiez la décision
                        finale pour chaque candidat.
                    </p>

                </div>

                <span class="badge badge-blue">

                    <?= $totalClassees ?>

                    candidature(s)

                </span>

            </div>


            <?php if (empty($candidatures)): ?>

                <div class="empty-state">

                    <div class="empty-icon">
                        ✓
                    </div>

                    <h3>
                        Aucune candidature classée
                    </h3>

                    <p>
                        Les candidatures apparaîtront ici
                        après leur classement.
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
                                    Décision actuelle
                                </th>

                                <th>
                                    Motif précédent
                                </th>

                                <th>
                                    Nouvelle décision
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach (
                            $candidatures
                            as $candidature
                        ): ?>

                            <?php

                            $initiales =
                                strtoupper(
                                    substr(
                                        $candidature[
                                            "prenom_candidat"
                                        ],
                                        0,
                                        1
                                    )
                                    .
                                    substr(
                                        $candidature[
                                            "nom_candidat"
                                        ],
                                        0,
                                        1
                                    )
                                );

                            $decision =
                                $candidature[
                                    "decision_finale"
                                ] ?? "";

                            ?>

                            <tr>

                                <!-- RANG -->

                                <td>

                                    <span class="rang">

                                        <?= htmlspecialchars(
                                            $candidature["rang"]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- CANDIDATURE -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $candidature[
                                                "numero_candidature"
                                            ]
                                        ) ?>

                                    </strong>

                                    <br>

                                    <span
                                        style="
                                            font-size:10px;
                                            color:#87939d;
                                        "
                                    >

                                        <?= htmlspecialchars(
                                            $candidature[
                                                "date_candidature"
                                            ]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- CANDIDAT -->

                                <td>

                                    <div class="candidate">

                                        <div
                                            class="candidate-avatar"
                                        >

                                            <?= htmlspecialchars(
                                                $initiales
                                            ) ?>

                                        </div>

                                        <div
                                            class="candidate-name"
                                        >

                                            <strong>

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

                                            </strong>

                                            <span>
                                                Candidat
                                            </span>

                                        </div>

                                    </div>

                                </td>


                                <!-- OFFRE -->

                                <td>

                                    <span
                                        style="
                                            font-weight:bold;
                                            color:#123b68;
                                        "
                                    >

                                        <?= htmlspecialchars(
                                            $candidature[
                                                "nom_offre"
                                            ]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- SCORE -->

                                <td>

                                    <span class="score">

                                        <?= number_format(
                                            (float)
                                            $candidature["score"],
                                            2,
                                            ",",
                                            " "
                                        ) ?>

                                        /100

                                    </span>

                                </td>


                                <!-- DÉCISION -->

                                <td>

                                    <?php if (
                                        $decision
                                        === "Acceptée"
                                    ): ?>

                                        <span
                                            class="badge badge-green"
                                        >
                                            Acceptée
                                        </span>

                                    <?php elseif (
                                        $decision
                                        === "Refusée"
                                    ): ?>

                                        <span
                                            class="badge badge-red"
                                        >
                                            Refusée
                                        </span>

                                    <?php elseif (
                                        $decision
                                        === "En attente"
                                    ): ?>

                                        <span
                                            class="badge badge-orange"
                                        >
                                            En attente
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="badge badge-gray"
                                        >
                                            Non définie
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- MOTIF -->

                                <td>

                                    <?php if (
                                        !empty(
                                            $candidature["motif"]
                                        )
                                    ): ?>

                                        <div class="motif">

                                            <?= nl2br(
                                                htmlspecialchars(
                                                    $candidature[
                                                        "motif"
                                                    ]
                                                )
                                            ) ?>

                                        </div>

                                    <?php else: ?>

                                        <span
                                            style="
                                                color:#9aa4ad;
                                                font-size:11px;
                                            "
                                        >
                                            Aucun motif
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- FORMULAIRE -->

                                <td>

                                    <form
                                        method="POST"
                                        action="decision.php"
                                        class="decision-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="id_candidature"
                                            value="<?= htmlspecialchars(
                                                $candidature[
                                                    "id_candidature"
                                                ]
                                            ) ?>"
                                        >


                                        <label
                                            for="decision_<?= htmlspecialchars(
                                                $candidature[
                                                    "id_candidature"
                                                ]
                                            ) ?>"
                                        >

                                            Décision finale

                                        </label>


                                        <select
                                            name="decision_finale"
                                            id="decision_<?= htmlspecialchars(
                                                $candidature[
                                                    "id_candidature"
                                                ]
                                            ) ?>"
                                            required
                                        >

                                            <option
                                                value=""
                                                <?= empty($decision)
                                                    ? "selected"
                                                    : "" ?>
                                            >
                                                -- Sélectionner --
                                            </option>


                                            <option
                                                value="Acceptée"
                                                <?= $decision === "Acceptée"
                                                    ? "selected"
                                                    : "" ?>
                                            >
                                                Acceptée
                                            </option>


                                            <option
                                                value="Refusée"
                                                <?= $decision === "Refusée"
                                                    ? "selected"
                                                    : "" ?>
                                            >
                                                Refusée
                                            </option>


                                            <option
                                                value="En attente"
                                                <?= $decision === "En attente"
                                                    ? "selected"
                                                    : "" ?>
                                            >
                                                En attente
                                            </option>

                                        </select>


                                        <label>
                                            Motif
                                        </label>


                                        <textarea
                                            name="motif"
                                            placeholder="Saisir le motif de la décision..."
                                            required
                                        ><?= htmlspecialchars(
                                            $candidature["motif"] ?? ""
                                        ) ?></textarea>


                                        <button
                                            type="submit"
                                        >

                                            <?php if (
                                                !empty(
                                                    $candidature[
                                                        "id_decision"
                                                    ]
                                                )
                                            ): ?>

                                                Modifier la décision

                                            <?php else: ?>

                                                Enregistrer la décision

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

        </section>

    </main>

</div>


<!-- =====================================================
     FOOTER
     ===================================================== -->

<footer class="footer">

    © 2026 Direction Générale de la Lutte contre la Pauvreté

</footer>

</body>

</html>