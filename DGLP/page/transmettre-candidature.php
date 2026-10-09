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
    SELECT nom, prenom
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
   ========================================================= */

$requete = $pdo->prepare("
    SELECT
        c.id_candidature,
        c.numero_candidature,
        c.id_offre,
        c.statut,
        c.date_candidature,
        o.description AS nom_offre,
        o.date_limite_candidature,
        o.statut AS statut_offre
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
   6. Vérifier l'état de la candidature
   ========================================================= */

$statutsAutorises = [
    "à compléter",
    "A compléter",
    "en attente"
];

if (
    !in_array(
        $candidature["statut"],
        $statutsAutorises,
        true
    )
) {
    header(
        "Location: accuse-reception.php?id="
        . $id_candidature
    );
    exit;
}


/* =========================================================
   7. Vérifier que l'offre est toujours disponible
   ========================================================= */

if (
    $candidature["statut_offre"] !== "ouverte"
    ||
    $candidature["date_limite_candidature"] < date("Y-m-d")
) {
    header(
        "Location: deposer-pieces.php?id="
        . $id_candidature
        . "&erreur=offre_fermee"
    );
    exit;
}


/* =========================================================
   8. Récupérer les pièces obligatoires
   ========================================================= */

$requetePieces = $pdo->prepare("
    SELECT
        pc.id_piece,
        pc.nom_piece,
        pc.obligatoire,
        pca.id_piece_candidature,
        pca.fichier,
        pca.statut AS statut_piece
    FROM piece_complementaire pc
    LEFT JOIN piece_candidature pca
        ON pc.id_piece = pca.id_piece
        AND pca.id_candidature = ?
    WHERE pc.id_offre = ?
    AND pc.obligatoire = 1
    ORDER BY pc.nom_piece ASC
");

$requetePieces->execute([
    $id_candidature,
    $candidature["id_offre"]
]);

$piecesObligatoires = $requetePieces->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   9. Vérifier les pièces obligatoires manquantes
   ========================================================= */

$piecesManquantes = [];

foreach ($piecesObligatoires as $piece) {

    if (empty($piece["fichier"])) {
        $piecesManquantes[] = $piece["nom_piece"];
    }
}


/* =========================================================
   10. Si des pièces manquent
   ========================================================= */

if (!empty($piecesManquantes)) {

    $listeManquantes = implode(
        ", ",
        $piecesManquantes
    );

    header(
        "Location: deposer-pieces.php?id="
        . $id_candidature
        . "&erreur=pieces_manquantes"
        . "&liste="
        . urlencode($listeManquantes)
    );

    exit;
}


/* =========================================================
   11. Traitement de la transmission
   ========================================================= */

$erreur = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {

        $pdo->beginTransaction();


        /*
         * Vérifier une dernière fois que la candidature
         * n'a pas déjà été transmise.
         */
        $verification = $pdo->prepare("
            SELECT
                statut
            FROM candidature
            WHERE id_candidature = ?
            AND id_candidat = ?
            FOR UPDATE
        ");

        $verification->execute([
            $id_candidature,
            $id_candidat
        ]);

        $candidatureVerifiee =
            $verification->fetch(PDO::FETCH_ASSOC);


        if (!$candidatureVerifiee) {

            throw new Exception(
                "Candidature introuvable."
            );
        }


        /*
         * Vérifier que la candidature n'a pas
         * déjà été transmise.
         */
        $statutsAutorisesTransmission = [
            "à compléter",
            "A compléter",
            "en attente"
        ];

        if (
            !in_array(
                $candidatureVerifiee["statut"],
                $statutsAutorisesTransmission,
                true
            )
        ) {

            throw new Exception(
                "Cette candidature a déjà été transmise."
            );
        }


        /*
         * Vérifier encore les pièces obligatoires
         * avant la transmission.
         */
        $verificationPieces = $pdo->prepare("
            SELECT
                pc.id_piece,
                pc.nom_piece
            FROM piece_complementaire pc
            LEFT JOIN piece_candidature pca
                ON pc.id_piece = pca.id_piece
                AND pca.id_candidature = ?
            WHERE pc.id_offre = ?
            AND pc.obligatoire = 1
            AND (
                pca.fichier IS NULL
                OR pca.fichier = ''
            )
        ");

        $verificationPieces->execute([
            $id_candidature,
            $candidature["id_offre"]
        ]);

        $piecesEncoreManquantes =
            $verificationPieces->fetchAll(PDO::FETCH_ASSOC);


        if (!empty($piecesEncoreManquantes)) {

            throw new Exception(
                "Certaines pièces obligatoires sont manquantes."
            );
        }


        /*
         * Transmission de la candidature.
         *
         * La table candidature ne possède pas de colonne
         * date_accuse_reception.
         *
         * On modifie donc uniquement le statut.
         */
        $miseAJour = $pdo->prepare("
            UPDATE candidature
            SET statut = 'Soumise'
            WHERE id_candidature = ?
            AND id_candidat = ?
        ");

        $miseAJour->execute([
            $id_candidature,
            $id_candidat
        ]);


        /*
         * Vérifier que la mise à jour a bien été effectuée.
         */
        if ($miseAJour->rowCount() === 0) {

            throw new Exception(
                "La candidature n'a pas pu être transmise."
            );
        }


        /*
         * Valider la transaction.
         */
        $pdo->commit();


        /*
         * Rediriger vers l'accusé de réception.
         */
        header(
            "Location: accuse-reception.php?id="
            . $id_candidature
        );

        exit;

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $erreur =
            "Impossible de transmettre votre candidature. "
            . "Veuillez réessayer.";
    }
}


/* =========================================================
   12. Vérifier si aucune pièce obligatoire n'est demandée
   ========================================================= */

$aucunePieceObligatoire =
    empty($piecesObligatoires);

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
        Transmission de la candidature - DGLP
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        /* =====================================================
           BASE
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
            color: #263238;
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
            left: 0;
            top: 0;
            width: 255px;
            height: 100vh;
            background: #123b68;
            color: #fff;
            padding: 25px 15px;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.12);
            z-index: 1000;
        }

        .sidebar-logo {
            text-align: center;
            margin-bottom: 20px;
        }

        .sidebar-logo img {
            width: 105px;
            height: 105px;
            object-fit: cover;
            border-radius: 50%;
            background: #fff;
            padding: 5px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.20);
        }

        .sidebar-title {
            text-align: center;
            margin-bottom: 30px;
        }

        .sidebar-title h2 {
            font-size: 25px;
            margin-bottom: 3px;
            color: #fff;
        }

        .sidebar-title p {
            font-size: 13px;
            color: #dbe7f3;
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 15px;
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            transition: 0.25s ease;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        .sidebar-menu a.active {
            background: #fff;
            color: #123b68;
            font-weight: bold;
        }

        .menu-icon {
            width: 22px;
            text-align: center;
            font-size: 17px;
        }


        /* =====================================================
           CONTENU PRINCIPAL
        ===================================================== */

        .main-content {
            margin-left: 255px;
            min-height: 100vh;
        }


        /* =====================================================
           HEADER
        ===================================================== */

        .top-header {
            height: 82px;
            background: rgba(255, 255, 255, 0.97);
            border-bottom: 1px solid #e2e8ee;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 35px;
            position: sticky;
            top: 0;
            z-index: 900;
        }

        .ministry {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .ministry img {
            width: 55px;
            height: 55px;
            object-fit: cover;
            border-radius: 8px;
        }

        .ministry-text h1 {
            font-size: 17px;
            color: #123b68;
            margin-bottom: 2px;
        }

        .ministry-text p {
            font-size: 12px;
            color: #697586;
        }

        .candidate-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #123b68;
            font-weight: bold;
        }

        .candidate-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #123b68;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }


        /* =====================================================
           CONTENU
        ===================================================== */

        .page-content {
            max-width: 1050px;
            margin: 0 auto;
            padding: 40px 35px 60px;
        }

        .page-title {
            margin-bottom: 30px;
        }

        .page-title h2 {
            color: #123b68;
            font-size: 28px;
            margin-bottom: 7px;
        }

        .page-title p {
            color: #667085;
            font-size: 14px;
        }


        /* =====================================================
           CARTE CANDIDATURE
        ===================================================== */

        .candidature-summary {
            background: #fff;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 5px 18px rgba(18, 59, 104, 0.08);
            border-left: 5px solid #123b68;
            margin-bottom: 25px;
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
            font-size: 21px;
            margin-bottom: 8px;
        }

        .reference {
            font-size: 13px;
            color: #667085;
        }

        .reference strong {
            color: #344054;
        }


        /* =====================================================
           STATUT
        ===================================================== */

        .statut {
            display: inline-block;
            padding: 7px 14px;
            border-radius: 20px;
            background: #fff3cd;
            color: #856404;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }


        /* =====================================================
           INFORMATIONS
        ===================================================== */

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .info-box {
            background: #f7f9fb;
            border: 1px solid #e6ebf0;
            border-radius: 10px;
            padding: 15px;
        }

        .info-box span {
            display: block;
            font-size: 12px;
            color: #667085;
            margin-bottom: 4px;
        }

        .info-box strong {
            color: #263238;
            font-size: 14px;
        }


        /* =====================================================
           MESSAGE
        ===================================================== */

        .message-box {
            background: #fff;
            border-radius: 14px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 18px rgba(18, 59, 104, 0.07);
            border-left: 5px solid #008c4a;
        }

        .message-box.warning {
            border-left-color: #d99a00;
        }

        .message-box h3 {
            color: #123b68;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .message-box p {
            color: #667085;
            font-size: 14px;
        }

        .message-box .check {
            color: #008c4a;
            font-size: 20px;
            margin-right: 7px;
        }


        /* =====================================================
           ERREUR
        ===================================================== */

        .error-box {
            background: #fff1f0;
            border: 1px solid #f3c2bd;
            border-left: 5px solid #c62828;
            color: #a61b1b;
            border-radius: 10px;
            padding: 15px 18px;
            margin-bottom: 25px;
            font-size: 14px;
        }


        /* =====================================================
           CONFIRMATION
        ===================================================== */

        .confirmation-card {
            background: #fff;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 5px 18px rgba(18, 59, 104, 0.08);
        }

        .confirmation-card h3 {
            color: #123b68;
            font-size: 20px;
            margin-bottom: 15px;
        }

        .confirmation-card p {
            color: #667085;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .confirmation-note {
            background: #eef5fb;
            border-radius: 9px;
            padding: 15px;
            margin: 20px 0 25px;
            color: #123b68;
            font-size: 13px;
            border: 1px solid #d7e5f2;
        }


        /* =====================================================
           BOUTONS
        ===================================================== */

        .actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .btn-primary,
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.25s ease;
            border: none;
        }

        .btn-primary {
            background: #008c4a;
            color: #fff;
        }

        .btn-primary:hover {
            background: #00753e;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #eef2f6;
            color: #123b68;
            border: 1px solid #d9e1e8;
        }

        .btn-secondary:hover {
            background: #e2e9f0;
        }


        /* =====================================================
           FOOTER
        ===================================================== */

        .footer {
            background: #123b68;
            color: #fff;
            margin-top: 30px;
            padding: 20px 35px;
            text-align: center;
            font-size: 13px;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 900px) {

            .sidebar {
                width: 75px;
                padding: 20px 8px;
            }

            .sidebar-logo img {
                width: 55px;
                height: 55px;
            }

            .sidebar-title h2 {
                font-size: 17px;
            }

            .sidebar-title p,
            .sidebar-menu span {
                display: none;
            }

            .sidebar-menu a {
                justify-content: center;
                padding: 13px 5px;
            }

            .menu-icon {
                font-size: 19px;
            }

            .main-content {
                margin-left: 75px;
            }

            .top-header {
                padding: 0 20px;
            }

            .ministry-text {
                display: none;
            }

            .page-content {
                padding: 30px 20px 50px;
            }
        }


        @media (max-width: 650px) {

            .top-header {
                height: 70px;
            }

            .ministry img {
                width: 45px;
                height: 45px;
            }

            .candidate-profile {
                font-size: 12px;
            }

            .candidate-avatar {
                width: 35px;
                height: 35px;
            }

            .page-title h2 {
                font-size: 23px;
            }

            .candidature-header {
                flex-direction: column;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .confirmation-card,
            .candidature-summary,
            .message-box {
                padding: 20px;
            }

            .btn-primary,
            .btn-secondary {
                width: 100%;
            }

            .footer {
                padding: 18px 15px;
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



<!-- =========================================================
     CONTENU PRINCIPAL
========================================================= -->

<div class="main-content">


    <!-- HEADER -->

    <header class="top-header">

        <div class="ministry">

            <img
                src="../images/ministere.jpg"
                alt="Ministère"
            >

            <div class="ministry-text">

                <h1>
                    Ministère du Commerce,
                    des PME/PMI et de l'Entrepreneuriat
                </h1>

                <p>
                    Direction Générale de la Lutte contre la Pauvreté
                </p>

            </div>

        </div>


        <div class="candidate-profile">

            <div class="candidate-avatar">

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

    <main class="page-content">


        <div class="page-title">

            <h2>
                Transmission de ma candidature
            </h2>

            <p>
                Vérifiez les informations de votre candidature
                avant sa transmission définitive à la DGLP.
            </p>

        </div>



        <!-- MESSAGE D'ERREUR -->

        <?php if ($erreur): ?>

            <div class="error-box">

                ⚠️
                <?= htmlspecialchars($erreur) ?>

            </div>

        <?php endif; ?>



        <!-- INFORMATIONS CANDIDATURE -->

        <section class="candidature-summary">

            <div class="candidature-header">

                <div>

                    <h3>
                        <?= htmlspecialchars(
                            $candidature["nom_offre"]
                        ) ?>
                    </h3>

                    <p class="reference">

                        <strong>
                            Numéro de candidature :
                        </strong>

                        <?= htmlspecialchars(
                            $candidature["numero_candidature"]
                        ) ?>

                    </p>

                </div>


                <span class="statut">

                    <?= htmlspecialchars(
                        $candidature["statut"]
                    ) ?>

                </span>

            </div>



            <div class="info-grid">

                <div class="info-box">

                    <span>
                        Date de candidature
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $candidature["date_candidature"]
                        ) ?>
                    </strong>

                </div>


                <div class="info-box">

                    <span>
                        Date limite de l'offre
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $candidature["date_limite_candidature"]
                        ) ?>
                    </strong>

                </div>

            </div>

        </section>



        <!-- VÉRIFICATION DES PIÈCES -->

        <?php if ($aucunePieceObligatoire): ?>

            <section class="message-box warning">

                <h3>
                    📄 Aucune pièce obligatoire
                </h3>

                <p>
                    Cette offre ne comporte aucune pièce
                    justificative obligatoire à déposer.
                </p>

            </section>

        <?php else: ?>

            <section class="message-box">

                <h3>
                    <span class="check">✓</span>
                    Vérification des pièces
                </h3>

                <p>
                    Toutes les pièces justificatives
                    obligatoires ont été déposées.
                    Votre candidature peut maintenant
                    être transmise.
                </p>

            </section>

        <?php endif; ?>



        <!-- CONFIRMATION -->

        <section class="confirmation-card">

            <h3>
                Confirmation de transmission
            </h3>

            <p>
                En cliquant sur le bouton
                <strong>« Transmettre ma candidature »</strong>,
                vous confirmez que les informations fournies
                et les pièces justificatives déposées sont exactes.
            </p>

            <p>
                Votre candidature sera alors définitivement
                transmise à la Direction Générale de la Lutte
                contre la Pauvreté.
            </p>


            <div class="confirmation-note">

                🔒
                <strong>Important :</strong>
                après transmission, vous ne pourrez plus modifier
                cette candidature depuis cet espace.
                Un accusé de réception sera généré après
                la transmission.

            </div>


            <form method="POST">

                <div class="actions">

                    <button
                        type="submit"
                        class="btn-primary"
                    >
                        ✓ Transmettre ma candidature
                    </button>


                    <a
                        href="deposer-pieces.php?id=<?= $id_candidature ?>"
                        class="btn-secondary"
                    >
                        ← Retour aux pièces
                    </a>

                </div>

            </form>

        </section>

    </main>



    <!-- FOOTER -->

    <footer class="footer">

        © 2026 Direction Générale de la Lutte
        contre la Pauvreté — Tous droits réservés.

    </footer>


</div>


</body>

</html>