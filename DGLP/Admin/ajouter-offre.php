<?php

session_start();
require_once "../../database.php";

/* Vérifier que l'administrateur est connecté */
if (!isset($_SESSION["id_admin"])) {
    header("Location: connexion.php");
    exit;
}

$erreur = "";
$succes = "";

/*
|--------------------------------------------------------------------------
| TRAITEMENT DU FORMULAIRE
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* ==============================
       INFORMATIONS DE L'OFFRE
       ============================== */

    $nom = trim($_POST["nom"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $date_limite = $_POST["date_limite_candidature"] ?? "";
    $nombre_place = $_POST["nombre_place"] ?? "";
    $montant = $_POST["montant"] ?? "";
    $document_supplementaire = isset($_POST["document_supplementaire"]) ? 1 : 0;

    /* ==============================
       CRITÈRES D'ÉLIGIBILITÉ
       ============================== */

    $criteres = $_POST["critere"] ?? [];

    /* ==============================
       RÈGLE D'ÉLIGIBILITÉ
       ============================== */

    $regle_eligibilite = $_POST["regle_eligibilite"] ?? "tous";

    /* ==============================
       VALIDATION
       ============================== */

    if (
        $nom === "" ||
        $description === "" ||
        $date_limite === "" ||
        $nombre_place === ""
    ) {

        $erreur = "Veuillez remplir tous les champs obligatoires.";

    } elseif (strlen($nom) > 150) {

        $erreur = "Le nom de l'offre ne doit pas dépasser 150 caractères.";

    } elseif (!ctype_digit($nombre_place) || (int)$nombre_place <= 0) {

        $erreur = "Le nombre de places doit être un nombre supérieur à zéro.";

    } elseif ($date_limite < date("Y-m-d")) {

        $erreur = "La date limite de candidature ne peut pas être dépassée.";

    } elseif (
        $montant !== "" &&
        (!is_numeric($montant) || $montant < 0)
    ) {

        $erreur = "Le montant indiqué est incorrect.";

    } elseif (
        !in_array(
            $regle_eligibilite,
            ["tous", "au_moins_un"],
            true
        )
    ) {

        $erreur = "La règle d'éligibilité sélectionnée est incorrecte.";

    } else {

        try {

            /*
             * Démarrage d'une transaction.
             */
            $pdo->beginTransaction();

            /* ==============================
               1. CRÉATION DE L'OFFRE
               ============================== */

            $requete = $pdo->prepare("
                INSERT INTO offre
                (
                    nom,
                    description,
                    date_creation,
                    date_limite_candidature,
                    nombre_place,
                    critere,
                    montant,
                    document_supplementaire,
                    statut,
                    id_admin
                )
                VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, 'brouillon', ?)
            ");

            $requete->execute([
                $nom,
                $description,
                $date_limite,
                (int)$nombre_place,
                null,
                $montant !== "" ? $montant : null,
                $document_supplementaire,
                $_SESSION["id_admin"]
            ]);

            /* Récupérer l'identifiant de l'offre */
            $id_offre = $pdo->lastInsertId();

            /* ==============================
               2. ENREGISTREMENT DES CRITÈRES
               ============================== */

            if (!empty($criteres)) {

                $requeteCritere = $pdo->prepare("
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

                foreach ($criteres as $critere) {

                    $nomCritere = trim(
                        $critere["nom"] ?? ""
                    );

                    $descriptionCritere = trim(
                        $critere["description"] ?? ""
                    );

                    $valeurMin = trim(
                        $critere["valeur_min"] ?? ""
                    );

                    $valeurMax = trim(
                        $critere["valeur_max"] ?? ""
                    );

                    $valeurAttendue = trim(
                        $critere["valeur_attendue"] ?? ""
                    );

                    $obligatoire = isset(
                        $critere["obligatoire"]
                    ) ? 1 : 0;

                    /*
                     * Ignorer une ligne complètement vide.
                     */
                    if (
                        $nomCritere === "" &&
                        $descriptionCritere === "" &&
                        $valeurMin === "" &&
                        $valeurMax === "" &&
                        $valeurAttendue === ""
                    ) {
                        continue;
                    }

                    /*
                     * Le nom du critère est obligatoire.
                     */
                    if ($nomCritere === "") {

                        throw new Exception(
                            "Chaque critère renseigné doit avoir un nom."
                        );
                    }

                    /*
                     * Vérification de la valeur minimale.
                     */
                    if (
                        $valeurMin !== "" &&
                        !is_numeric($valeurMin)
                    ) {

                        throw new Exception(
                            "La valeur minimale du critère « "
                            . $nomCritere .
                            " » est incorrecte."
                        );
                    }

                    /*
                     * Vérification de la valeur maximale.
                     */
                    if (
                        $valeurMax !== "" &&
                        !is_numeric($valeurMax)
                    ) {

                        throw new Exception(
                            "La valeur maximale du critère « "
                            . $nomCritere .
                            " » est incorrecte."
                        );
                    }

                    /*
                     * Vérification minimum / maximum.
                     */
                    if (
                        $valeurMin !== "" &&
                        $valeurMax !== "" &&
                        (float)$valeurMin > (float)$valeurMax
                    ) {

                        throw new Exception(
                            "Pour le critère « "
                            . $nomCritere .
                            " », la valeur minimale ne peut pas être supérieure à la valeur maximale."
                        );
                    }

                    /*
                     * Enregistrement du critère.
                     */
                    $requeteCritere->execute([
                        $id_offre,
                        $nomCritere,
                        $descriptionCritere !== ""
                            ? $descriptionCritere
                            : null,
                        $valeurMin !== ""
                            ? $valeurMin
                            : null,
                        $valeurMax !== ""
                            ? $valeurMax
                            : null,
                        $valeurAttendue !== ""
                            ? $valeurAttendue
                            : null,
                        $obligatoire
                    ]);
                }
            }

            /* ==============================
               3. ENREGISTREMENT DE LA RÈGLE
               ============================== */

            $requeteRegle = $pdo->prepare("
                INSERT INTO regle_eligibilite
                (
                    id_offre,
                    type_regle
                )
                VALUES (?, ?)
            ");

            $requeteRegle->execute([
                $id_offre,
                $regle_eligibilite
            ]);

            /*
             * Tout s'est bien passé.
             */
            $pdo->commit();

            header("Location: offres.php");
            exit;

        } catch (Exception $e) {

            /*
             * Annuler toutes les opérations
             * en cas d'erreur.
             */
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erreur =
                "Erreur lors de la création de l'offre : "
                . $e->getMessage();
        }
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

    <title>Créer une offre - DGLP Administration</title>

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
            max-width: 1250px;
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
           MESSAGES
        ========================================================= */

        .message-erreur {
            padding: 14px 17px;
            margin-bottom: 22px;
            background: #fff1f1;
            border: 1px solid #efb7b7;
            border-left: 5px solid #c0392b;
            color: #9f2929;
            border-radius: 8px;
            font-size: 14px;
        }


        /* =========================================================
           FORMULAIRE
        ========================================================= */

        .form-card {
            background: #ffffff;
            border: 1px solid #e0e6eb;
            border-radius: 13px;
            box-shadow: 0 7px 22px rgba(18, 59, 104, 0.07);
            margin-bottom: 22px;
            overflow: hidden;
        }

        .card-header {
            padding: 21px 25px;
            border-bottom: 1px solid #e7ebef;
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: #eaf3ee;
            color: #008c4a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }

        .card-header h3 {
            color: #123b68;
            font-size: 18px;
        }

        .card-header p {
            color: #7d8993;
            font-size: 12px;
        }

        .card-body {
            padding: 25px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            color: #34495e;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        .required {
            color: #c0392b;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        textarea,
        select {
            width: 100%;
            border: 1px solid #ccd5dd;
            border-radius: 7px;
            background: #ffffff;
            color: #263238;
            font-size: 14px;
            outline: none;
            transition: 0.2s ease;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        select {
            height: 44px;
            padding: 0 12px;
        }

        textarea {
            padding: 12px;
            resize: vertical;
            min-height: 125px;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: #123b68;
            box-shadow: 0 0 0 3px rgba(18,59,104,0.09);
        }

        .aide {
            margin-top: 5px;
            color: #8a969f;
            font-size: 11px;
        }


        /* =========================================================
           CHECKBOX
        ========================================================= */

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-top: 10px;
            color: #465762;
            font-size: 13px;
            font-weight: 600;
        }

        .checkbox-container input {
            width: 17px;
            height: 17px;
            accent-color: #008c4a;
        }


        /* =========================================================
           CRITERES
        ========================================================= */

        .bloc-criteres {
            margin-top: 0;
        }

        .intro-criteres {
            color: #6e7c87;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .critere {
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #dfe5ea;
            border-radius: 10px;
            background: #f8fafb;
        }

        .critere-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .critere-header h4 {
            color: #123b68;
            font-size: 15px;
        }

        .numero-critere {
            background: #123b68;
            color: #ffffff;
            border-radius: 20px;
            padding: 5px 11px;
            font-size: 11px;
            font-weight: 700;
        }

        .ligne-criteres {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .critere .full {
            grid-column: 1 / -1;
        }

        .bouton-ajouter {
            margin-top: 5px;
            background: #eaf3ee;
            color: #008c4a;
            border: 1px solid #b9ddc9;
            padding: 10px 17px;
            border-radius: 7px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
        }

        .bouton-ajouter:hover {
            background: #008c4a;
            color: #ffffff;
        }

        .bouton-supprimer {
            margin-top: 15px;
            background: #fff1f1;
            color: #b52e2e;
            border: 1px solid #efc0c0;
            padding: 8px 13px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .bouton-supprimer:hover {
            background: #c0392b;
            color: #ffffff;
        }


        /* =========================================================
           REGLE ELIGIBILITE
        ========================================================= */

        .regle {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .option-regle {
            border: 1px solid #dce3e8;
            border-radius: 9px;
            padding: 17px;
            cursor: pointer;
            transition: 0.2s ease;
            background: #ffffff;
        }

        .option-regle:hover {
            border-color: #123b68;
            background: #f8fbfd;
        }

        .option-regle label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 0;
            cursor: pointer;
        }

        .option-regle input {
            margin-top: 3px;
            accent-color: #008c4a;
        }

        .option-regle strong {
            display: block;
            color: #123b68;
            font-size: 14px;
        }

        .option-regle span {
            display: block;
            color: #7a8791;
            font-size: 12px;
            font-weight: normal;
            margin-top: 3px;
        }


        /* =========================================================
           BOUTONS
        ========================================================= */

        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 25px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 22px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: 0.25s ease;
        }

        .btn-principal {
            background: #008c4a;
            color: #ffffff;
        }

        .btn-principal:hover {
            background: #006f3b;
            transform: translateY(-1px);
            box-shadow: 0 5px 12px rgba(0,140,74,0.20);
        }

        .btn-annuler {
            background: #ffffff;
            color: #123b68;
            border: 1px solid #cbd5dd;
        }

        .btn-annuler:hover {
            background: #edf3f8;
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .ligne-criteres {
                grid-template-columns: 1fr;
            }

            .critere .full {
                grid-column: auto;
            }

            .regle {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }

            .admin-text {
                display: none;
            }
        }

    </style>

</head>


<body>


<!-- =========================================================
     SIDEBAR ADMINISTRATION
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


        <a href="offres.php" class="active">

            <span class="menu-icon">▤</span>

            <span class="menu-text">
                Offres
            </span>

        </a>


        <a href="candidatures.php">

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
                A
            </div>

            <div class="admin-text">

                <strong>
                    Administrateur
                </strong>

                <span>
                    Gestion des offres
                </span>

            </div>

        </div>

    </header>


    <!-- PAGE -->

    <main class="page">


        <div class="page-title">

            <span class="badge">
                Gestion des offres
            </span>

            <h2>
                Créer une nouvelle offre
            </h2>

            <p>
                Configurez les informations, les critères et les règles
                d'éligibilité de l'offre de service.
            </p>

        </div>


        <?php if ($erreur !== ""): ?>

            <div class="message-erreur">

                <?= htmlspecialchars($erreur) ?>

            </div>

        <?php endif; ?>


        <form method="POST">


            <!-- =====================================================
                 INFORMATIONS DE L'OFFRE
            ====================================================== -->

            <section class="form-card">

                <div class="card-header">

                    <div class="card-icon">
                        01
                    </div>

                    <div>

                        <h3>
                            Informations de l'offre
                        </h3>

                        <p>
                            Présentez les principales informations du programme.
                        </p>

                    </div>

                </div>


                <div class="card-body">

                    <div class="form-grid">


                        <!-- NOM -->

                        <div class="form-group full">

                            <label for="nom">

                                Nom de l'offre
                                <span class="required">*</span>

                            </label>

                            <input
                                type="text"
                                id="nom"
                                name="nom"
                                maxlength="150"
                                placeholder="Exemple : Programme d'insertion professionnelle"
                                value="<?= htmlspecialchars(
                                    $_POST["nom"] ?? ""
                                ) ?>"
                                required
                            >

                        </div>


                        <!-- DESCRIPTION -->

                        <div class="form-group full">

                            <label for="description">

                                Description de l'offre
                                <span class="required">*</span>

                            </label>

                            <textarea
                                id="description"
                                name="description"
                                placeholder="Décrivez l'offre de service, son objectif et les bénéficiaires concernés..."
                                required
                            ><?= htmlspecialchars(
                                $_POST["description"] ?? ""
                            ) ?></textarea>

                        </div>


                        <!-- DATE LIMITE -->

                        <div class="form-group">

                            <label for="date_limite_candidature">

                                Date limite de candidature
                                <span class="required">*</span>

                            </label>

                            <input
                                type="date"
                                id="date_limite_candidature"
                                name="date_limite_candidature"
                                value="<?= htmlspecialchars(
                                    $_POST["date_limite_candidature"] ?? ""
                                ) ?>"
                                min="<?= date("Y-m-d") ?>"
                                required
                            >

                        </div>


                        <!-- NOMBRE DE PLACES -->

                        <div class="form-group">

                            <label for="nombre_place">

                                Nombre de places
                                <span class="required">*</span>

                            </label>

                            <input
                                type="number"
                                id="nombre_place"
                                name="nombre_place"
                                min="1"
                                placeholder="Exemple : 50"
                                value="<?= htmlspecialchars(
                                    $_POST["nombre_place"] ?? ""
                                ) ?>"
                                required
                            >

                        </div>


                        <!-- MONTANT -->

                        <div class="form-group">

                            <label for="montant">

                                Montant (FCFA)

                            </label>

                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                id="montant"
                                name="montant"
                                placeholder="Exemple : 500000"
                                value="<?= htmlspecialchars(
                                    $_POST["montant"] ?? ""
                                ) ?>"
                            >

                            <span class="aide">
                                Laissez vide si aucun montant n'est associé à l'offre.
                            </span>

                        </div>


                        <!-- DOCUMENTS -->

                        <div class="form-group">

                            <label>
                                Documents complémentaires
                            </label>

                            <label class="checkbox-container">

                                <input
                                    type="checkbox"
                                    name="document_supplementaire"
                                    value="1"
                                    <?= isset(
                                        $_POST["document_supplementaire"]
                                    ) ? "checked" : "" ?>
                                >

                                Documents demandés

                            </label>

                        </div>


                    </div>

                </div>

            </section>


            <!-- =====================================================
                 CRITERES
            ====================================================== -->

            <section class="form-card">

                <div class="card-header">

                    <div class="card-icon">
                        02
                    </div>

                    <div>

                        <h3>
                            Critères d'éligibilité
                        </h3>

                        <p>
                            Définissez les conditions permettant d'évaluer les candidats.
                        </p>

                    </div>

                </div>


                <div class="card-body bloc-criteres">

                    <p class="intro-criteres">

                        Ajoutez les critères qui permettront de déterminer
                        automatiquement ou manuellement l'éligibilité des candidats.

                    </p>


                    <div id="liste-criteres">


                        <!-- PREMIER CRITERE -->

                        <div class="critere">

                            <div class="critere-header">

                                <h4>
                                    Critère d'éligibilité
                                </h4>

                                <span class="numero-critere">
                                    CRITÈRE 1
                                </span>

                            </div>


                            <div class="ligne-criteres">


                                <!-- TYPE -->

                                <div class="form-group">

                                    <label>
                                        Type de critère
                                    </label>

                                    <select
                                        name="critere[0][nom]"
                                        required
                                    >

                                        <option value="">
                                            -- Choisir un critère --
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

                                    </select>

                                </div>


                                <!-- DESCRIPTION -->

                                <div class="form-group">

                                    <label>
                                        Description
                                    </label>

                                    <input
                                        type="text"
                                        name="critere[0][description]"
                                        placeholder="Exemple : âge du candidat"
                                    >

                                </div>


                                <!-- MIN -->

                                <div class="form-group">

                                    <label>
                                        Valeur minimale
                                    </label>

                                    <input
                                        type="number"
                                        step="0.01"
                                        name="critere[0][valeur_min]"
                                        placeholder="Exemple : 18"
                                    >

                                </div>


                                <!-- MAX -->

                                <div class="form-group">

                                    <label>
                                        Valeur maximale
                                    </label>

                                    <input
                                        type="number"
                                        step="0.01"
                                        name="critere[0][valeur_max]"
                                        placeholder="Exemple : 35"
                                    >

                                </div>


                                <!-- VALEUR ATTENDUE -->

                                <div class="form-group full">

                                    <label>
                                        Valeur attendue
                                    </label>

                                    <input
                                        type="text"
                                        name="critere[0][valeur_attendue]"
                                        placeholder="Exemple : Libreville"
                                    >

                                </div>


                            </div>


                            <label class="checkbox-container">

                                <input
                                    type="checkbox"
                                    name="critere[0][obligatoire]"
                                    value="1"
                                    checked
                                >

                                Critère obligatoire

                            </label>


                        </div>

                    </div>


                    <button
                        type="button"
                        class="bouton-ajouter"
                        onclick="ajouterCritere()"
                    >
                        + Ajouter un critère
                    </button>


                </div>

            </section>


            <!-- =====================================================
                 REGLE
            ====================================================== -->

            <section class="form-card">

                <div class="card-header">

                    <div class="card-icon">
                        03
                    </div>

                    <div>

                        <h3>
                            Règle d'éligibilité
                        </h3>

                        <p>
                            Déterminez comment les critères seront appliqués.
                        </p>

                    </div>

                </div>


                <div class="card-body">

                    <div class="regle">


                        <div class="option-regle">

                            <label>

                                <input
                                    type="radio"
                                    name="regle_eligibilite"
                                    value="tous"
                                    <?= (
                                        ($_POST["regle_eligibilite"] ?? "tous")
                                        === "tous"
                                    ) ? "checked" : "" ?>
                                >

                                <span>

                                    <strong>
                                        Tous les critères
                                    </strong>

                                    <span>
                                        Le candidat doit respecter l'ensemble
                                        des critères définis.
                                    </span>

                                </span>

                            </label>

                        </div>


                        <div class="option-regle">

                            <label>

                                <input
                                    type="radio"
                                    name="regle_eligibilite"
                                    value="au_moins_un"
                                    <?= (
                                        ($_POST["regle_eligibilite"] ?? "")
                                        === "au_moins_un"
                                    ) ? "checked" : "" ?>
                                >

                                <span>

                                    <strong>
                                        Au moins un critère
                                    </strong>

                                    <span>
                                        Le candidat doit respecter au moins
                                        un des critères définis.
                                    </span>

                                </span>

                            </label>

                        </div>


                    </div>

                </div>

            </section>


            <!-- =====================================================
                 ACTIONS
            ====================================================== -->

            <div class="actions">

                <a
                    href="offres.php"
                    class="btn btn-annuler"
                >
                    ← Annuler
                </a>


                <button
                    type="submit"
                    class="btn btn-principal"
                >
                    Créer l'offre
                </button>

            </div>


        </form>


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


<script>

let nombreCriteres = 1;


/*
|--------------------------------------------------------------------------
| AJOUTER UN CRITÈRE
|--------------------------------------------------------------------------
*/

function ajouterCritere() {

    const liste =
        document.getElementById("liste-criteres");

    const index = nombreCriteres;

    const div =
        document.createElement("div");

    div.className = "critere";

    div.innerHTML = `

        <div class="critere-header">

            <h4>
                Critère d'éligibilité
            </h4>

            <span class="numero-critere">
                CRITÈRE ${index + 1}
            </span>

        </div>


        <div class="ligne-criteres">


            <div class="form-group">

                <label>
                    Type de critère
                </label>

                <select
                    name="critere[${index}][nom]"
                    required
                >

                    <option value="">
                        -- Choisir un critère --
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

                </select>

            </div>


            <div class="form-group">

                <label>
                    Description
                </label>

                <input
                    type="text"
                    name="critere[${index}][description]"
                    placeholder="Description du critère"
                >

            </div>


            <div class="form-group">

                <label>
                    Valeur minimale
                </label>

                <input
                    type="number"
                    step="0.01"
                    name="critere[${index}][valeur_min]"
                    placeholder="Exemple : 18"
                >

            </div>


            <div class="form-group">

                <label>
                    Valeur maximale
                </label>

                <input
                    type="number"
                    step="0.01"
                    name="critere[${index}][valeur_max]"
                    placeholder="Exemple : 35"
                >

            </div>


            <div class="form-group full">

                <label>
                    Valeur attendue
                </label>

                <input
                    type="text"
                    name="critere[${index}][valeur_attendue]"
                    placeholder="Exemple : Libreville"
                >

            </div>


        </div>


        <label class="checkbox-container">

            <input
                type="checkbox"
                name="critere[${index}][obligatoire]"
                value="1"
                checked
            >

            Critère obligatoire

        </label>


        <button
            type="button"
            class="bouton-supprimer"
            onclick="supprimerCritere(this)"
        >
            Supprimer ce critère
        </button>

    `;

    liste.appendChild(div);

    nombreCriteres++;

}


/*
|--------------------------------------------------------------------------
| SUPPRIMER UN CRITÈRE
|--------------------------------------------------------------------------
*/

function supprimerCritere(bouton) {

    const critere =
        bouton.closest(".critere");

    critere.remove();

}

</script>


</body>

</html>