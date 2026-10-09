<?php

session_start();

require_once "../../database.php";

/*
|--------------------------------------------------------------------------
| VERIFICATION DE LA SESSION
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["id_candidat"])) {
    header("Location: connexion.php");
    exit;
}

$id_candidat = $_SESSION["id_candidat"];

$message = "";
$message_type = "";


/*
|--------------------------------------------------------------------------
| INFORMATIONS DU CANDIDAT
|--------------------------------------------------------------------------
*/

$requete = $pdo->prepare("
    SELECT
        nom,
        prenom,
        date_naissance,
        province,
        ville,
        telephone
    FROM candidat
    WHERE id_candidat = ?
");

$requete->execute([$id_candidat]);

$candidat = $requete->fetch(PDO::FETCH_ASSOC);

if (!$candidat) {
    session_destroy();
    header("Location: connexion.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| EMAIL DU CANDIDAT
|--------------------------------------------------------------------------
*/

$requete = $pdo->prepare("
    SELECT email
    FROM compte
    WHERE id_candidat = ?
");

$requete->execute([$id_candidat]);

$compte = $requete->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| DOSSIER EXISTANT
|--------------------------------------------------------------------------
*/

$requete = $pdo->prepare("
    SELECT *
    FROM dossier
    WHERE id_candidat = ?
    LIMIT 1
");

$requete->execute([$id_candidat]);

$dossier = $requete->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| TRAITEMENT DU FORMULAIRE
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
    |--------------------------------------------------------------------------
    | RECUPERATION DES CHAMPS
    |--------------------------------------------------------------------------
    */

    $nip = trim($_POST["nip"] ?? "");

    $situation_matrimoniale =
        trim($_POST["situation_matrimoniale"] ?? "");

    $personn_a_charge =
        trim($_POST["personnes_charge"] ?? "");

    /*
     * IMPORTANT :
     * Le nom de la colonne dans la base est
     * situation_profesionnelle
     * avec un seul "s" dans "profesionnelle".
     */
    $situation_profesionnelle =
        trim($_POST["situation_professionnelle"] ?? "");

    $activite =
        trim($_POST["activite_actuelle"] ?? "");

    $type_activite =
        trim($_POST["type_activite"] ?? "");

    $description_activite =
        trim($_POST["description_activite"] ?? "");


    /*
    |--------------------------------------------------------------------------
    | DOSSIER D'UPLOAD
    |--------------------------------------------------------------------------
    */

    $dossier_upload = "../../uploads/dossiers/";

    if (!is_dir($dossier_upload)) {
        mkdir($dossier_upload, 0775, true);
    }


    /*
    |--------------------------------------------------------------------------
    | FICHIERS EXISTANTS
    |--------------------------------------------------------------------------
    */

    $carte_photo =
        $dossier["carte_photo"] ?? null;

    $acte_naissance =
        $dossier["acte_naissance"] ?? null;


    /*
    |--------------------------------------------------------------------------
    | UPLOAD PHOTO
    |--------------------------------------------------------------------------
    */

    if (
        isset($_FILES["carte_photo"]) &&
        $_FILES["carte_photo"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES["carte_photo"]["error"] === UPLOAD_ERR_OK) {

            $fichier = $_FILES["carte_photo"];

            $taille_max = 5 * 1024 * 1024;

            $types_autorises = [
                "image/jpeg",
                "image/png"
            ];

            $finfo = new finfo(FILEINFO_MIME_TYPE);

            $type = $finfo->file(
                $fichier["tmp_name"]
            );

            if (
                $fichier["size"] <= $taille_max &&
                in_array($type, $types_autorises, true)
            ) {

                $extension =
                    ($type === "image/png")
                    ? "png"
                    : "jpg";

                $nom_fichier =
                    "photo_" .
                    $id_candidat .
                    "_" .
                    time() .
                    "." .
                    $extension;

                $destination =
                    $dossier_upload . $nom_fichier;

                if (
                    move_uploaded_file(
                        $fichier["tmp_name"],
                        $destination
                    )
                ) {

                    $carte_photo = $nom_fichier;

                } else {

                    $message =
                        "Impossible d'enregistrer la photo.";

                    $message_type = "erreur";
                }

            } else {

                $message =
                    "La photo doit être une image JPG ou PNG de 5 Mo maximum.";

                $message_type = "erreur";
            }

        } else {

            $message =
                "Une erreur est survenue lors de l'envoi de la photo.";

            $message_type = "erreur";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPLOAD ACTE DE NAISSANCE
    |--------------------------------------------------------------------------
    */

    if (
        empty($message) &&
        isset($_FILES["acte_naissance"]) &&
        $_FILES["acte_naissance"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES["acte_naissance"]["error"] === UPLOAD_ERR_OK) {

            $fichier = $_FILES["acte_naissance"];

            $taille_max = 5 * 1024 * 1024;

            $types_autorises = [
                "application/pdf",
                "image/jpeg",
                "image/png"
            ];

            $finfo = new finfo(FILEINFO_MIME_TYPE);

            $type = $finfo->file(
                $fichier["tmp_name"]
            );

            if (
                $fichier["size"] <= $taille_max &&
                in_array($type, $types_autorises, true)
            ) {

                if ($type === "application/pdf") {
                    $extension = "pdf";
                } elseif ($type === "image/png") {
                    $extension = "png";
                } else {
                    $extension = "jpg";
                }

                $nom_fichier =
                    "acte_naissance_" .
                    $id_candidat .
                    "_" .
                    time() .
                    "." .
                    $extension;

                $destination =
                    $dossier_upload . $nom_fichier;

                if (
                    move_uploaded_file(
                        $fichier["tmp_name"],
                        $destination
                    )
                ) {

                    $acte_naissance = $nom_fichier;

                } else {

                    $message =
                        "Impossible d'enregistrer l'acte de naissance.";

                    $message_type = "erreur";
                }

            } else {

                $message =
                    "L'acte de naissance doit être un PDF, JPG ou PNG de 5 Mo maximum.";

                $message_type = "erreur";
            }

        } else {

            $message =
                "Une erreur est survenue lors de l'envoi de l'acte de naissance.";

            $message_type = "erreur";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CALCUL DU STATUT DU DOSSIER
    |--------------------------------------------------------------------------
    */

    if (
        !empty($nip) &&
        !empty($carte_photo) &&
        !empty($acte_naissance) &&
        !empty($situation_matrimoniale) &&
        $personn_a_charge !== "" &&
        !empty($situation_profesionnelle) &&
        !empty($activite) &&
        !empty($type_activite)
    ) {

        $statut = "complet";

    } else {

        $statut = "incomplet";
    }


    /*
    |--------------------------------------------------------------------------
    | ENREGISTREMENT EN BASE
    |--------------------------------------------------------------------------
    */

    if (empty($message)) {

        try {

            /*
            |--------------------------------------------------------------------------
            | MISE A JOUR DU DOSSIER EXISTANT
            |--------------------------------------------------------------------------
            */

            if ($dossier) {

                $requete = $pdo->prepare("
                    UPDATE dossier
                    SET
                        nip = ?,
                        carte_photo = ?,
                        acte_naissance = ?,
                        statut = ?,
                        situation_matrimoniale = ?,
                        personn_a_charge = ?,
                        situation_profesionnelle = ?,
                        activite = ?,
                        type_activite = ?,
                        description_activite = ?
                    WHERE id_candidat = ?
                ");

                $requete->execute([
                    $nip,
                    $carte_photo,
                    $acte_naissance,
                    $statut,
                    $situation_matrimoniale,
                    $personn_a_charge,
                    $situation_profesionnelle,
                    $activite,
                    $type_activite,
                    $description_activite,
                    $id_candidat
                ]);

            } else {

                /*
                |--------------------------------------------------------------------------
                | CREATION DU DOSSIER
                |--------------------------------------------------------------------------
                */

                $requete = $pdo->prepare("
                    INSERT INTO dossier
                    (
                        nip,
                        carte_photo,
                        acte_naissance,
                        statut,
                        id_candidat,
                        id_admin,
                        situation_matrimoniale,
                        personn_a_charge,
                        situation_profesionnelle,
                        activite,
                        type_activite,
                        description_activite
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?
                    )
                ");

                $requete->execute([
                    $nip,
                    $carte_photo,
                    $acte_naissance,
                    $statut,
                    $id_candidat,
                    $situation_matrimoniale,
                    $personn_a_charge,
                    $situation_profesionnelle,
                    $activite,
                    $type_activite,
                    $description_activite
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | MESSAGE DE SUCCES
            |--------------------------------------------------------------------------
            */

            $message =
                "Votre dossier a été enregistré avec succès.";

            $message_type = "succes";


            /*
            |--------------------------------------------------------------------------
            | RECHARGEMENT DU DOSSIER
            |--------------------------------------------------------------------------
            */

            $requete = $pdo->prepare("
                SELECT *
                FROM dossier
                WHERE id_candidat = ?
                LIMIT 1
            ");

            $requete->execute([
                $id_candidat
            ]);

            $dossier =
                $requete->fetch(PDO::FETCH_ASSOC);


       } catch (PDOException $e) {

    $message =
        "ERREUR SQL : " . $e->getMessage();

    $message_type = "erreur";

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

    <title>DGLP - Mon dossier</title>

    <style>

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

            color: #263238;

            line-height: 1.6;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;

            width: 255px;
            height: 100vh;

            background: #123b68;

            color: white;

            padding: 25px 15px;

            box-shadow:
                3px 0 15px rgba(0, 0, 0, 0.12);

            z-index: 1000;

            overflow-y: auto;
        }

        .sidebar-logo {
            text-align: center;
            margin-bottom: 28px;
        }

        .sidebar-logo img {
            width: 105px;
            height: 105px;

            object-fit: contain;

            border-radius: 50%;

            background: white;

            padding: 5px;

            border:
                3px solid
                rgba(255, 255, 255, 0.85);
        }

        .sidebar-title {
            text-align: center;
            margin-top: 12px;
        }

        .sidebar-title h2 {
            font-size: 25px;
            letter-spacing: 1px;
        }

        .sidebar-title p {
            font-size: 13px;
            opacity: 0.85;
            margin-top: 3px;
        }

        .sidebar-menu {
            margin-top: 30px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;

            gap: 12px;

            padding: 13px 15px;

            margin-bottom: 8px;

            border-radius: 8px;

            color: white;

            font-size: 14px;

            transition: 0.25s ease;
        }

        .sidebar-menu a:hover {
            background:
                rgba(255, 255, 255, 0.12);

            transform: translateX(2px);
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

        .main {
            margin-left: 255px;
            min-height: 100vh;
        }

        .top-header {
            height: 82px;

            background:
                rgba(255, 255, 255, 0.97);

            border-bottom:
                1px solid #e1e6ea;

            display: flex;

            align-items: center;

            justify-content:
                space-between;

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
            width: 52px;
            height: 52px;
            object-fit: contain;
        }

        .ministere-text h3 {
            color: #123b68;
            font-size: 15px;
            font-weight: 700;
        }

        .ministere-text p {
            color: #6b747c;
            font-size: 12px;
            margin-top: 2px;
        }

        .profil-candidat {
            display: flex;
            align-items: center;
            gap: 10px;

            color: #123b68;

            font-weight: bold;

            font-size: 14px;
        }

        .avatar {
            width: 40px;
            height: 40px;

            border-radius: 50%;

            background: #123b68;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: bold;
        }

        .content {
            padding: 35px;

            max-width: 1250px;

            margin: auto;
        }

        .page-title {
            margin-bottom: 8px;

            color: #123b68;

            font-size: 30px;
        }

        .page-subtitle {
            color: #6b747c;

            margin-bottom: 28px;

            font-size: 14px;
        }

        .message-succes,
        .message-erreur {
            padding: 14px 18px;

            border-radius: 8px;

            margin-bottom: 22px;

            font-size: 14px;

            font-weight: 600;
        }

        .message-succes {
            background: #e8f7ef;

            color: #08783f;

            border-left:
                4px solid #008c4a;
        }

        .message-erreur {
            background: #fdecec;

            color: #b42318;

            border-left:
                4px solid #d92d20;
        }

        .dossier-form {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 22px;
        }

        .card {
            background:
                rgba(255, 255, 255, 0.97);

            border-radius: 12px;

            padding: 25px;

            box-shadow:
                0 4px 18px
                rgba(0, 0, 0, 0.07);

            border:
                1px solid #e8ecef;
        }

        .card-full {
            grid-column: 1 / -1;
        }

        .card h2 {
            color: #123b68;

            font-size: 19px;

            margin-bottom: 20px;

            padding-bottom: 12px;

            border-bottom:
                2px solid #eef1f3;
        }

        .card h2::before {
            content: "";

            display: inline-block;

            width: 5px;
            height: 20px;

            background: #008c4a;

            border-radius: 3px;

            margin-right: 10px;

            vertical-align: -3px;
        }

        .personal-layout {
            display: flex;

            gap: 28px;

            align-items: flex-start;
        }

        .photo-zone {
            width: 150px;
            min-width: 150px;

            height: 175px;

            border:
                2px dashed #cbd4dc;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            overflow: hidden;

            background: #f8fafb;
        }

        .photo-zone img {
            width: 100%;
            height: 100%;

            object-fit: cover;
        }

        .photo-vide {
            color: #7a858d;

            font-size: 12px;

            text-align: center;

            padding: 10px;
        }

        .personal-info {
            flex: 1;

            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 14px 25px;
        }

        .info-item {
            border-bottom:
                1px solid #edf0f2;

            padding-bottom: 9px;
        }

        .info-label {
            display: block;

            font-size: 12px;

            color: #7a858d;

            margin-bottom: 3px;
        }

        .info-value {
            color: #263238;

            font-size: 14px;

            font-weight: 600;
        }

        .field {
            margin-bottom: 18px;
        }

        .field:last-child {
            margin-bottom: 0;
        }

        label {
            display: block;

            color: #35434d;

            font-weight: 600;

            font-size: 13px;

            margin-bottom: 7px;
        }

        input,
        select,
        textarea {
            width: 100%;

            border:
                1px solid #d4dce2;

            border-radius: 7px;

            padding: 11px 13px;

            font-family:
                Arial, Helvetica, sans-serif;

            font-size: 14px;

            color: #263238;

            background: white;

            outline: none;

            transition: 0.2s ease;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #123b68;

            box-shadow:
                0 0 0 3px
                rgba(18, 59, 104, 0.08);
        }

        textarea {
            resize: vertical;

            min-height: 120px;
        }

        input[type="file"] {
            padding: 9px;

            background: #f8fafb;

            cursor: pointer;
        }

        .file-info {
            color: #008c4a;

            font-size: 12px;

            margin-top: 6px;
        }

        .file-help {
            color: #7a858d;

            font-size: 11px;

            margin-top: 5px;
        }

        .status-zone {
            display: flex;

            align-items: center;

            justify-content:
                space-between;

            gap: 20px;
        }

        .status-info {
            display: flex;

            align-items: center;

            gap: 12px;
        }

        .status-label {
            font-size: 14px;

            font-weight: 600;

            color: #56616a;
        }

        .badge {
            display: inline-flex;

            align-items: center;

            padding: 7px 14px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

            text-transform: uppercase;
        }

        .badge-complet {
            background: #e8f7ef;

            color: #08783f;
        }

        .badge-incomplet {
            background: #fff4df;

            color: #a15c00;
        }

        .btn-enregistrer {
            border: none;

            background: #123b68;

            color: white;

            padding: 12px 22px;

            border-radius: 7px;

            font-size: 14px;

            font-weight: bold;

            cursor: pointer;

            transition: 0.25s ease;
        }

        .btn-enregistrer:hover {
            background: #0d2d50;

            transform: translateY(-1px);

            box-shadow:
                0 4px 10px
                rgba(18, 59, 104, 0.2);
        }

        .footer {
            margin-left: 255px;

            background: #123b68;

            color: white;

            text-align: center;

            padding: 18px;

            font-size: 12px;
        }

        @media (max-width: 1000px) {

            .dossier-form {
                grid-template-columns: 1fr;
            }

            .card-full {
                grid-column: auto;
            }
        }

        @media (max-width: 900px) {

            .sidebar {
                width: 75px;

                padding: 20px 8px;
            }

            .sidebar-title h2 {
                font-size: 17px;
            }

            .sidebar-title p,
            .sidebar-menu span {
                display: none;
            }

            .sidebar-logo img {
                width: 52px;
                height: 52px;
            }

            .sidebar-menu a {
                justify-content: center;

                padding: 13px 5px;
            }

            .menu-icon {
                font-size: 19px;
            }

            .main {
                margin-left: 75px;
            }

            .footer {
                margin-left: 75px;
            }

            .top-header {
                padding: 0 20px;
            }

            .ministere-text {
                display: none;
            }
        }

        @media (max-width: 650px) {

            .content {
                padding: 20px 15px;
            }

            .top-header {
                height: 70px;
            }

            .page-title {
                font-size: 24px;
            }

            .personal-layout {
                flex-direction: column;
            }

            .photo-zone {
                width: 130px;

                min-width: 130px;

                height: 155px;
            }

            .personal-info {
                width: 100%;

                grid-template-columns: 1fr;
            }

            .status-zone {
                align-items: flex-start;

                flex-direction: column;
            }

            .btn-enregistrer {
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

        <div class="sidebar-title">

            <h2>DGLP</h2>

            <p>Espace candidat</p>

        </div>

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


        <a
            href="dossier.php"
            class="active"
        >

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

<div class="main">


    <!-- HEADER -->

    <header class="top-header">

        <div class="ministere">

            <img
                src="../images/ministere.jpg"
                alt="Logo du ministère"
            >

            <div class="ministere-text">

                <h3>
                    Ministère du Commerce, des PME/PMI et de l'Entrepreneuriat
                </h3>

                <p>
                    Direction Générale de la Lutte contre la Pauvreté
                </p>

            </div>

        </div>


        <div class="profil-candidat">

            <div class="avatar">

                <?= strtoupper(
                    substr(
                        $candidat["prenom"],
                        0,
                        1
                    )
                ) ?>

            </div>

            <span>

                <?= htmlspecialchars(
                    $candidat["prenom"]
                ) ?>

                <?= htmlspecialchars(
                    $candidat["nom"]
                ) ?>

            </span>

        </div>

    </header>


    <!-- CONTENU -->

    <main class="content">

        <h1 class="page-title">
            Mon dossier candidat
        </h1>

        <p class="page-subtitle">
            Consultez et complétez vos informations personnelles,
            administratives et socio-économiques.
        </p>


        <?php if (!empty($message)): ?>

            <div class="<?=
                $message_type === "succes"
                    ? "message-succes"
                    : "message-erreur"
            ?>">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <form
            action=""
            method="POST"
            enctype="multipart/form-data"
            class="dossier-form"
        >


            <!-- =================================================
                 INFORMATIONS PERSONNELLES
            ================================================== -->

            <article class="card card-full">

                <h2>
                    Informations personnelles
                </h2>


                <div class="personal-layout">

                    <div class="photo-zone">

                        <?php if (!empty($dossier["carte_photo"])): ?>

                            <img
                                src="../../uploads/dossiers/<?= htmlspecialchars($dossier["carte_photo"]) ?>"
                                alt="Photo du candidat"
                            >

                        <?php else: ?>

                            <p class="photo-vide">
                                Aucune photo enregistrée.
                            </p>

                        <?php endif; ?>

                    </div>


                    <div class="personal-info">

                        <div class="info-item">

                            <span class="info-label">
                                Nom
                            </span>

                            <span class="info-value">
                                <?= htmlspecialchars($candidat["nom"]) ?>
                            </span>

                        </div>


                        <div class="info-item">

                            <span class="info-label">
                                Prénom
                            </span>

                            <span class="info-value">
                                <?= htmlspecialchars($candidat["prenom"]) ?>
                            </span>

                        </div>


                        <div class="info-item">

                            <span class="info-label">
                                Date de naissance
                            </span>

                            <span class="info-value">
                                <?= htmlspecialchars($candidat["date_naissance"]) ?>
                            </span>

                        </div>


                        <div class="info-item">

                            <span class="info-label">
                                Province
                            </span>

                            <span class="info-value">
                                <?= htmlspecialchars($candidat["province"]) ?>
                            </span>

                        </div>


                        <div class="info-item">

                            <span class="info-label">
                                Ville
                            </span>

                            <span class="info-value">
                                <?= htmlspecialchars($candidat["ville"]) ?>
                            </span>

                        </div>


                        <div class="info-item">

                            <span class="info-label">
                                Téléphone
                            </span>

                            <span class="info-value">
                                <?= htmlspecialchars($candidat["telephone"]) ?>
                            </span>

                        </div>


                        <div class="info-item">

                            <span class="info-label">
                                Email
                            </span>

                            <span class="info-value">
                                <?= htmlspecialchars(
                                    $compte["email"] ?? ""
                                ) ?>
                            </span>

                        </div>

                    </div>

                </div>

            </article>


            <!-- =================================================
                 INFORMATIONS ADMINISTRATIVES
            ================================================== -->

            <article class="card">

                <h2>
                    Informations administratives
                </h2>


                <div class="field">

                    <label for="nip">
                        NIP
                    </label>

                    <input
                        type="text"
                        id="nip"
                        name="nip"
                        value="<?= htmlspecialchars(
                            $dossier["nip"] ?? ""
                        ) ?>"
                        placeholder="Entrez votre NIP"
                    >

                </div>


                <div class="field">

                    <label for="carte_photo">
                        Carte / Photo
                    </label>

                    <input
                        type="file"
                        id="carte_photo"
                        name="carte_photo"
                        accept=".jpg,.jpeg,.png"
                    >

                    <p class="file-help">
                        Format JPG ou PNG — 5 Mo maximum.
                    </p>


                    <?php if (!empty($dossier["carte_photo"])): ?>

                        <p class="file-info">
                            ✓ Photo déjà enregistrée.
                        </p>

                    <?php endif; ?>

                </div>


                <div class="field">

                    <label for="acte_naissance">
                        Acte de naissance
                    </label>

                    <input
                        type="file"
                        id="acte_naissance"
                        name="acte_naissance"
                        accept=".pdf,.jpg,.jpeg,.png"
                    >

                    <p class="file-help">
                        Format PDF, JPG ou PNG — 5 Mo maximum.
                    </p>


                    <?php if (!empty($dossier["acte_naissance"])): ?>

                        <p class="file-info">
                            ✓ Acte de naissance déjà enregistré.
                        </p>

                    <?php endif; ?>

                </div>

            </article>


            <!-- =================================================
                 SITUATION SOCIALE
            ================================================== -->

            <article class="card">

                <h2>
                    Situation sociale
                </h2>


                <div class="field">

                    <label for="situation_matrimoniale">
                        Situation matrimoniale
                    </label>

                    <select
                        id="situation_matrimoniale"
                        name="situation_matrimoniale"
                    >

                        <option value="">
                            -- Sélectionner --
                        </option>

                        <option
                            value="celibataire"
                            <?= (
                                ($dossier["situation_matrimoniale"] ?? "")
                                === "celibataire"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            Célibataire
                        </option>

                        <option
                            value="marie"
                            <?= (
                                ($dossier["situation_matrimoniale"] ?? "")
                                === "marie"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            Marié(e)
                        </option>

                        <option
                            value="divorce"
                            <?= (
                                ($dossier["situation_matrimoniale"] ?? "")
                                === "divorce"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            Divorcé(e)
                        </option>

                        <option
                            value="veuf"
                            <?= (
                                ($dossier["situation_matrimoniale"] ?? "")
                                === "veuf"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            Veuf / Veuve
                        </option>

                    </select>

                </div>


                <div class="field">

                    <label for="personnes_charge">
                        Nombre de personnes à charge
                    </label>

                    <input
                        type="number"
                        id="personnes_charge"
                        name="personnes_charge"
                        min="0"
                        value="<?= htmlspecialchars(
                            $dossier["personn_a_charge"] ?? ""
                        ) ?>"
                        placeholder="Exemple : 2"
                    >

                </div>


                <div class="field">

                    <label for="situation_professionnelle">
                        Situation professionnelle
                    </label>

                    <select
                        id="situation_professionnelle"
                        name="situation_professionnelle"
                    >

                        <option value="">
                            -- Sélectionner --
                        </option>

                        <option
                            value="emploi"
                            <?= (
                                ($dossier["situation_profesionnelle"] ?? "")
                                === "emploi"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            En emploi
                        </option>

                        <option
                            value="sans_emploi"
                            <?= (
                                ($dossier["situation_profesionnelle"] ?? "")
                                === "sans_emploi"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            Sans emploi
                        </option>

                        <option
                            value="etudiant"
                            <?= (
                                ($dossier["situation_profesionnelle"] ?? "")
                                === "etudiant"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            Étudiant(e)
                        </option>

                        <option
                            value="independant"
                            <?= (
                                ($dossier["situation_profesionnelle"] ?? "")
                                === "independant"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            Indépendant(e)
                        </option>

                    </select>

                </div>

            </article>


            <!-- =================================================
                 ACTIVITE
            ================================================== -->

            <article class="card">

                <h2>
                    Activité
                </h2>


                <div class="field">

                    <label for="activite_actuelle">
                        Activité actuelle
                    </label>

                    <input
                        type="text"
                        id="activite_actuelle"
                        name="activite_actuelle"
                        value="<?= htmlspecialchars(
                            $dossier["activite"] ?? ""
                        ) ?>"
                        placeholder="Exemple : Commerçant, agriculteur..."
                    >

                </div>


                <div class="field">

                    <label for="type_activite">
                        Type d'activité
                    </label>

                    <select
                        id="type_activite"
                        name="type_activite"
                    >

                        <option value="">
                            -- Sélectionner --
                        </option>

                        <option
                            value="emploi"
                            <?= (
                                ($dossier["type_activite"] ?? "")
                                === "emploi"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            Emploi
                        </option>

                        <option
                            value="commerce"
                            <?= (
                                ($dossier["type_activite"] ?? "")
                                === "commerce"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            Commerce
                        </option>

                        <option
                            value="agriculture"
                            <?= (
                                ($dossier["type_activite"] ?? "")
                                === "agriculture"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            Agriculture
                        </option>

                        <option
                            value="artisanat"
                            <?= (
                                ($dossier["type_activite"] ?? "")
                                === "artisanat"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            Artisanat
                        </option>

                        <option
                            value="autre"
                            <?= (
                                ($dossier["type_activite"] ?? "")
                                === "autre"
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            Autre
                        </option>

                    </select>

                </div>


                <div class="field">

                    <label for="description_activite">
                        Description de l'activité
                    </label>

                    <textarea
                        id="description_activite"
                        name="description_activite"
                        rows="5"
                        placeholder="Décrivez brièvement votre activité..."
                    ><?= htmlspecialchars(
                        $dossier["description_activite"] ?? ""
                    ) ?></textarea>

                </div>

            </article>


            <!-- =================================================
                 ETAT DU DOSSIER
            ================================================== -->

            <article class="card card-full">

                <h2>
                    État du dossier
                </h2>


                <?php

                $statut_affiche =
                    $dossier["statut"] ?? "incomplet";

                ?>


                <div class="status-zone">

                    <div class="status-info">

                        <span class="status-label">
                            Statut actuel :
                        </span>


                        <?php if ($statut_affiche === "complet"): ?>

                            <span class="badge badge-complet">
                                ✓ Dossier complet
                            </span>

                        <?php else: ?>

                            <span class="badge badge-incomplet">
                                ⚠ Dossier incomplet
                            </span>

                        <?php endif; ?>

                    </div>


                    <button
                        type="submit"
                        class="btn-enregistrer"
                    >
                        Enregistrer mon dossier
                    </button>

                </div>

            </article>


        </form>

    </main>

</div>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="footer">

    <p>
        © 2026 Direction Générale de la Lutte contre la Pauvreté
    </p>

</footer>


</body>

</html>