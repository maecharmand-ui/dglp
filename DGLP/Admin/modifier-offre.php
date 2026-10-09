<?php

session_start();
require_once "../../database.php";

/* =========================================================
   VÉRIFICATION DE LA CONNEXION ADMIN
   ========================================================= */

if (!isset($_SESSION["id_admin"])) {
    header("Location: connexion.php");
    exit;
}


/* =========================================================
   VÉRIFICATION DE L'ID DE L'OFFRE
   ========================================================= */

if (!isset($_GET["id"]) || !ctype_digit($_GET["id"])) {
    header("Location: offres.php");
    exit;
}

$id_offre = (int) $_GET["id"];


/* =========================================================
   RÉCUPÉRATION DE L'OFFRE
   ========================================================= */

$requete = $pdo->prepare("
    SELECT
        id_offre,
        nom,
        description,
        date_creation,
        date_limite_candidature,
        nombre_place,
        montant,
        document_supplementaire,
        statut
    FROM offre
    WHERE id_offre = ?
");

$requete->execute([$id_offre]);

$offre = $requete->fetch(PDO::FETCH_ASSOC);

if (!$offre) {
    header("Location: offres.php");
    exit;
}


/* =========================================================
   RÉCUPÉRATION DES CRITÈRES
   ========================================================= */

$requeteCriteres = $pdo->prepare("
    SELECT
        id_critere,
        nom,
        description,
        valeur_min,
        valeur_max,
        valeur_attendue,
        obligatoire
    FROM critere
    WHERE id_offre = ?
    ORDER BY id_critere ASC
");

$requeteCriteres->execute([$id_offre]);

$criteres = $requeteCriteres->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   RÉCUPÉRATION DE LA RÈGLE D'ÉLIGIBILITÉ
   ========================================================= */

$requeteRegle = $pdo->prepare("
    SELECT type_regle
    FROM regle_eligibilite
    WHERE id_offre = ?
");

$requeteRegle->execute([$id_offre]);

$regle = $requeteRegle->fetchColumn();

if (!$regle) {
    $regle = "tous";
}


/* =========================================================
   VARIABLES
   ========================================================= */

$erreur = "";


/* =========================================================
   TRAITEMENT DU FORMULAIRE
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nom = trim($_POST["nom"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $date_limite = $_POST["date_limite_candidature"] ?? "";
    $nombre_place = $_POST["nombre_place"] ?? "";
    $montant = $_POST["montant"] ?? "";

    $document_supplementaire = isset(
        $_POST["document_supplementaire"]
    ) ? 1 : 0;

    $regle_eligibilite =
        $_POST["regle_eligibilite"] ?? "tous";

    $criteresPost = $_POST["critere"] ?? [];


    /* =====================================================
       VALIDATION DE BASE
       ===================================================== */

    if ($nom === "") {

        $erreur =
            "Le nom de l'offre est obligatoire.";

    } elseif (mb_strlen($nom) > 150) {

        $erreur =
            "Le nom de l'offre ne doit pas dépasser 150 caractères.";

    } elseif ($description === "") {

        $erreur =
            "La description de l'offre est obligatoire.";

    } elseif ($date_limite === "") {

        $erreur =
            "La date limite de candidature est obligatoire.";

    } elseif (
        !ctype_digit((string) $nombre_place)
        || (int) $nombre_place <= 0
    ) {

        $erreur =
            "Le nombre de places doit être un nombre supérieur à zéro.";

    } elseif (
        $montant !== ""
        && !is_numeric($montant)
    ) {

        $erreur =
            "Le montant doit être un nombre valide.";

    } elseif (
        !in_array(
            $regle_eligibilite,
            ["tous", "au_moins_un"],
            true
        )
    ) {

        $erreur =
            "La règle d'éligibilité sélectionnée est invalide.";
    }


    /* =====================================================
       VALIDATION DES CRITÈRES
       ===================================================== */

    if ($erreur === "" && !empty($criteresPost)) {

        foreach ($criteresPost as $index => $critere) {

            $nomCritere = trim(
                $critere["nom"] ?? ""
            );

            $valeur_min = trim(
                $critere["valeur_min"] ?? ""
            );

            $valeur_max = trim(
                $critere["valeur_max"] ?? ""
            );

            $valeur_attendue = trim(
                $critere["valeur_attendue"] ?? ""
            );


            if ($nomCritere === "") {

                $erreur =
                    "Le nom du critère numéro "
                    . ($index + 1)
                    . " est obligatoire.";

                break;
            }


            if (
                $valeur_min !== ""
                && !is_numeric($valeur_min)
            ) {

                $erreur =
                    "La valeur minimale du critère « "
                    . $nomCritere
                    . " » est invalide.";

                break;
            }


            if (
                $valeur_max !== ""
                && !is_numeric($valeur_max)
            ) {

                $erreur =
                    "La valeur maximale du critère « "
                    . $nomCritere
                    . " » est invalide.";

                break;
            }


            if (
                $valeur_min !== ""
                && $valeur_max !== ""
                && (float) $valeur_min > (float) $valeur_max
            ) {

                $erreur =
                    "Pour le critère « "
                    . $nomCritere
                    . " », la valeur minimale ne peut pas être supérieure à la valeur maximale.";

                break;
            }
        }
    }


    /* =====================================================
       ENREGISTREMENT
       ===================================================== */

    if ($erreur === "") {

        try {

            $pdo->beginTransaction();


            /* =================================================
               1. MODIFICATION DE L'OFFRE
               ================================================= */

            $requeteUpdate = $pdo->prepare("
                UPDATE offre
                SET
                    nom = ?,
                    description = ?,
                    date_limite_candidature = ?,
                    nombre_place = ?,
                    montant = ?,
                    document_supplementaire = ?
                WHERE id_offre = ?
            ");

            $requeteUpdate->execute([
                $nom,
                $description,
                $date_limite,
                (int) $nombre_place,
                $montant === "" ? null : $montant,
                $document_supplementaire,
                $id_offre
            ]);


            /* =================================================
               2. SUPPRESSION DES ANCIENS CRITÈRES
               ================================================= */

            $requeteDeleteCriteres = $pdo->prepare("
                DELETE FROM critere
                WHERE id_offre = ?
            ");

            $requeteDeleteCriteres->execute([
                $id_offre
            ]);


            /* =================================================
               3. AJOUT DES NOUVEAUX CRITÈRES
               ================================================= */

            if (!empty($criteresPost)) {

                $requeteInsertCritere = $pdo->prepare("
                    INSERT INTO critere
                    (
                        id_offre,
                        nom,
                        description,
                        valeur_min,
                        valeur_max,
                        valeur_attendue,
                        obligatoire
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");


                foreach ($criteresPost as $critere) {

                    $nomCritere = trim(
                        $critere["nom"] ?? ""
                    );

                    $descriptionCritere = trim(
                        $critere["description"] ?? ""
                    );

                    $valeur_min = trim(
                        $critere["valeur_min"] ?? ""
                    );

                    $valeur_max = trim(
                        $critere["valeur_max"] ?? ""
                    );

                    $valeur_attendue = trim(
                        $critere["valeur_attendue"] ?? ""
                    );

                    $obligatoire = isset(
                        $critere["obligatoire"]
                    ) ? 1 : 0;


                    if ($nomCritere === "") {
                        continue;
                    }


                    $requeteInsertCritere->execute([
                        $id_offre,
                        $nomCritere,
                        $descriptionCritere !== ""
                            ? $descriptionCritere
                            : null,
                        $valeur_min !== ""
                            ? $valeur_min
                            : null,
                        $valeur_max !== ""
                            ? $valeur_max
                            : null,
                        $valeur_attendue !== ""
                            ? $valeur_attendue
                            : null,
                        $obligatoire
                    ]);
                }
            }


            /* =================================================
               4. ENREGISTREMENT DE LA RÈGLE D'ÉLIGIBILITÉ
               ================================================= */

            $requeteExisteRegle = $pdo->prepare("
                SELECT id_regle
                FROM regle_eligibilite
                WHERE id_offre = ?
            ");

            $requeteExisteRegle->execute([
                $id_offre
            ]);

            $id_regle = $requeteExisteRegle->fetchColumn();


            if ($id_regle) {

                $requeteUpdateRegle = $pdo->prepare("
                    UPDATE regle_eligibilite
                    SET type_regle = ?
                    WHERE id_offre = ?
                ");

                $requeteUpdateRegle->execute([
                    $regle_eligibilite,
                    $id_offre
                ]);

            } else {

                $requeteInsertRegle = $pdo->prepare("
                    INSERT INTO regle_eligibilite
                    (
                        id_offre,
                        type_regle
                    )
                    VALUES (?, ?)
                ");

                $requeteInsertRegle->execute([
                    $id_offre,
                    $regle_eligibilite
                ]);
            }


            /* =================================================
               5. VALIDATION DE LA TRANSACTION
               ================================================= */

            $pdo->commit();


            header(
                "Location: offres.php?modification=success"
            );

            exit;

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erreur =
                "Erreur lors de la modification de l'offre : "
                . $e->getMessage();
        }
    }


    /* =====================================================
       CONSERVATION DES DONNÉES EN CAS D'ERREUR
       ===================================================== */

    if ($erreur !== "") {

        $offre["nom"] = $nom;
        $offre["description"] = $description;
        $offre["date_limite_candidature"] = $date_limite;
        $offre["nombre_place"] = $nombre_place;
        $offre["montant"] = $montant;
        $offre["document_supplementaire"] =
            $document_supplementaire;

        $criteres = $criteresPost;
        $regle = $regle_eligibilite;
    }
}


/* =========================================================
   INFORMATIONS ADMINISTRATEUR
   ========================================================= */

$prenomAdmin = $_SESSION["prenom_admin"] ?? "";
$nomAdmin = $_SESSION["nom_admin"] ?? "";

$initiales = "";

if ($prenomAdmin !== "") {
    $initiales .= strtoupper(
        substr($prenomAdmin, 0, 1)
    );
}

if ($nomAdmin !== "") {
    $initiales .= strtoupper(
        substr($nomAdmin, 0, 1)
    );
}

if ($initiales === "") {
    $initiales = "AD";
}


/* =========================================================
   FONCTION STATUT
   ========================================================= */

function classeStatutOffre(string $statut): string
{
    $statut = strtolower(trim($statut));

    if ($statut === "ouverte") {
        return "statut-ouverte";
    }

    if ($statut === "fermee") {
        return "statut-fermee";
    }

    return "statut-brouillon";
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

    <title>Modifier l'offre - DGLP</title>

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


        /* =====================================================
           MENU
        ===================================================== */

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


        /* =====================================================
           PROFIL ADMIN
        ===================================================== */

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
            max-width: 1250px;
            margin: 0 auto;
        }


        /* =====================================================
           EN-TÊTE PAGE
        ===================================================== */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
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
           INFORMATIONS OFFRE
        ===================================================== */

        .offer-summary {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 25px;
            background: white;
            border: 1px solid #e1e6eb;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 25px;
            box-shadow: 0 5px 16px rgba(18, 59, 104, 0.06);
        }

        .offer-title {
            color: #123b68;
            font-size: 21px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .offer-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .meta-item {
            background: #f4f7fa;
            border: 1px solid #e1e7ed;
            color: #5d6c7b;
            padding: 7px 11px;
            border-radius: 7px;
            font-size: 12px;
        }

        .meta-item strong {
            color: #123b68;
        }


        /* =====================================================
           STATUT
        ===================================================== */

        .statut {
            display: inline-block;
            padding: 7px 13px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .statut-ouverte {
            background: #dff5e8;
            color: #08783d;
        }

        .statut-fermee {
            background: #fbe2e5;
            color: #b42332;
        }

        .statut-brouillon {
            background: #fff0cf;
            color: #946200;
        }


        /* =====================================================
           MESSAGE ERREUR
        ===================================================== */

        .message-erreur {
            background: #fff0f1;
            border: 1px solid #f3c2c7;
            color: #9b2634;
            padding: 15px 18px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 14px;
        }


        /* =====================================================
           FORMULAIRE
        ===================================================== */

        .form-card {
            background: white;
            border: 1px solid #e1e6eb;
            border-radius: 14px;
            box-shadow: 0 5px 16px rgba(18, 59, 104, 0.06);
            margin-bottom: 25px;
            overflow: hidden;
        }

        .form-card-header {
            padding: 21px 25px;
            border-bottom: 1px solid #e8edf2;
        }

        .form-card-header h2 {
            color: #123b68;
            font-size: 19px;
            margin-bottom: 3px;
        }

        .form-card-header p {
            color: #718096;
            font-size: 13px;
        }

        .form-card-body {
            padding: 25px;
        }


        /* =====================================================
           FORM GROUP
        ===================================================== */

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #34495e;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input,
        textarea,
        select {
            width: 100%;
            border: 1px solid #d7e0e7;
            background: white;
            border-radius: 8px;
            padding: 11px 13px;
            color: #35495e;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: 0.2s ease;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: #123b68;
            box-shadow: 0 0 0 3px rgba(18, 59, 104, 0.08);
        }

        textarea {
            min-height: 130px;
            resize: vertical;
        }

        .grille {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }


        /* =====================================================
           CHECKBOX
        ===================================================== */

        .checkbox {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 11px 13px;
            background: #f7f9fb;
            border: 1px solid #e2e8ee;
            border-radius: 8px;
        }

        .checkbox input {
            width: auto;
            margin: 0;
            accent-color: #123b68;
        }

        .checkbox label {
            margin: 0;
            font-weight: normal;
        }


        /* =====================================================
           CRITÈRES
        ===================================================== */

        .critere {
            background: #f8fafc;
            border: 1px solid #dce4eb;
            border-radius: 11px;
            padding: 21px;
            margin-bottom: 18px;
        }

        .critere-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8ee;
        }

        .critere-header h3 {
            color: #123b68;
            font-size: 16px;
        }


        /* =====================================================
           RÈGLE
        ===================================================== */

        .radio-group {
            background: #f7f9fb;
            border: 1px solid #e1e7ed;
            border-radius: 9px;
            padding: 16px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 12px;
        }

        .radio-option:last-child {
            margin-bottom: 0;
        }

        .radio-option input {
            width: auto;
            accent-color: #123b68;
        }

        .radio-option label {
            margin: 0;
            font-weight: normal;
        }


        /* =====================================================
           BOUTONS
        ===================================================== */

        .actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .btn {
            border: none;
            padding: 11px 18px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-family: inherit;
            font-size: 13px;
            font-weight: bold;
            transition: 0.2s ease;
        }

        .btn-ajouter {
            background: #008c4a;
            color: white;
            margin-top: 3px;
        }

        .btn-ajouter:hover {
            background: #00763f;
        }

        .btn-supprimer {
            background: #fff0f1;
            color: #b42332;
            border: 1px solid #f0c2c7;
            padding: 8px 12px;
            font-size: 11px;
        }

        .btn-supprimer:hover {
            background: #b42332;
            color: white;
        }

        .btn-enregistrer {
            background: #123b68;
            color: white;
            font-size: 14px;
        }

        .btn-enregistrer:hover {
            background: #0d2d50;
        }

        .btn-retour {
            background: #eef2f5;
            color: #526271;
            border: 1px solid #dbe2e8;
        }

        .btn-retour:hover {
            background: #dfe6eb;
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

            .offer-summary {
                grid-template-columns: 1fr;
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

            .grille {
                grid-template-columns: 1fr;
            }

            .form-card-body {
                padding: 18px;
            }

            .critere {
                padding: 15px;
            }

            .critere-header {
                align-items: flex-start;
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

            .offer-summary {
                padding: 18px;
            }

            .form-card-header {
                padding: 18px;
            }

            .form-card-body {
                padding: 15px;
            }

            .actions {
                flex-direction: column;
                align-items: stretch;
            }

            .actions .btn {
                width: 100%;
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


        <a
            href="offres.php"
            class="active"
        >

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


        <a href="historique.php">

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

            <div>

                <h1>
                    Modifier l'offre
                </h1>

                <p>
                    Modifiez les informations, critères et
                    règles d'éligibilité de cette offre.
                </p>

            </div>

        </div>



        <!-- =================================================
             RÉSUMÉ DE L'OFFRE
             ================================================= -->

        <section class="offer-summary">

            <div>

                <div class="offer-title">

                    <?= htmlspecialchars(
                        $offre["nom"]
                    ) ?>

                </div>


                <div class="offer-meta">

                    <span class="meta-item">

                        <strong>ID :</strong>

                        OFFRE-<?= htmlspecialchars(
                            $offre["id_offre"]
                        ) ?>

                    </span>


                    <span class="meta-item">

                        <strong>Création :</strong>

                        <?= htmlspecialchars(
                            $offre["date_creation"]
                        ) ?>

                    </span>


                    <span class="meta-item">

                        <strong>Places :</strong>

                        <?= htmlspecialchars(
                            $offre["nombre_place"]
                        ) ?>

                    </span>


                    <span class="meta-item">

                        <strong>Échéance :</strong>

                        <?= htmlspecialchars(
                            $offre[
                                "date_limite_candidature"
                            ]
                        ) ?>

                    </span>

                </div>

            </div>


            <div>

                <span class="statut <?= htmlspecialchars(
                    classeStatutOffre(
                        $offre["statut"]
                    )
                ) ?>">

                    <?= htmlspecialchars(
                        ucfirst(
                            $offre["statut"]
                        )
                    ) ?>

                </span>

            </div>

        </section>



        <!-- =================================================
             ERREUR
             ================================================= -->

        <?php if ($erreur !== ""): ?>

            <div class="message-erreur">

                <?= htmlspecialchars($erreur) ?>

            </div>

        <?php endif; ?>



        <!-- =================================================
             FORMULAIRE
             ================================================= -->

        <form method="POST">


            <!-- =================================================
                 INFORMATIONS DE L'OFFRE
                 ================================================= -->

            <section class="form-card">

                <div class="form-card-header">

                    <h2>
                        Informations de l'offre
                    </h2>

                    <p>
                        Modifiez les informations principales
                        de l'offre de service.
                    </p>

                </div>


                <div class="form-card-body">


                    <div class="form-group">

                        <label for="nom">
                            Nom de l'offre
                        </label>

                        <input
                            type="text"
                            id="nom"
                            name="nom"
                            maxlength="150"
                            value="<?= htmlspecialchars(
                                $offre["nom"] ?? ""
                            ) ?>"
                            placeholder="Exemple : Programme d'insertion professionnelle"
                            required
                        >

                    </div>



                    <div class="form-group">

                        <label for="description">
                            Description de l'offre
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            required
                        ><?= htmlspecialchars(
                            $offre["description"]
                        ) ?></textarea>

                    </div>



                    <div class="grille">

                        <div class="form-group">

                            <label for="date_limite_candidature">
                                Date limite de candidature
                            </label>

                            <input
                                type="date"
                                id="date_limite_candidature"
                                name="date_limite_candidature"
                                value="<?= htmlspecialchars(
                                    $offre[
                                        "date_limite_candidature"
                                    ]
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="nombre_place">
                                Nombre de places
                            </label>

                            <input
                                type="number"
                                id="nombre_place"
                                name="nombre_place"
                                min="1"
                                value="<?= htmlspecialchars(
                                    $offre[
                                        "nombre_place"
                                    ]
                                ) ?>"
                                required
                            >

                        </div>

                    </div>



                    <div class="form-group">

                        <label for="montant">
                            Montant
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            id="montant"
                            name="montant"
                            value="<?= htmlspecialchars(
                                $offre["montant"] ?? ""
                            ) ?>"
                            placeholder="Exemple : 250000"
                        >

                    </div>



                    <div class="form-group">

                        <div class="checkbox">

                            <input
                                type="checkbox"
                                id="document_supplementaire"
                                name="document_supplementaire"
                                value="1"
                                <?= (
                                    (int) $offre[
                                        "document_supplementaire"
                                    ] === 1
                                )
                                    ? "checked"
                                    : "" ?>
                            >

                            <label for="document_supplementaire">
                                Documents complémentaires requis
                            </label>

                        </div>

                    </div>


                </div>

            </section>



            <!-- =================================================
                 CRITÈRES
                 ================================================= -->

            <section class="form-card">

                <div class="form-card-header">

                    <h2>
                        Critères d'éligibilité
                    </h2>

                    <p>
                        Définissez les conditions que les candidats
                        doivent respecter.
                    </p>

                </div>


                <div class="form-card-body">


                    <div id="criteres-container">


                        <?php if (!empty($criteres)): ?>


                            <?php foreach (
                                $criteres as $index => $critere
                            ): ?>


                                <div class="critere">


                                    <div class="critere-header">

                                        <h3>
                                            Critère <?= $index + 1 ?>
                                        </h3>

                                        <button
                                            type="button"
                                            class="btn btn-supprimer"
                                            onclick="supprimerCritere(this)"
                                        >
                                            Supprimer
                                        </button>

                                    </div>



                                    <div class="form-group">

                                        <label>
                                            Type de critère
                                        </label>

                                        <select
                                            name="critere[<?= $index ?>][nom]"
                                            required
                                        >

                                            <option value="">
                                                Sélectionner
                                            </option>

                                            <option
                                                value="Âge"
                                                <?= (
                                                    $critere["nom"]
                                                    === "Âge"
                                                )
                                                    ? "selected"
                                                    : "" ?>
                                            >
                                                Âge
                                            </option>

                                            <option
                                                value="Résidence"
                                                <?= (
                                                    $critere["nom"]
                                                    === "Résidence"
                                                )
                                                    ? "selected"
                                                    : "" ?>
                                            >
                                                Résidence
                                            </option>

                                            <option
                                                value="Qualification"
                                                <?= (
                                                    $critere["nom"]
                                                    === "Qualification"
                                                )
                                                    ? "selected"
                                                    : "" ?>
                                            >
                                                Qualification
                                            </option>

                                            <option
                                                value="Situation sociale"
                                                <?= (
                                                    $critere["nom"]
                                                    === "Situation sociale"
                                                )
                                                    ? "selected"
                                                    : "" ?>
                                            >
                                                Situation sociale
                                            </option>

                                            <option
                                                value="Autre"
                                                <?= (
                                                    !in_array(
                                                        $critere["nom"],
                                                        [
                                                            "Âge",
                                                            "Résidence",
                                                            "Qualification",
                                                            "Situation sociale"
                                                        ],
                                                        true
                                                    )
                                                )
                                                    ? "selected"
                                                    : "" ?>
                                            >
                                                Autre
                                            </option>

                                        </select>

                                    </div>



                                    <div class="form-group">

                                        <label>
                                            Description
                                        </label>

                                        <textarea
                                            name="critere[<?= $index ?>][description]"
                                        ><?= htmlspecialchars(
                                            $critere[
                                                "description"
                                            ] ?? ""
                                        ) ?></textarea>

                                    </div>



                                    <div class="grille">

                                        <div class="form-group">

                                            <label>
                                                Valeur minimale
                                            </label>

                                            <input
                                                type="number"
                                                step="0.01"
                                                name="critere[<?= $index ?>][valeur_min]"
                                                value="<?= htmlspecialchars(
                                                    $critere[
                                                        "valeur_min"
                                                    ] ?? ""
                                                ) ?>"
                                            >

                                        </div>


                                        <div class="form-group">

                                            <label>
                                                Valeur maximale
                                            </label>

                                            <input
                                                type="number"
                                                step="0.01"
                                                name="critere[<?= $index ?>][valeur_max]"
                                                value="<?= htmlspecialchars(
                                                    $critere[
                                                        "valeur_max"
                                                    ] ?? ""
                                                ) ?>"
                                            >

                                        </div>

                                    </div>



                                    <div class="form-group">

                                        <label>
                                            Valeur attendue
                                        </label>

                                        <input
                                            type="text"
                                            name="critere[<?= $index ?>][valeur_attendue]"
                                            value="<?= htmlspecialchars(
                                                $critere[
                                                    "valeur_attendue"
                                                ] ?? ""
                                            ) ?>"
                                        >

                                    </div>



                                    <div class="checkbox">

                                        <input
                                            type="checkbox"
                                            name="critere[<?= $index ?>][obligatoire]"
                                            value="1"
                                            <?= (
                                                (int) (
                                                    $critere[
                                                        "obligatoire"
                                                    ] ?? 0
                                                ) === 1
                                            )
                                                ? "checked"
                                                : "" ?>
                                        >

                                        <label>
                                            Critère obligatoire
                                        </label>

                                    </div>


                                </div>


                            <?php endforeach; ?>


                        <?php else: ?>


                            <div class="critere">

                                <div class="critere-header">

                                    <h3>
                                        Critère 1
                                    </h3>

                                    <button
                                        type="button"
                                        class="btn btn-supprimer"
                                        onclick="supprimerCritere(this)"
                                    >
                                        Supprimer
                                    </button>

                                </div>


                                <div class="form-group">

                                    <label>
                                        Type de critère
                                    </label>

                                    <select
                                        name="critere[0][nom]"
                                    >

                                        <option value="">
                                            Sélectionner
                                        </option>

                                        <option value="Âge">
                                            Âge
                                        </option>

                                        <option value="Résidence">
                                            Résidence
                                        </option>

                                        <option value="Qualification">
                                            Qualification
                                        </option>

                                        <option value="Situation sociale">
                                            Situation sociale
                                        </option>

                                        <option value="Autre">
                                            Autre
                                        </option>

                                    </select>

                                </div>


                                <div class="form-group">

                                    <label>
                                        Description
                                    </label>

                                    <textarea
                                        name="critere[0][description]"
                                    ></textarea>

                                </div>


                                <div class="grille">

                                    <div class="form-group">

                                        <label>
                                            Valeur minimale
                                        </label>

                                        <input
                                            type="number"
                                            step="0.01"
                                            name="critere[0][valeur_min]"
                                        >

                                    </div>


                                    <div class="form-group">

                                        <label>
                                            Valeur maximale
                                        </label>

                                        <input
                                            type="number"
                                            step="0.01"
                                            name="critere[0][valeur_max]"
                                        >

                                    </div>

                                </div>


                                <div class="form-group">

                                    <label>
                                        Valeur attendue
                                    </label>

                                    <input
                                        type="text"
                                        name="critere[0][valeur_attendue]"
                                    >

                                </div>


                                <div class="checkbox">

                                    <input
                                        type="checkbox"
                                        name="critere[0][obligatoire]"
                                        value="1"
                                    >

                                    <label>
                                        Critère obligatoire
                                    </label>

                                </div>

                            </div>


                        <?php endif; ?>


                    </div>



                    <button
                        type="button"
                        class="btn btn-ajouter"
                        onclick="ajouterCritere()"
                    >
                        + Ajouter un critère
                    </button>


                </div>

            </section>



            <!-- =================================================
                 RÈGLE D'ÉLIGIBILITÉ
                 ================================================= -->

            <section class="form-card">

                <div class="form-card-header">

                    <h2>
                        Règle d'éligibilité
                    </h2>

                    <p>
                        Déterminez comment les critères seront
                        utilisés pour l'évaluation des candidats.
                    </p>

                </div>


                <div class="form-card-body">

                    <div class="radio-group">


                        <div class="radio-option">

                            <input
                                type="radio"
                                id="tous"
                                name="regle_eligibilite"
                                value="tous"
                                <?= (
                                    $regle === "tous"
                                )
                                    ? "checked"
                                    : "" ?>
                            >

                            <label for="tous">
                                Tous les critères doivent être respectés
                            </label>

                        </div>


                        <div class="radio-option">

                            <input
                                type="radio"
                                id="au_moins_un"
                                name="regle_eligibilite"
                                value="au_moins_un"
                                <?= (
                                    $regle === "au_moins_un"
                                )
                                    ? "checked"
                                    : "" ?>
                            >

                            <label for="au_moins_un">
                                Au moins un critère doit être respecté
                            </label>

                        </div>


                    </div>


                    <!-- =================================================
                         BOUTONS
                         ================================================= -->

                    <div class="actions">

                        <button
                            type="submit"
                            class="btn btn-enregistrer"
                        >
                            Enregistrer les modifications
                        </button>


                        <a
                            href="offres.php"
                            class="btn btn-retour"
                        >
                            Annuler
                        </a>

                    </div>

                </div>

            </section>


        </form>


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



<script>

/* =========================================================
   COMPTEUR DES CRITÈRES
   ========================================================= */

let compteurCritere =
    <?= !empty($criteres)
        ? count($criteres)
        : 1 ?>;


/* =========================================================
   AJOUTER UN CRITÈRE
   ========================================================= */

function ajouterCritere() {

    const container =
        document.getElementById(
            "criteres-container"
        );


    const div =
        document.createElement("div");


    div.className = "critere";


    div.innerHTML = `

        <div class="critere-header">

            <h3>
                Critère ${compteurCritere + 1}
            </h3>

            <button
                type="button"
                class="btn btn-supprimer"
                onclick="supprimerCritere(this)"
            >
                Supprimer
            </button>

        </div>


        <div class="form-group">

            <label>
                Type de critère
            </label>

            <select
                name="critere[${compteurCritere}][nom]"
                required
            >

                <option value="">
                    Sélectionner
                </option>

                <option value="Âge">
                    Âge
                </option>

                <option value="Résidence">
                    Résidence
                </option>

                <option value="Qualification">
                    Qualification
                </option>

                <option value="Situation sociale">
                    Situation sociale
                </option>

                <option value="Autre">
                    Autre
                </option>

            </select>

        </div>


        <div class="form-group">

            <label>
                Description
            </label>

            <textarea
                name="critere[${compteurCritere}][description]"
            ></textarea>

        </div>


        <div class="grille">

            <div class="form-group">

                <label>
                    Valeur minimale
                </label>

                <input
                    type="number"
                    step="0.01"
                    name="critere[${compteurCritere}][valeur_min]"
                >

            </div>


            <div class="form-group">

                <label>
                    Valeur maximale
                </label>

                <input
                    type="number"
                    step="0.01"
                    name="critere[${compteurCritere}][valeur_max]"
                >

            </div>

        </div>


        <div class="form-group">

            <label>
                Valeur attendue
            </label>

            <input
                type="text"
                name="critere[${compteurCritere}][valeur_attendue]"
            >

        </div>


        <div class="checkbox">

            <input
                type="checkbox"
                name="critere[${compteurCritere}][obligatoire]"
                value="1"
            >

            <label>
                Critère obligatoire
            </label>

        </div>

    `;


    container.appendChild(div);

    compteurCritere++;
}


/* =========================================================
   SUPPRIMER UN CRITÈRE
   ========================================================= */

function supprimerCritere(bouton) {

    const critere =
        bouton.closest(".critere");


    if (critere) {

        critere.remove();

    }
}

</script>


</body>

</html>