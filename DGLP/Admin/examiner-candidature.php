<?php

session_start();

require_once "../../database.php";

/* =========================================================
   1. Vérifier que l'administrateur est connecté
   ========================================================= */

if (!isset($_SESSION["id_admin"])) {
    header("Location: connexion.php");
    exit;
}

/* =========================================================
   2. Vérifier l'identifiant de la candidature
   ========================================================= */

if (!isset($_GET["id"]) || !ctype_digit($_GET["id"])) {
    header("Location: candidatures.php");
    exit;
}

$id_candidature = (int) $_GET["id"];

/* =========================================================
   3. Récupérer les informations de la candidature
   ========================================================= */

$requete = $pdo->prepare("
    SELECT
        c.id_candidature,
        c.numero_candidature,
        c.date_candidature,
        c.statut,

        ca.id_candidat,
        ca.nom,
        ca.prenom,
        ca.date_naissance,
        ca.province,
        ca.ville,
        ca.telephone,

        co.email,

        o.id_offre,
        o.nom AS nom_offre,
        o.description AS description_offre,
        o.date_creation,
        o.date_limite_candidature,
        o.nombre_place,
        o.critere,
        o.montant,
        o.document_supplementaire,
        o.statut AS statut_offre

    FROM candidature c

    INNER JOIN candidat ca
        ON c.id_candidat = ca.id_candidat

    LEFT JOIN compte co
        ON ca.id_candidat = co.id_candidat

    INNER JOIN offre o
        ON c.id_offre = o.id_offre

    WHERE c.id_candidature = ?
");

$requete->execute([$id_candidature]);

$candidature = $requete->fetch(PDO::FETCH_ASSOC);

if (!$candidature) {
    header("Location: candidatures.php");
    exit;
}

/* =========================================================
   4. Récupérer le dossier du candidat
   ========================================================= */

$requete = $pdo->prepare("
    SELECT
        id_dossier,
        nip,
        carte_photo,
        acte_naissance,
        statut,
        situation_matrimoniale,
        personn_a_charge,
        situation_profesionnelle,
        activite,
        type_activite,
        description_activite
    FROM dossier
    WHERE id_candidat = ?
    LIMIT 1
");

$requete->execute([
    $candidature["id_candidat"]
]);

$dossier = $requete->fetch(PDO::FETCH_ASSOC);

/* =========================================================
   5. Récupérer les pièces complémentaires
   ========================================================= */

$requete = $pdo->prepare("
    SELECT
        pc.id_piece_candidature,
        pc.id_piece,
        pc.fichier,
        pc.date_depot,
        p.nom_piece,
        p.obligatoire
    FROM piece_candidature pc

    INNER JOIN piece_complementaire p
        ON pc.id_piece = p.id_piece

    WHERE pc.id_candidature = ?

    ORDER BY pc.id_piece_candidature ASC
");

$requete->execute([$id_candidature]);

$pieces_complementaires = $requete->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   6. Récupérer les critères de l'offre
   ========================================================= */

$requete = $pdo->prepare("
    SELECT
        id_critere,
        nom,
        description,
        valeur_min,
        valeur_max,
        valeur_attendue,
        poids,
        obligatoire
    FROM critere
    WHERE id_offre = ?
    ORDER BY id_critere ASC
");

$requete->execute([
    $candidature["id_offre"]
]);

$criteria = $requete->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   7. Récupérer le contrôle d'éligibilité
   ========================================================= */

$controle = null;

$requete = $pdo->prepare("
    SELECT
        id_controle,
        score,
        statut,
        justification,
        date_controle
    FROM controle_eligibilite
    WHERE id_candidature = ?
    ORDER BY id_controle DESC
    LIMIT 1
");

$requete->execute([$id_candidature]);

$controle = $requete->fetch(PDO::FETCH_ASSOC);

/* =========================================================
   8. Récupérer la décision existante
   ========================================================= */

$decision = null;

if ($controle) {

    $requete = $pdo->prepare("
        SELECT
            d.id_decision,
            d.decision_finale,
            d.motif,
            d.date_decision,
            a.nom AS nom_admin,
            a.prenom AS prenom_admin
        FROM decision d

        LEFT JOIN admin a
            ON d.id_admin = a.id_admin

        WHERE d.id_controle = ?

        LIMIT 1
    ");

    $requete->execute([
        $controle["id_controle"]
    ]);

    $decision = $requete->fetch(PDO::FETCH_ASSOC);
}

/* =========================================================
   9. Vérifier le classement existant
   ========================================================= */

$classement = null;

$requete = $pdo->prepare("
    SELECT
        id_classement,
        rang,
        score,
        statut,
        justification,
        date_classement
    FROM classement
    WHERE id_candidature = ?
    LIMIT 1
");

$requete->execute([$id_candidature]);

$classement = $requete->fetch(PDO::FETCH_ASSOC);

/* =========================================================
   10. Fonction d'affichage sécurisé
   ========================================================= */

function afficher($valeur)
{
    if ($valeur === null || $valeur === "") {
        return "Non renseigné";
    }

    return htmlspecialchars(
        (string) $valeur,
        ENT_QUOTES,
        "UTF-8"
    );
}

/* =========================================================
   11. Vérifier les fichiers
   ========================================================= */

$chemin_uploads = "../../uploads/dossiers/";

function fichierExiste($nomFichier, $chemin)
{
    if (empty($nomFichier)) {
        return false;
    }

    $nomFichier = basename($nomFichier);

    return file_exists($chemin . $nomFichier);
}

/* =========================================================
   12. Initiales du candidat
   ========================================================= */

$initiales = "";

if (!empty($candidature["prenom"])) {
    $initiales .= strtoupper(
        mb_substr($candidature["prenom"], 0, 1)
    );
}

if (!empty($candidature["nom"])) {
    $initiales .= strtoupper(
        mb_substr($candidature["nom"], 0, 1)
    );
}

if ($initiales === "") {
    $initiales = "CA";
}

/* =========================================================
   13. Nom administrateur
   ========================================================= */

$nomAdmin = trim(
    ($_SESSION["prenom_admin"] ?? "") .
    " " .
    ($_SESSION["nom_admin"] ?? "")
);

if ($nomAdmin === "") {
    $nomAdmin = "Administrateur";
}

/* =========================================================
   14. Classe de statut
   ========================================================= */

function classeStatut($statut)
{
    $statut = strtolower(trim((string) $statut));

    if (
        $statut === "éligible" ||
        $statut === "eligible" ||
        $statut === "soumise"
    ) {
        return "status-success";
    }

    if (
        $statut === "non éligible" ||
        $statut === "non eligible"
    ) {
        return "status-danger";
    }

    if (
        $statut === "en attente" ||
        $statut === "à examiner" ||
        $statut === "a examiner" ||
        $statut === "à compléter" ||
        $statut === "a compléter"
    ) {
        return "status-warning";
    }

    return "status-neutral";
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
        Examiner candidature - DGLP
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
                url("../images/dglp.jpg") center center / 420px auto no-repeat fixed;
            background-color: #f4f6f8;
            color: #263746;
            line-height: 1.6;
        }

        a {
            color: inherit;
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
            z-index: 1000;
            box-shadow: 4px 0 18px rgba(0, 0, 0, 0.12);
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 25px 20px 22px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.12);
        }

        .sidebar-logo {
            width: 88px;
            height: 88px;
            object-fit: cover;
            border-radius: 50%;
            background: white;
            padding: 4px;
            margin-bottom: 12px;
        }

        .sidebar-title {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .sidebar-subtitle {
            margin-top: 3px;
            font-size: 12px;
            color: rgba(255,255,255,0.75);
        }

        .sidebar-menu {
            padding: 18px 12px;
        }

        .menu-label {
            padding: 10px 14px;
            color: rgba(255,255,255,0.48);
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 12px 14px;
            margin-bottom: 5px;
            border-radius: 9px;
            color: rgba(255,255,255,0.88);
            font-size: 14px;
            transition: 0.2s ease;
        }

        .menu-link:hover {
            background: rgba(255,255,255,0.10);
            color: white;
        }

        .menu-link.active {
            background: white;
            color: #123b68;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(0,0,0,0.10);
        }

        .menu-icon {
            width: 22px;
            text-align: center;
            font-size: 16px;
        }

        .sidebar-bottom {
            position: absolute;
            bottom: 18px;
            left: 12px;
            right: 12px;
        }

        .logout-link {
            color: #ffd7d7;
        }

        .logout-link:hover {
            background: rgba(220, 53, 69, 0.15);
            color: white;
        }

        /* =====================================================
           MAIN
        ===================================================== */

        .main {
            margin-left: 255px;
            min-height: 100vh;
        }

        /* =====================================================
           TOP HEADER
        ===================================================== */

        .topbar {
            height: 78px;
            background: rgba(255,255,255,0.96);
            border-bottom: 1px solid #e3e8ed;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            position: sticky;
            top: 0;
            z-index: 900;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        .ministry {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .ministry-logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
            border-radius: 6px;
        }

        .ministry-name {
            color: #123b68;
            font-size: 14px;
            font-weight: bold;
        }

        .ministry-subtitle {
            color: #71808f;
            font-size: 12px;
            margin-top: 2px;
        }

        .admin-area {
            display: flex;
            align-items: center;
            gap: 11px;
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
            font-size: 13px;
            font-weight: bold;
        }

        .admin-name {
            color: #263746;
            font-size: 13px;
            font-weight: bold;
        }

        .admin-role {
            color: #7b8792;
            font-size: 11px;
        }

        /* =====================================================
           CONTENT
        ===================================================== */

        .content {
            padding: 30px;
            max-width: 1450px;
            margin: auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-title {
            color: #123b68;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .page-description {
            color: #71808f;
            font-size: 14px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 16px;
            background: white;
            border: 1px solid #dce3e9;
            border-radius: 8px;
            color: #123b68;
            font-size: 13px;
            font-weight: bold;
            transition: 0.2s;
        }

        .back-button:hover {
            background: #f3f7fb;
            border-color: #123b68;
        }

        /* =====================================================
           CANDIDATE BANNER
        ===================================================== */

        .candidate-banner {
            background: linear-gradient(
                135deg,
                #123b68,
                #0d2d50
            );
            color: white;
            border-radius: 14px;
            padding: 25px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            box-shadow: 0 7px 22px rgba(18,59,104,0.16);
        }

        .candidate-main {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .candidate-avatar {
            width: 70px;
            height: 70px;
            flex-shrink: 0;
            border-radius: 50%;
            background: white;
            color: #123b68;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 800;
        }

        .candidate-name {
            font-size: 21px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .candidate-number {
            color: rgba(255,255,255,0.72);
            font-size: 13px;
        }

        .candidate-status {
            text-align: right;
        }

        .candidate-status-label {
            color: rgba(255,255,255,0.65);
            font-size: 11px;
            margin-bottom: 6px;
        }

        .banner-status {
            display: inline-block;
            padding: 7px 13px;
            background: white;
            color: #123b68;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        /* =====================================================
           CARDS
        ===================================================== */

        .card {
            background: white;
            border: 1px solid #e3e8ed;
            border-radius: 13px;
            margin-bottom: 22px;
            overflow: hidden;
            box-shadow: 0 3px 12px rgba(20,40,60,0.05);
        }

        .card-header {
            padding: 18px 22px;
            border-bottom: 1px solid #e8edf1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .card-title {
            color: #123b68;
            font-size: 17px;
            font-weight: 800;
        }

        .card-title-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: #edf4fa;
            color: #123b68;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .card-body {
            padding: 22px;
        }

        /* =====================================================
           INFORMATION GRID
        ===================================================== */

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 13px;
        }

        .info-box {
            background: #f7f9fb;
            border: 1px solid #e8edf1;
            border-radius: 9px;
            padding: 14px;
        }

        .info-label {
            display: block;
            color: #7a8792;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 5px;
        }

        .info-value {
            color: #263746;
            font-size: 14px;
            font-weight: 600;
            word-break: break-word;
        }

        /* =====================================================
           STATUS
        ===================================================== */

        .status {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .status-success {
            background: #e4f5ec;
            color: #08743d;
        }

        .status-danger {
            background: #fde8ea;
            color: #b42331;
        }

        .status-warning {
            background: #fff3d6;
            color: #896500;
        }

        .status-neutral {
            background: #edf0f2;
            color: #586570;
        }

        /* =====================================================
           DESCRIPTION
        ===================================================== */

        .description-box {
            margin-top: 17px;
            padding: 16px;
            background: #f7f9fb;
            border: 1px solid #e8edf1;
            border-radius: 9px;
        }

        .description-title {
            color: #687682;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .description-text {
            color: #354553;
            font-size: 14px;
        }

        /* =====================================================
           DOCUMENTS
        ===================================================== */

        .documents-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .document {
            border: 1px solid #e2e8ed;
            border-radius: 10px;
            padding: 17px;
            background: #fff;
            transition: 0.2s;
        }

        .document:hover {
            border-color: #bfd0df;
            box-shadow: 0 4px 12px rgba(18,59,104,0.06);
        }

        .document-title {
            color: #263746;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .document-meta {
            color: #7a8792;
            font-size: 12px;
            margin-top: 10px;
        }

        .document-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
            padding: 8px 11px;
            background: #edf4fa;
            color: #123b68;
            border-radius: 7px;
            font-size: 12px;
            font-weight: bold;
        }

        .document-link:hover {
            background: #dceaf5;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 5px;
            vertical-align: middle;
        }

        .obligatoire {
            background: #fde8ea;
            color: #b42331;
        }

        .facultatif {
            background: #edf0f2;
            color: #586570;
        }

        /* =====================================================
           CRITERES
        ===================================================== */

        .criteria-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .criteria-item {
            border: 1px solid #e3e8ed;
            border-radius: 10px;
            padding: 17px;
            background: #fff;
        }

        .criteria-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .criteria-name {
            color: #123b68;
            font-size: 14px;
            font-weight: 800;
        }

        .criteria-weight {
            background: #edf4fa;
            color: #123b68;
            padding: 5px 9px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .criteria-description {
            margin-top: 9px;
            color: #697784;
            font-size: 13px;
        }

        .criteria-values {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .criteria-value {
            padding: 5px 9px;
            background: #f4f6f8;
            border-radius: 6px;
            color: #53616d;
            font-size: 11px;
        }

        /* =====================================================
           RESULTAT
        ===================================================== */

        .result-box {
            background: #f5f9fc;
            border: 1px solid #dce8f1;
            border-radius: 11px;
            padding: 20px;
        }

        .result-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .result-item {
            padding: 15px;
            background: white;
            border: 1px solid #e1e8ee;
            border-radius: 9px;
        }

        .result-label {
            display: block;
            color: #7a8792;
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .score {
            color: #123b68;
            font-size: 26px;
            font-weight: 800;
        }

        .justification {
            margin-top: 16px;
            padding: 14px;
            background: white;
            border: 1px solid #e1e8ee;
            border-radius: 9px;
        }

        .justification-title {
            display: block;
            color: #697784;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        /* =====================================================
           CLASSEMENT
        ===================================================== */

        .rank-box {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 13px;
        }

        .rank-item {
            padding: 16px;
            border-radius: 9px;
            background: #f7f9fb;
            border: 1px solid #e5eaee;
        }

        .rank-label {
            display: block;
            color: #7a8792;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .rank-value {
            color: #263746;
            font-size: 16px;
            font-weight: 800;
        }

        /* =====================================================
           ACTIONS
        ===================================================== */

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 42px;
            padding: 10px 17px;
            border-radius: 8px;
            border: none;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.94;
        }

        .btn-primary {
            background: #123b68;
            color: white;
        }

        .btn-success {
            background: #008c4a;
            color: white;
        }

        .btn-secondary {
            background: #edf0f2;
            color: #44515c;
        }

        .btn-secondary:hover {
            background: #e1e6ea;
        }

        /* =====================================================
           EMPTY
        ===================================================== */

        .empty {
            padding: 18px;
            text-align: center;
            color: #7c8892;
            background: #f8f9fa;
            border: 1px dashed #d7dfe5;
            border-radius: 9px;
            font-size: 13px;
        }

        /* =====================================================
           FOOTER
        ===================================================== */

        .footer {
            margin-top: 30px;
            padding: 20px 30px;
            background: #123b68;
            color: rgba(255,255,255,0.8);
            text-align: center;
            font-size: 12px;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1100px) {

            .sidebar {
                width: 75px;
            }

            .sidebar-header {
                padding: 18px 8px;
            }

            .sidebar-logo {
                width: 48px;
                height: 48px;
            }

            .sidebar-title,
            .sidebar-subtitle,
            .menu-label,
            .menu-text,
            .sidebar-bottom .menu-text {
                display: none;
            }

            .menu-link {
                justify-content: center;
                padding: 13px 8px;
            }

            .main {
                margin-left: 75px;
            }

            .sidebar-bottom {
                left: 8px;
                right: 8px;
            }

        }

        @media (max-width: 800px) {

            .content {
                padding: 20px;
            }

            .topbar {
                padding: 0 20px;
            }

            .admin-name,
            .admin-role {
                display: none;
            }

            .page-header {
                flex-direction: column;
            }

            .info-grid,
            .documents-grid,
            .result-grid,
            .rank-box {
                grid-template-columns: 1fr;
            }

            .candidate-banner {
                flex-direction: column;
                align-items: flex-start;
            }

            .candidate-status {
                text-align: left;
            }

        }

        @media (max-width: 550px) {

            .main {
                margin-left: 0;
            }

            .sidebar {
                position: fixed;
                width: 100%;
                height: 62px;
                bottom: 0;
                top: auto;
                display: flex;
                align-items: center;
                overflow: visible;
            }

            .sidebar-header {
                display: none;
            }

            .sidebar-menu {
                width: 100%;
                padding: 5px;
                display: flex;
                justify-content: space-around;
            }

            .menu-link {
                margin: 0;
                width: 43px;
                height: 43px;
                padding: 0;
            }

            .menu-label,
            .sidebar-bottom {
                display: none;
            }

            .topbar {
                height: 70px;
            }

            .ministry-name {
                font-size: 11px;
            }

            .ministry-subtitle {
                font-size: 10px;
            }

            .ministry-logo {
                width: 40px;
                height: 40px;
            }

            .content {
                padding: 16px;
                padding-bottom: 85px;
            }

            .page-title {
                font-size: 23px;
            }

            .candidate-avatar {
                width: 58px;
                height: 58px;
            }

            .candidate-name {
                font-size: 18px;
            }

            .card-header,
            .card-body {
                padding: 16px;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .footer {
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

        <div class="sidebar-title">
            DGLP
        </div>

        <div class="sidebar-subtitle">
            Administration
        </div>

    </div>

    <nav class="sidebar-menu">

        <div class="menu-label">
            Navigation
        </div>

        <a
            href="dashboard.php"
            class="menu-link"
        >
            <span class="menu-icon">⌂</span>
            <span class="menu-text">Tableau de bord</span>
        </a>

        <a
            href="offres.php"
            class="menu-link"
        >
            <span class="menu-icon">▣</span>
            <span class="menu-text">Offres</span>
        </a>

        <a
            href="candidatures.php"
            class="menu-link active"
        >
            <span class="menu-icon">☷</span>
            <span class="menu-text">Candidatures</span>
        </a>

        <a
            href="classement.php"
            class="menu-link"
        >
            <span class="menu-icon">★</span>
            <span class="menu-text">Classement</span>
        </a>

        <a
            href="historique.php"
            class="menu-link"
        >
            <span class="menu-icon">◷</span>
            <span class="menu-text">Historique</span>
        </a>

    </nav>

    <div class="sidebar-bottom">

        <a
            href="deconnexion.php"
            class="menu-link logout-link"
        >
            <span class="menu-icon">↪</span>
            <span class="menu-text">Déconnexion</span>
        </a>

    </div>

</aside>

<!-- =========================================================
     MAIN
========================================================= -->

<div class="main">

    <!-- =====================================================
         TOPBAR
    ===================================================== -->

    <header class="topbar">

        <div class="ministry">

            <img
                src="../images/ministere.jpg"
                alt="Ministère"
                class="ministry-logo"
            >

            <div>

                <div class="ministry-name">
                    Ministère du Commerce, des PME/PMI et de l'Entrepreneuriat
                </div>

                <div class="ministry-subtitle">
                    Direction Générale de la Lutte contre la Pauvreté
                </div>

            </div>

        </div>

        <div class="admin-area">

            <div class="admin-avatar">

                <?= afficher(
                    strtoupper(
                        mb_substr(
                            $_SESSION["prenom_admin"] ?? "A",
                            0,
                            1
                        )
                    ) .
                    strtoupper(
                        mb_substr(
                            $_SESSION["nom_admin"] ?? "D",
                            0,
                            1
                        )
                    )
                ) ?>

            </div>

            <div>

                <div class="admin-name">
                    <?= afficher($nomAdmin) ?>
                </div>

                <div class="admin-role">
                    <?= afficher(
                        $_SESSION["role_admin"] ?? "Administrateur"
                    ) ?>
                </div>

            </div>

        </div>

    </header>

    <!-- =====================================================
         CONTENT
    ===================================================== -->

    <main class="content">

        <div class="page-header">

            <div>

                <h1 class="page-title">
                    Examiner une candidature
                </h1>

                <p class="page-description">
                    Consultez les informations du candidat,
                    les documents, les critères et les résultats
                    de traitement de la candidature.
                </p>

            </div>

            <a
                href="candidatures.php"
                class="back-button"
            >
                ← Retour aux candidatures
            </a>

        </div>

        <!-- =================================================
             CANDIDAT
        ================================================== -->

        <div class="candidate-banner">

            <div class="candidate-main">

                <div class="candidate-avatar">
                    <?= afficher($initiales) ?>
                </div>

                <div>

                    <div class="candidate-name">

                        <?= afficher(
                            $candidature["prenom"]
                        ) ?>

                        <?= afficher(
                            $candidature["nom"]
                        ) ?>

                    </div>

                    <div class="candidate-number">

                        Candidature :
                        <?= afficher(
                            $candidature["numero_candidature"]
                        ) ?>

                    </div>

                </div>

            </div>

            <div class="candidate-status">

                <div class="candidate-status-label">
                    Statut actuel
                </div>

                <span class="banner-status">

                    <?= afficher(
                        $candidature["statut"]
                    ) ?>

                </span>

            </div>

        </div>

        <!-- =================================================
             INFORMATIONS CANDIDATURE
        ================================================== -->

        <section class="card">

            <div class="card-header">

                <div class="card-title-wrap">

                    <div class="card-icon">
                        ☷
                    </div>

                    <h2 class="card-title">
                        Informations de la candidature
                    </h2>

                </div>

            </div>

            <div class="card-body">

                <div class="info-grid">

                    <div class="info-box">

                        <span class="info-label">
                            Numéro de candidature
                        </span>

                        <div class="info-value">
                            <?= afficher(
                                $candidature["numero_candidature"]
                            ) ?>
                        </div>

                    </div>

                    <div class="info-box">

                        <span class="info-label">
                            Date de candidature
                        </span>

                        <div class="info-value">
                            <?= afficher(
                                $candidature["date_candidature"]
                            ) ?>
                        </div>

                    </div>

                    <div class="info-box">

                        <span class="info-label">
                            Statut
                        </span>

                        <div class="info-value">

                            <span class="status <?= classeStatut(
                                $candidature["statut"]
                            ) ?>">

                                <?= afficher(
                                    $candidature["statut"]
                                ) ?>

                            </span>

                        </div>

                    </div>

                    <div class="info-box">

                        <span class="info-label">
                            Offre
                        </span>

                        <div class="info-value">
                            <?= afficher(
                                $candidature["nom_offre"]
                            ) ?>
                        </div>

                    </div>

                </div>

            </div>

        </section>

        <!-- =================================================
             INFORMATIONS CANDIDAT
        ================================================== -->

        <section class="card">

            <div class="card-header">

                <div class="card-title-wrap">

                    <div class="card-icon">
                        ♙
                    </div>

                    <h2 class="card-title">
                        Informations du candidat
                    </h2>

                </div>

            </div>

            <div class="card-body">

                <div class="info-grid">

                    <div class="info-box">

                        <span class="info-label">
                            Nom
                        </span>

                        <div class="info-value">
                            <?= afficher(
                                $candidature["nom"]
                            ) ?>
                        </div>

                    </div>

                    <div class="info-box">

                        <span class="info-label">
                            Prénom
                        </span>

                        <div class="info-value">
                            <?= afficher(
                                $candidature["prenom"]
                            ) ?>
                        </div>

                    </div>

                    <div class="info-box">

                        <span class="info-label">
                            Date de naissance
                        </span>

                        <div class="info-value">
                            <?= afficher(
                                $candidature["date_naissance"]
                            ) ?>
                        </div>

                    </div>

                    <div class="info-box">

                        <span class="info-label">
                            Email
                        </span>

                        <div class="info-value">
                            <?= afficher(
                                $candidature["email"]
                            ) ?>
                        </div>

                    </div>

                    <div class="info-box">

                        <span class="info-label">
                            Province
                        </span>

                        <div class="info-value">
                            <?= afficher(
                                $candidature["province"]
                            ) ?>
                        </div>

                    </div>

                    <div class="info-box">

                        <span class="info-label">
                            Ville
                        </span>

                        <div class="info-value">
                            <?= afficher(
                                $candidature["ville"]
                            ) ?>
                        </div>

                    </div>

                    <div class="info-box">

                        <span class="info-label">
                            Téléphone
                        </span>

                        <div class="info-value">
                            <?= afficher(
                                $candidature["telephone"]
                            ) ?>
                        </div>

                    </div>

                </div>

            </div>

        </section>

        <!-- =================================================
             DOSSIER
        ================================================== -->

        <section class="card">

            <div class="card-header">

                <div class="card-title-wrap">

                    <div class="card-icon">
                        ▤
                    </div>

                    <h2 class="card-title">
                        Informations du dossier
                    </h2>

                </div>

                <?php if ($dossier): ?>

                    <span class="status <?= classeStatut(
                        $dossier["statut"]
                    ) ?>">

                        <?= afficher(
                            $dossier["statut"]
                        ) ?>

                    </span>

                <?php endif; ?>

            </div>

            <div class="card-body">

                <?php if (!$dossier): ?>

                    <div class="empty">
                        Aucun dossier n'est enregistré pour ce candidat.
                    </div>

                <?php else: ?>

                    <div class="info-grid">

                        <div class="info-box">

                            <span class="info-label">
                                NIP
                            </span>

                            <div class="info-value">
                                <?= afficher(
                                    $dossier["nip"]
                                ) ?>
                            </div>

                        </div>

                        <div class="info-box">

                            <span class="info-label">
                                Situation matrimoniale
                            </span>

                            <div class="info-value">
                                <?= afficher(
                                    $dossier["situation_matrimoniale"]
                                ) ?>
                            </div>

                        </div>

                        <div class="info-box">

                            <span class="info-label">
                                Personnes à charge
                            </span>

                            <div class="info-value">
                                <?= afficher(
                                    $dossier["personn_a_charge"]
                                ) ?>
                            </div>

                        </div>

                        <div class="info-box">

                            <span class="info-label">
                                Situation professionnelle
                            </span>

                            <div class="info-value">
                                <?= afficher(
                                    $dossier["situation_profesionnelle"]
                                ) ?>
                            </div>

                        </div>

                        <div class="info-box">

                            <span class="info-label">
                                Activité
                            </span>

                            <div class="info-value">
                                <?= afficher(
                                    $dossier["activite"]
                                ) ?>
                            </div>

                        </div>

                        <div class="info-box">

                            <span class="info-label">
                                Type d'activité
                            </span>

                            <div class="info-value">
                                <?= afficher(
                                    $dossier["type_activite"]
                                ) ?>
                            </div>

                        </div>

                    </div>

                    <div class="description-box">

                        <div class="description-title">
                            DESCRIPTION DE L'ACTIVITÉ
                        </div>

                        <div class="description-text">

                            <?= nl2br(
                                afficher(
                                    $dossier["description_activite"]
                                )
                            ) ?>

                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </section>

        <!-- =================================================
             PIECES DOSSIER
        ================================================== -->

        <section class="card">

            <div class="card-header">

                <div class="card-title-wrap">

                    <div class="card-icon">
                        ▧
                    </div>

                    <h2 class="card-title">
                        Pièces du dossier
                    </h2>

                </div>

            </div>

            <div class="card-body">

                <?php if (!$dossier): ?>

                    <div class="empty">
                        Aucun document disponible.
                    </div>

                <?php else: ?>

                    <div class="documents-grid">

                        <!-- PHOTO -->

                        <div class="document">

                            <div class="document-title">
                                Carte / Photo d'identité
                            </div>

                            <?php if (
                                fichierExiste(
                                    $dossier["carte_photo"],
                                    $chemin_uploads
                                )
                            ): ?>

                                <a
                                    href="<?= htmlspecialchars(
                                        $chemin_uploads .
                                        rawurlencode(
                                            basename(
                                                $dossier["carte_photo"]
                                            )
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                    target="_blank"
                                    class="document-link"
                                >
                                    ↗ Consulter le document
                                </a>

                            <?php else: ?>

                                <span class="empty">
                                    Document non disponible
                                </span>

                            <?php endif; ?>

                        </div>

                        <!-- ACTE NAISSANCE -->

                        <div class="document">

                            <div class="document-title">
                                Acte de naissance
                            </div>

                            <?php if (
                                fichierExiste(
                                    $dossier["acte_naissance"],
                                    $chemin_uploads
                                )
                            ): ?>

                                <a
                                    href="<?= htmlspecialchars(
                                        $chemin_uploads .
                                        rawurlencode(
                                            basename(
                                                $dossier["acte_naissance"]
                                            )
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                    target="_blank"
                                    class="document-link"
                                >
                                    ↗ Consulter le document
                                </a>

                            <?php else: ?>

                                <span class="empty">
                                    Document non disponible
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </section>

        <!-- =================================================
             PIECES COMPLEMENTAIRES
        ================================================== -->

        <section class="card">

            <div class="card-header">

                <div class="card-title-wrap">

                    <div class="card-icon">
                        ▱
                    </div>

                    <h2 class="card-title">
                        Pièces complémentaires
                    </h2>

                </div>

            </div>

            <div class="card-body">

                <?php if (
                    empty($pieces_complementaires)
                ): ?>

                    <div class="empty">
                        Aucune pièce complémentaire déposée.
                    </div>

                <?php else: ?>

                    <div class="documents-grid">

                        <?php foreach (
                            $pieces_complementaires
                            as $piece
                        ): ?>

                            <div class="document">

                                <div class="document-title">

                                    <?= afficher(
                                        $piece["nom_piece"]
                                    ) ?>

                                    <?php if (
                                        (int) $piece["obligatoire"] === 1
                                    ): ?>

                                        <span class="badge obligatoire">
                                            Obligatoire
                                        </span>

                                    <?php else: ?>

                                        <span class="badge facultatif">
                                            Facultatif
                                        </span>

                                    <?php endif; ?>

                                </div>

                                <?php if (
                                    !empty($piece["fichier"])
                                ): ?>

                                    <a
                                        href="<?= htmlspecialchars(
                                            "../../uploads/dossiers/" .
                                            rawurlencode(
                                                basename(
                                                    $piece["fichier"]
                                                )
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                        target="_blank"
                                        class="document-link"
                                    >
                                        ↗ Consulter la pièce
                                    </a>

                                <?php else: ?>

                                    <span class="empty">
                                        Aucun fichier déposé
                                    </span>

                                <?php endif; ?>

                                <?php if (
                                    !empty($piece["date_depot"])
                                ): ?>

                                    <div class="document-meta">

                                        Déposé le :
                                        <?= afficher(
                                            $piece["date_depot"]
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </section>

        <!-- =================================================
             OFFRE
        ================================================== -->

        <section class="card">

            <div class="card-header">

                <div class="card-title-wrap">

                    <div class="card-icon">
                        ▣
                    </div>

                    <h2 class="card-title">
                        Informations sur l'offre
                    </h2>

                </div>

                <span class="status <?= classeStatut(
                    $candidature["statut_offre"]
                ) ?>">

                    <?= afficher(
                        $candidature["statut_offre"]
                    ) ?>

                </span>

            </div>

            <div class="card-body">

                <div class="info-grid">

                    <div class="info-box">

                        <span class="info-label">
                            Nom de l'offre
                        </span>

                        <div class="info-value">
                            <?= afficher(
                                $candidature["nom_offre"]
                            ) ?>
                        </div>

                    </div>

                    <div class="info-box">

                        <span class="info-label">
                            Date de création
                        </span>

                        <div class="info-value">
                            <?= afficher(
                                $candidature["date_creation"]
                            ) ?>
                        </div>

                    </div>

                    <div class="info-box">

                        <span class="info-label">
                            Date limite
                        </span>

                        <div class="info-value">
                            <?= afficher(
                                $candidature["date_limite_candidature"]
                            ) ?>
                        </div>

                    </div>

                    <div class="info-box">

                        <span class="info-label">
                            Nombre de places
                        </span>

                        <div class="info-value">
                            <?= afficher(
                                $candidature["nombre_place"]
                            ) ?>
                        </div>

                    </div>

                    <div class="info-box">

                        <span class="info-label">
                            Montant
                        </span>

                        <div class="info-value">

                            <?php if (
                                $candidature["montant"] !== null
                            ): ?>

                                <?= number_format(
                                    (float) $candidature["montant"],
                                    2,
                                    ",",
                                    " "
                                ) ?>

                                FCFA

                            <?php else: ?>

                                Non renseigné

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

                <div class="description-box">

                    <div class="description-title">
                        DESCRIPTION
                    </div>

                    <div class="description-text">

                        <?= nl2br(
                            afficher(
                                $candidature["description_offre"]
                            )
                        ) ?>

                    </div>

                </div>

                <?php if (
                    !empty($candidature["critere"])
                ): ?>

                    <div class="description-box">

                        <div class="description-title">
                            CRITÈRES GÉNÉRAUX
                        </div>

                        <div class="description-text">

                            <?= nl2br(
                                afficher(
                                    $candidature["critere"]
                                )
                            ) ?>

                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </section>

        <!-- =================================================
             CRITERES
        ================================================== -->

        <section class="card">

            <div class="card-header">

                <div class="card-title-wrap">

                    <div class="card-icon">
                        ✓
                    </div>

                    <h2 class="card-title">
                        Critères d'éligibilité
                    </h2>

                </div>

            </div>

            <div class="card-body">

                <?php if (empty($criteria)): ?>

                    <div class="empty">
                        Aucun critère détaillé n'est enregistré
                        pour cette offre.
                    </div>

                <?php else: ?>

                    <div class="criteria-list">

                        <?php foreach (
                            $criteria
                            as $critere
                        ): ?>

                            <div class="criteria-item">

                                <div class="criteria-top">

                                    <div>

                                        <span class="criteria-name">

                                            <?= afficher(
                                                $critere["nom"]
                                            ) ?>

                                        </span>

                                        <?php if (
                                            (int) $critere["obligatoire"] === 1
                                        ): ?>

                                            <span class="badge obligatoire">
                                                Obligatoire
                                            </span>

                                        <?php else: ?>

                                            <span class="badge facultatif">
                                                Facultatif
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                    <span class="criteria-weight">

                                        Poids :
                                        <?= afficher(
                                            $critere["poids"]
                                        ) ?>

                                    </span>

                                </div>

                                <?php if (
                                    !empty($critere["description"])
                                ): ?>

                                    <div class="criteria-description">

                                        <?= nl2br(
                                            afficher(
                                                $critere["description"]
                                            )
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                                <div class="criteria-values">

                                    <?php if (
                                        $critere["valeur_min"] !== null &&
                                        $critere["valeur_min"] !== ""
                                    ): ?>

                                        <span class="criteria-value">
                                            Minimum :
                                            <?= afficher(
                                                $critere["valeur_min"]
                                            ) ?>
                                        </span>

                                    <?php endif; ?>

                                    <?php if (
                                        $critere["valeur_max"] !== null &&
                                        $critere["valeur_max"] !== ""
                                    ): ?>

                                        <span class="criteria-value">
                                            Maximum :
                                            <?= afficher(
                                                $critere["valeur_max"]
                                            ) ?>
                                        </span>

                                    <?php endif; ?>

                                    <?php if (
                                        $critere["valeur_attendue"] !== null &&
                                        $critere["valeur_attendue"] !== ""
                                    ): ?>

                                        <span class="criteria-value">
                                            Valeur attendue :
                                            <?= afficher(
                                                $critere["valeur_attendue"]
                                            ) ?>
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </section>

        <!-- =================================================
             CONTROLE ELIGIBILITE
        ================================================== -->

        <section class="card">

            <div class="card-header">

                <div class="card-title-wrap">

                    <div class="card-icon">
                        ✓
                    </div>

                    <h2 class="card-title">
                        Contrôle d'éligibilité
                    </h2>

                </div>

            </div>

            <div class="card-body">

                <?php if (!$controle): ?>

                    <div class="empty">
                        Aucun contrôle d'éligibilité n'a encore
                        été effectué.
                    </div>

                <?php else: ?>

                    <div class="result-box">

                        <div class="result-grid">

                            <div class="result-item">

                                <span class="result-label">
                                    Score
                                </span>

                                <span class="score">

                                    <?= number_format(
                                        (float) $controle["score"],
                                        2,
                                        ",",
                                        " "
                                    ) ?>

                                    <small>/ 100</small>

                                </span>

                            </div>

                            <div class="result-item">

                                <span class="result-label">
                                    Statut
                                </span>

                                <span class="status <?= classeStatut(
                                    $controle["statut"]
                                ) ?>">

                                    <?= afficher(
                                        $controle["statut"]
                                    ) ?>

                                </span>

                            </div>

                            <div class="result-item">

                                <span class="result-label">
                                    Date du contrôle
                                </span>

                                <strong>

                                    <?= afficher(
                                        $controle["date_controle"]
                                    ) ?>

                                </strong>

                            </div>

                        </div>

                        <?php if (
                            !empty($controle["justification"])
                        ): ?>

                            <div class="justification">

                                <span class="justification-title">
                                    Justification
                                </span>

                                <?= nl2br(
                                    afficher(
                                        $controle["justification"]
                                    )
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </div>

        </section>

        <!-- =================================================
             CLASSEMENT
        ================================================== -->

        <section class="card">

            <div class="card-header">

                <div class="card-title-wrap">

                    <div class="card-icon">
                        ★
                    </div>

                    <h2 class="card-title">
                        Classement
                    </h2>

                </div>

            </div>

            <div class="card-body">

                <?php if (!$classement): ?>

                    <div class="empty">
                        Cette candidature n'a pas encore été classée.
                    </div>

                <?php else: ?>

                    <div class="rank-box">

                        <div class="rank-item">

                            <span class="rank-label">
                                Rang
                            </span>

                            <span class="rank-value">
                                <?= afficher(
                                    $classement["rang"]
                                ) ?>
                            </span>

                        </div>

                        <div class="rank-item">

                            <span class="rank-label">
                                Score
                            </span>

                            <span class="rank-value">

                                <?= number_format(
                                    (float) $classement["score"],
                                    2,
                                    ",",
                                    " "
                                ) ?>

                                / 100

                            </span>

                        </div>

                        <div class="rank-item">

                            <span class="rank-label">
                                Statut
                            </span>

                            <span class="status <?= classeStatut(
                                $classement["statut"]
                            ) ?>">

                                <?= afficher(
                                    $classement["statut"]
                                ) ?>

                            </span>

                        </div>

                        <div class="rank-item">

                            <span class="rank-label">
                                Date du classement
                            </span>

                            <span class="rank-value">

                                <?= afficher(
                                    $classement["date_classement"]
                                ) ?>

                            </span>

                        </div>

                    </div>

                    <?php if (
                        !empty($classement["justification"])
                    ): ?>

                        <div class="justification">

                            <span class="justification-title">
                                Justification
                            </span>

                            <?= nl2br(
                                afficher(
                                    $classement["justification"]
                                )
                            ) ?>

                        </div>

                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </section>

        <!-- =================================================
             DECISION FINALE
        ================================================== -->

        <section class="card">

            <div class="card-header">

                <div class="card-title-wrap">

                    <div class="card-icon">
                        ◆
                    </div>

                    <h2 class="card-title">
                        Décision finale
                    </h2>

                </div>

            </div>

            <div class="card-body">

                <?php if (!$decision): ?>

                    <div class="empty">
                        Aucune décision finale n'a encore été prise.
                    </div>

                <?php else: ?>

                    <div class="result-box">

                        <div class="result-grid">

                            <div class="result-item">

                                <span class="result-label">
                                    Décision
                                </span>

                                <span class="status <?= classeStatut(
                                    $decision["decision_finale"]
                                ) ?>">

                                    <?= afficher(
                                        $decision["decision_finale"]
                                    ) ?>

                                </span>

                            </div>

                            <div class="result-item">

                                <span class="result-label">
                                    Date
                                </span>

                                <strong>

                                    <?= afficher(
                                        $decision["date_decision"]
                                    ) ?>

                                </strong>

                            </div>

                            <div class="result-item">

                                <span class="result-label">
                                    Administrateur
                                </span>

                                <strong>

                                    <?= afficher(
                                        trim(
                                            $decision["prenom_admin"] .
                                            " " .
                                            $decision["nom_admin"]
                                        )
                                    ) ?>

                                </strong>

                            </div>

                        </div>

                        <?php if (
                            !empty($decision["motif"])
                        ): ?>

                            <div class="justification">

                                <span class="justification-title">
                                    Motif
                                </span>

                                <?= nl2br(
                                    afficher(
                                        $decision["motif"]
                                    )
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </div>

        </section>

        <!-- =================================================
             ACTIONS
        ================================================== -->

        <section class="card">

            <div class="card-header">

                <div class="card-title-wrap">

                    <div class="card-icon">
                        ⚙
                    </div>

                    <h2 class="card-title">
                        Actions
                    </h2>

                </div>

            </div>

            <div class="card-body">

                <div class="actions">

                    <a
                        href="controle-eligibilite.php?id=<?= $id_candidature ?>"
                        class="btn btn-primary"
                    >
                        ✓ Contrôle d'éligibilité
                    </a>

                    <?php if (
                        $controle &&
                        $controle["statut"] === "Éligible"
                    ): ?>

                        <a
                            href="classement.php?id=<?= $id_candidature ?>"
                            class="btn btn-success"
                        >
                            ★ Classer la candidature
                        </a>

                    <?php endif; ?>

                    <a
                        href="candidatures.php"
                        class="btn btn-secondary"
                    >
                        ← Retour aux candidatures
                    </a>

                </div>

            </div>

        </section>

    </main>

    <!-- =====================================================
         FOOTER
    ===================================================== -->

    <footer class="footer">

        © 2026 Direction Générale de la Lutte contre la Pauvreté

    </footer>

</div>

</body>

</html>