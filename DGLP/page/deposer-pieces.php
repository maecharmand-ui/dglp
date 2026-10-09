<?php

session_start();

require_once "../../database.php";

/* =========================================================
   1. Vérifier que le candidat est connecté
========================================================= */

if (!isset($_SESSION["id_candidat"])) {
    header("Location: connexion.php");
    exit;
}

$id_candidat = (int) $_SESSION["id_candidat"];


/* =========================================================
   2. Vérifier l'identifiant de la candidature
========================================================= */

if (!isset($_GET["id"]) || !ctype_digit($_GET["id"])) {
    header("Location: suivi-candidature.php");
    exit;
}

$id_candidature = (int) $_GET["id"];


/* =========================================================
   3. Récupérer les informations du candidat
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
   4. Récupérer la candidature
      et vérifier son propriétaire
========================================================= */

$requete = $pdo->prepare("
    SELECT
        c.id_candidature,
        c.numero_candidature,
        c.id_offre,
        c.statut,
        c.date_candidature,
        o.nom AS nom_offre,
        o.description AS description_offre
    FROM candidature c
    INNER JOIN offre o
        ON c.id_offre = o.id_offre
    WHERE c.id_candidature = ?
    AND c.id_candidat = ?
    LIMIT 1
");

$requete->execute([
    $id_candidature,
    $id_candidat
]);

$candidature = $requete->fetch(PDO::FETCH_ASSOC);


/* =========================================================
   5. Vérifier que la candidature existe
========================================================= */

if (!$candidature) {
    header("Location: suivi-candidature.php?erreur=candidature_introuvable");
    exit;
}


/* =========================================================
   6. Récupérer les pièces demandées pour cette offre
========================================================= */

$requetePieces = $pdo->prepare("
    SELECT
        id_piece,
        nom_piece,
        obligatoire
    FROM piece_complementaire
    WHERE id_offre = ?
    ORDER BY obligatoire DESC, nom_piece ASC
");

$requetePieces->execute([
    $candidature["id_offre"]
]);

$pieces = $requetePieces->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   7. Traitement des fichiers envoyés
========================================================= */

$messageSucces = null;
$erreur = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
     * Vérifier qu'une pièce a bien été sélectionnée
     */
    if (!isset($_FILES["pieces"])) {

        $erreur = "Aucun fichier n'a été sélectionné.";

    } else {

        /*
         * Dossier de stockage
         */
        $dossierUpload = "../../uploads/pieces/";

        /*
         * Créer le dossier s'il n'existe pas
         */
        if (!is_dir($dossierUpload)) {

            if (!mkdir($dossierUpload, 0755, true)) {
                $erreur =
                    "Impossible de créer le dossier de stockage des fichiers.";
            }
        }


        if ($erreur === null) {

            /*
             * Extensions / types autorisés
             */
            $typesAutorises = [
                "application/pdf",
                "image/jpeg",
                "image/png"
            ];

            /*
             * Taille maximale :
             * 5 Mo par fichier
             */
            $tailleMax = 5 * 1024 * 1024;


            /*
             * Parcourir les pièces envoyées
             */
            foreach (
                $_FILES["pieces"]["name"] as $id_piece => $nom_original
            ) {

                /*
                 * Vérifier que la pièce existe
                 * bien dans la liste des pièces de l'offre
                 */
                $pieceExiste = false;

                foreach ($pieces as $piece) {

                    if (
                        (int) $piece["id_piece"]
                        ===
                        (int) $id_piece
                    ) {
                        $pieceExiste = true;
                        break;
                    }
                }

                if (!$pieceExiste) {
                    continue;
                }


                /*
                 * Vérifier qu'un fichier a été envoyé
                 */
                if (
                    !isset(
                        $_FILES["pieces"]["error"][$id_piece]
                    )
                    ||
                    $_FILES["pieces"]["error"][$id_piece]
                    === UPLOAD_ERR_NO_FILE
                ) {
                    continue;
                }


                /*
                 * Vérifier les erreurs d'upload
                 */
                if (
                    $_FILES["pieces"]["error"][$id_piece]
                    !== UPLOAD_ERR_OK
                ) {

                    $erreur =
                        "Une erreur est survenue lors de l'envoi du fichier : "
                        . htmlspecialchars($nom_original);

                    break;
                }


                /*
                 * Chemin temporaire
                 */
                $fichierTemporaire =
                    $_FILES["pieces"]["tmp_name"][$id_piece];


                /*
                 * Taille du fichier
                 */
                $tailleFichier =
                    (int) $_FILES["pieces"]["size"][$id_piece];


                /*
                 * Vérifier la taille
                 */
                if ($tailleFichier > $tailleMax) {

                    $erreur =
                        "Le fichier « "
                        . htmlspecialchars($nom_original)
                        . " » dépasse la taille maximale de 5 Mo.";

                    break;
                }


                /*
                 * Déterminer le type MIME réel
                 */
                $finfo = new finfo(FILEINFO_MIME_TYPE);

                $typeMime =
                    $finfo->file($fichierTemporaire);


                /*
                 * Vérifier le type MIME
                 */
                if (
                    !in_array(
                        $typeMime,
                        $typesAutorises,
                        true
                    )
                ) {

                    $erreur =
                        "Le fichier « "
                        . htmlspecialchars($nom_original)
                        . " » n'est pas dans un format autorisé. "
                        . "Formats acceptés : PDF, JPG et PNG.";

                    break;
                }


                /*
                 * Déterminer l'extension
                 */
                switch ($typeMime) {

                    case "application/pdf":
                        $extension = "pdf";
                        break;

                    case "image/jpeg":
                        $extension = "jpg";
                        break;

                    case "image/png":
                        $extension = "png";
                        break;

                    default:
                        $extension = null;
                }


                if ($extension === null) {

                    $erreur =
                        "Format de fichier non reconnu.";

                    break;
                }


                /*
                 * Générer un nom sécurisé et unique
                 */
                $nomFichier =
                    "candidature_"
                    . $id_candidature
                    . "_piece_"
                    . (int) $id_piece
                    . "_"
                    . bin2hex(random_bytes(8))
                    . "."
                    . $extension;


                /*
                 * Chemin complet
                 */
                $cheminFichier =
                    $dossierUpload . $nomFichier;


                /*
                 * Déplacer le fichier
                 */
                if (
                    !move_uploaded_file(
                        $fichierTemporaire,
                        $cheminFichier
                    )
                ) {

                    $erreur =
                        "Impossible d'enregistrer le fichier « "
                        . htmlspecialchars($nom_original)
                        . " ».";

                    break;
                }


                /*
                 * Vérifier si cette pièce existe déjà
                 */
                $requeteExistante = $pdo->prepare("
                    SELECT
                        id_piece_candidature,
                        fichier
                    FROM piece_candidature
                    WHERE id_candidature = ?
                    AND id_piece = ?
                    LIMIT 1
                ");

                $requeteExistante->execute([
                    $id_candidature,
                    (int) $id_piece
                ]);

                $pieceExistante =
                    $requeteExistante->fetch(PDO::FETCH_ASSOC);


                /*
                 * Si la pièce existe déjà :
                 * remplacer l'ancien fichier
                 */
                if ($pieceExistante) {

                    /*
                     * Supprimer l'ancien fichier
                     */
                    $ancienFichier =
                        $dossierUpload
                        . $pieceExistante["fichier"];

                    if (
                        is_file($ancienFichier)
                        &&
                        $pieceExistante["fichier"] !== $nomFichier
                    ) {
                        unlink($ancienFichier);
                    }


                    /*
                     * Mettre à jour l'enregistrement
                     */
                    $miseAJour = $pdo->prepare("
                        UPDATE piece_candidature
                        SET
                            fichier = ?,
                            statut = 'Déposé',
                            date_depot = CURRENT_TIMESTAMP
                        WHERE id_piece_candidature = ?
                    ");

                    $miseAJour->execute([
                        $nomFichier,
                        $pieceExistante["id_piece_candidature"]
                    ]);

                } else {

                    /*
                     * Créer un nouvel enregistrement
                     */
                    $insertion = $pdo->prepare("
                        INSERT INTO piece_candidature (
                            id_piece,
                            id_candidature,
                            fichier,
                            statut,
                            date_depot
                        )
                        VALUES (
                            ?,
                            ?,
                            ?,
                            'Déposé',
                            CURRENT_TIMESTAMP
                        )
                    ");

                    $insertion->execute([
                        (int) $id_piece,
                        $id_candidature,
                        $nomFichier
                    ]);
                }
            }


            /*
             * Message si aucune erreur
             */
            if ($erreur === null) {

                $messageSucces =
                    "Les pièces sélectionnées ont été enregistrées avec succès.";
            }
        }
    }
}


/* =========================================================
   8. Récupérer les pièces déjà déposées
========================================================= */

$requeteDeposees = $pdo->prepare("
    SELECT
        pc.id_piece,
        pc.nom_piece,
        pc.obligatoire,
        pca.fichier,
        pca.statut,
        pca.date_depot
    FROM piece_complementaire pc
    LEFT JOIN piece_candidature pca
        ON pc.id_piece = pca.id_piece
        AND pca.id_candidature = ?
    WHERE pc.id_offre = ?
    ORDER BY pc.obligatoire DESC, pc.nom_piece ASC
");

$requeteDeposees->execute([
    $id_candidature,
    $candidature["id_offre"]
]);

$piecesCandidature =
    $requeteDeposees->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   9. Vérifier les pièces obligatoires manquantes
========================================================= */

$piecesObligatoiresManquantes = 0;

foreach ($piecesCandidature as $piece) {

    if (
        (int) $piece["obligatoire"] === 1
        &&
        empty($piece["fichier"])
    ) {
        $piecesObligatoiresManquantes++;
    }
}


/*
 * Toutes les pièces obligatoires sont présentes
 */
$toutesPiecesObligatoires =
    (
        $piecesObligatoiresManquantes === 0
        &&
        count($piecesCandidature) > 0
    );


/* =========================================================
   10. Informations d'affichage
========================================================= */

$prenom = htmlspecialchars(
    $candidat["prenom"] ?? ""
);

$nom = htmlspecialchars(
    $candidat["nom"] ?? ""
);

$initialePrenom = !empty($candidat["prenom"])
    ? strtoupper(substr($candidat["prenom"], 0, 1))
    : "";

$initialeNom = !empty($candidat["nom"])
    ? strtoupper(substr($candidat["nom"], 0, 1))
    : "";

$initiales =
    $initialePrenom . $initialeNom;


/*
 * Statut de la candidature
 */
$statutCandidature =
    strtolower(
        trim(
            $candidature["statut"] ?? ""
        )
    );

$classeStatut = "statut-attente";

if (
    in_array(
        $statutCandidature,
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
    $classeStatut = "statut-valide";
}

if (
    in_array(
        $statutCandidature,
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

    <title>
        DGLP - Pièces justificatives
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

            font-family:
                Arial,
                Helvetica,
                sans-serif;

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

            box-shadow:
                4px 0 15px rgba(0, 0, 0, 0.12);

            z-index: 1000;

            display: flex;

            flex-direction: column;
        }


        .sidebar-header {

            text-align: center;

            padding: 25px 15px 20px;

            border-bottom:
                1px solid
                rgba(255, 255, 255, 0.15);
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

            background:
                rgba(255, 255, 255, 0.12);

            transform:
                translateX(3px);
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

            background:
                rgba(255, 255, 255, 0.96);

            border-bottom:
                1px solid #e3e7eb;

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
           CARTE DE CANDIDATURE
        ===================================================== */

        .candidature-card {

            background: white;

            border-radius: 14px;

            border: 1px solid #e3e7eb;

            box-shadow:
                0 5px 20px rgba(0, 0, 0, 0.07);

            padding: 25px;

            margin-bottom: 22px;
        }


        .candidature-header {

            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            gap: 20px;

            margin-bottom: 20px;
        }


        .candidature-header h3 {

            color: #123b68;

            font-size: 20px;

            margin-bottom: 5px;
        }


        .reference {

            color: #7a858e;

            font-size: 12px;
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


        .statut-attente {

            background: #fff4d6;

            color: #946c00;
        }


        .statut-valide {

            background: #e4f7ed;

            color: #008c4a;
        }


        .statut-rejete {

            background: #fde8e8;

            color: #b42318;
        }


        /* =====================================================
           INFORMATIONS
        ===================================================== */

        .info-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 14px;

            margin-bottom: 20px;
        }


        .info-item {

            background: #f7f9fa;

            padding: 14px;

            border-radius: 8px;
        }


        .info-item label {

            display: block;

            color: #7a858e;

            font-size: 11px;

            margin-bottom: 3px;
        }


        .info-item strong {

            color: #263238;

            font-size: 13px;
        }


        /* =====================================================
           ALERTES
        ===================================================== */

        .message {

            padding: 15px 18px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 13px;
        }


        .message.success {

            background: #eaf7f0;

            color: #12633b;

            border-left:
                4px solid #008c4a;
        }


        .message.erreur {

            background: #fdecec;

            color: #a52727;

            border-left:
                4px solid #c62828;
        }


        .message.info {

            background: #eef5fb;

            color: #34536d;

            border-left:
                4px solid #123b68;
        }


        /* =====================================================
           TITRE DES PIÈCES
        ===================================================== */

        .section-title {

            color: #123b68;

            font-size: 18px;

            margin: 25px 0 15px;

            padding-bottom: 8px;

            border-bottom:
                2px solid #eef1f4;
        }


        /* =====================================================
           FORMULAIRE DES PIÈCES
        ===================================================== */

        .pieces-form {

            display: flex;

            flex-direction: column;

            gap: 15px;
        }


        .piece-card {

            background: white;

            border: 1px solid #e1e6ea;

            border-radius: 11px;

            padding: 20px;

            transition: 0.25s;
        }


        .piece-card:hover {

            border-color: #c7d3de;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.05);
        }


        .piece-top {

            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 15px;

            margin-bottom: 13px;
        }


        .piece-title {

            color: #123b68;

            font-size: 15px;

            font-weight: bold;
        }


        .piece-obligatoire {

            color: #b42318;

            font-size: 12px;

            font-weight: bold;

            margin-left: 5px;
        }


        .piece-facultative {

            color: #7a858e;

            font-size: 11px;

            font-weight: normal;

            margin-left: 5px;
        }


        .piece-status {

            font-size: 11px;

            padding: 5px 9px;

            border-radius: 15px;

            background: #f0f2f4;

            color: #68737d;

            white-space: nowrap;
        }


        .piece-status.depose {

            background: #e4f7ed;

            color: #008c4a;

            font-weight: bold;
        }


        .piece-info {

            font-size: 12px;

            color: #68737d;

            margin-bottom: 12px;
        }


        .piece-fichier {

            background: #f7f9fa;

            border-radius: 7px;

            padding: 9px 11px;

            margin-bottom: 12px;

            font-size: 12px;

            color: #495057;

            word-break: break-word;
        }


        .piece-card input[type="file"] {

            width: 100%;

            padding: 10px;

            background: #fafbfc;

            border: 1px dashed #cbd3da;

            border-radius: 7px;

            font-size: 12px;

            color: #5f6b73;

            cursor: pointer;
        }


        .piece-card input[type="file"]:hover {

            border-color: #123b68;

            background: #f5f8fb;
        }


        /* =====================================================
           BOUTONS
        ===================================================== */

        .actions {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 12px;

            flex-wrap: wrap;

            margin-top: 22px;
        }


        .btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 12px 19px;

            border-radius: 7px;

            border: none;

            font-size: 13px;

            font-weight: bold;

            cursor: pointer;

            transition: 0.25s;
        }


        .btn-primary {

            background: #123b68;

            color: white;
        }


        .btn-primary:hover {

            background: #0d2d50;

            transform: translateY(-1px);
        }


        .btn-success {

            background: #008c4a;

            color: white;
        }


        .btn-success:hover {

            background: #00743e;

            transform: translateY(-1px);
        }


        .btn-light {

            background: #eef2f5;

            color: #123b68;
        }


        .btn-light:hover {

            background: #e1e7ec;
        }


        .btn-disabled {

            background: #dfe4e8;

            color: #7b858d;

            cursor: not-allowed;

            pointer-events: none;
        }


        /* =====================================================
           ÉTAT DES PIÈCES
        ===================================================== */

        .etat-card {

            margin-top: 25px;

            background: #f7f9fa;

            border-radius: 10px;

            padding: 20px;

            border-left:
                4px solid #123b68;
        }


        .etat-card.complete {

            background: #eef8f3;

            border-left-color: #008c4a;
        }


        .etat-card h3 {

            color: #123b68;

            font-size: 16px;

            margin-bottom: 8px;
        }


        .etat-card.complete h3 {

            color: #008c4a;
        }


        .etat-card p {

            color: #68737d;

            font-size: 13px;

            margin-bottom: 8px;
        }


        .etat-card strong {

            color: #263238;
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


            .info-grid {

                grid-template-columns:
                    repeat(2, 1fr);
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


            .candidature-header {

                flex-direction: column;
            }


            .info-grid {

                grid-template-columns: 1fr;
            }


            .piece-top {

                flex-direction: column;
            }


            .piece-status {

                align-self: flex-start;
            }


            .actions {

                flex-direction: column;

                align-items: stretch;
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

        <h2>
            DGLP
        </h2>

        <p>
            Espace candidat
        </p>

    </div>


    <nav class="sidebar-menu">

        <a href="espacecandidat.php">

            <span class="menu-icon">
                ⌂
            </span>

            <span>
                Tableau de bord
            </span>

        </a>


        <a href="offres.php">

            <span class="menu-icon">
                ▣
            </span>

            <span>
                Offres disponibles
            </span>

        </a>


        <a href="dossier.php">

            <span class="menu-icon">
                ▤
            </span>

            <span>
                Mon dossier
            </span>

        </a>


        <a
            href="suivi-candidature.php"
            class="active"
        >

            <span class="menu-icon">
                ✓
            </span>

            <span>
                Mes candidatures
            </span>

        </a>


        <a href="deconnexion.php">

            <span class="menu-icon">
                ↪
            </span>

            <span>
                Déconnexion
            </span>

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
                alt="Logo du Ministère"
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
                Pièces justificatives
            </h2>

            <p>
                Déposez les documents nécessaires à votre candidature.
            </p>

        </div>


        <!-- =============================================
             INFORMATIONS CANDIDATURE
        ============================================== -->

        <div class="candidature-card">

            <div class="candidature-header">

                <div>

                    <h3>
                        <?= htmlspecialchars(
                            $candidature["nom_offre"]
                        ) ?>
                    </h3>

                    <p class="reference">

                        Numéro de candidature :
                        <strong>
                            <?= htmlspecialchars(
                                $candidature["numero_candidature"]
                            ) ?>
                        </strong>

                    </p>

                </div>


                <span class="statut <?= $classeStatut ?>">

                    <?= htmlspecialchars(
                        $candidature["statut"]
                    ) ?>

                </span>

            </div>


            <div class="info-grid">

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

                        <?php
                        if (!empty($candidature["date_candidature"])) {
                            echo date(
                                "d/m/Y",
                                strtotime(
                                    $candidature["date_candidature"]
                                )
                            );
                        } else {
                            echo "-";
                        }
                        ?>

                    </strong>

                </div>


                <div class="info-item">

                    <label>
                        Type de démarche
                    </label>

                    <strong>
                        Candidature en ligne
                    </strong>

                </div>

            </div>


            <?php if (!empty($candidature["description_offre"])): ?>

                <div class="message info">

                    <strong>
                        À propos de l'offre :
                    </strong>

                    <?= nl2br(
                        htmlspecialchars(
                            $candidature["description_offre"]
                        )
                    ) ?>

                </div>

            <?php endif; ?>

        </div>


        <!-- =============================================
             MESSAGES
        ============================================== -->

        <?php if ($messageSucces): ?>

            <div class="message success">

                <strong>
                    Opération réussie :
                </strong>

                <?= htmlspecialchars(
                    $messageSucces
                ) ?>

            </div>

        <?php endif; ?>


        <?php if ($erreur): ?>

            <div class="message erreur">

                <strong>
                    Attention :
                </strong>

                <?= htmlspecialchars(
                    $erreur
                ) ?>

            </div>

        <?php endif; ?>


        <?php if (empty($pieces)): ?>


            <!-- =========================================
                 AUCUNE PIÈCE
            ========================================== -->

            <div class="message info">

                <h3>
                    Aucune pièce complémentaire n'est demandée.
                </h3>

                <p>
                    Cette offre ne possède actuellement
                    aucune pièce justificative configurée.
                </p>

            </div>


        <?php else: ?>


            <!-- =========================================
                 INFORMATIONS UPLOAD
            ========================================== -->

            <div class="message info">

                <strong>
                    Documents à fournir
                </strong>

                <p>
                    Les fichiers doivent être au format
                    PDF, JPG ou PNG.
                </p>

                <p>
                    Taille maximale :
                    <strong>
                        5 Mo par fichier.
                    </strong>
                </p>

                <p>
                    Si un document a déjà été déposé,
                    vous pouvez sélectionner un nouveau
                    fichier pour le remplacer.
                </p>

            </div>


            <!-- =========================================
                 FORMULAIRE
            ========================================== -->

            <form
                method="POST"
                enctype="multipart/form-data"
                class="pieces-form"
            >


                <h3 class="section-title">

                    Documents justificatifs

                </h3>


                <?php foreach (
                    $piecesCandidature as $piece
                ): ?>


                    <div class="piece-card">


                        <div class="piece-top">

                            <div>

                                <div class="piece-title">

                                    <?= htmlspecialchars(
                                        $piece["nom_piece"]
                                    ) ?>


                                    <?php if (
                                        (int) $piece["obligatoire"] === 1
                                    ): ?>

                                        <span class="piece-obligatoire">
                                            * obligatoire
                                        </span>

                                    <?php else: ?>

                                        <span class="piece-facultative">
                                            (facultatif)
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>


                            <?php if (
                                !empty($piece["fichier"])
                            ): ?>

                                <span class="piece-status depose">
                                    ✓ Document déposé
                                </span>

                            <?php else: ?>

                                <span class="piece-status">
                                    Non déposé
                                </span>

                            <?php endif; ?>

                        </div>


                        <?php if (
                            !empty($piece["fichier"])
                        ): ?>


                            <div class="piece-fichier">

                                <strong>
                                    Fichier actuel :
                                </strong>

                                <?= htmlspecialchars(
                                    $piece["fichier"]
                                ) ?>


                                <?php if (
                                    !empty($piece["date_depot"])
                                ): ?>

                                    <br>

                                    <span>
                                        Déposé le
                                        <?= date(
                                            "d/m/Y à H:i",
                                            strtotime(
                                                $piece["date_depot"]
                                            )
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                            </div>


                            <p class="piece-info">

                                Vous pouvez sélectionner
                                un nouveau fichier ci-dessous
                                pour remplacer le document actuel.

                            </p>


                        <?php else: ?>


                            <p class="piece-info">

                                Aucun fichier n'a encore été
                                déposé pour cette pièce.

                            </p>


                        <?php endif; ?>


                        <input
                            type="file"
                            name="pieces[<?= (int) $piece["id_piece"] ?>]"
                            accept=".pdf,.jpg,.jpeg,.png"
                        >

                    </div>


                <?php endforeach; ?>


                <!-- =====================================
                     ACTIONS
                ====================================== -->

                <div class="actions">

                    <a
                        href="suivi-candidature.php"
                        class="btn btn-light"
                    >
                        ← Mes candidatures
                    </a>


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Enregistrer les pièces
                    </button>

                </div>


            </form>


            <!-- =========================================
                 ÉTAT DES PIÈCES
            ========================================== -->

            <div
                class="
                    etat-card
                    <?= $toutesPiecesObligatoires
                        ? 'complete'
                        : ''
                    ?>
                "
            >

                <h3>
                    État des pièces justificatives
                </h3>


                <?php if (
                    $toutesPiecesObligatoires
                ): ?>


                    <p>

                        ✓
                        <strong>
                            Toutes les pièces obligatoires
                            ont été déposées.
                        </strong>

                    </p>


                    <p>

                        Votre dossier est maintenant prêt
                        à être transmis.

                    </p>


                    <div class="actions">

                        <a
                            href="transmettre-candidature.php?id=<?= (int) $id_candidature ?>"
                            class="btn btn-success"
                        >
                            Transmettre ma candidature →
                        </a>

                    </div>


                <?php else: ?>


                    <p>

                        Il reste

                        <strong>
                            <?= $piecesObligatoiresManquantes ?>
                        </strong>

                        pièce(s) obligatoire(s)
                        à déposer.

                    </p>


                    <p>

                        Veuillez déposer toutes les pièces
                        obligatoires avant de transmettre
                        votre candidature.

                    </p>


                <?php endif; ?>

            </div>


        <?php endif; ?>


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