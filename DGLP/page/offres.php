<?php

session_start();
require_once "../../database.php";


/*
|--------------------------------------------------------------------------
| RÉCUPÉRER LES OFFRES OUVERTES ET ENCORE VALIDES
|--------------------------------------------------------------------------
*/

$requete = $pdo->query("
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
    WHERE statut = 'ouverte'
    AND date_limite_candidature >= CURDATE()
    ORDER BY date_creation DESC
");

$offres = $requete->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>DGLP - Offres de services</title>


    <style>

        /* =========================================================
           RESET
        ========================================================= */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        /* =========================================================
           BODY
        ========================================================= */

        body {

            font-family: Arial, sans-serif;

            color: #3e362c;

            line-height: 1.6;

            background:
                linear-gradient(
                    rgba(244, 246, 248, 0.94),
                    rgba(244, 246, 248, 0.94)
                ),
                url("../images/dglp.jpg")
                center center /
                420px auto
                no-repeat fixed;

            background-color: #f4f6f8;
        }


        a {
            text-decoration: none;
            color: inherit;
        }


        /* =========================================================
           SIDEBAR
        ========================================================= */

        .sidebar {

            position: fixed;

            top: 0;
            left: 0;

            width: 255px;
            height: 100vh;

            background: #123b68;

            color: white;

            box-shadow:
                3px 0 15px
                rgba(0, 0, 0, 0.15);

            z-index: 1000;

            display: flex;

            flex-direction: column;

            overflow-y: auto;
        }


        /* LOGO */

        .sidebar-logo {

            text-align: center;

            padding: 25px 15px 15px;
        }


        .sidebar-logo img {

            width: 105px;
            height: 105px;

            object-fit: cover;

            border-radius: 50%;

            border: 4px solid white;

            background: white;

            box-shadow:
                0 4px 12px
                rgba(0, 0, 0, 0.20);
        }


        /* TITRE */

        .sidebar-title {

            text-align: center;

            padding: 0 15px 25px;

            border-bottom:
                1px solid
                rgba(255, 255, 255, 0.20);
        }


        .sidebar-title h2 {

            font-size: 24px;

            letter-spacing: 1px;

            margin-bottom: 3px;
        }


        .sidebar-title p {

            font-size: 13px;

            opacity: 0.85;
        }


        /* MENU */

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

            border-radius: 7px;

            font-size: 14px;

            transition: 0.3s;
        }


        .sidebar-menu a:hover {

            background:
                rgba(255, 255, 255, 0.13);

            transform: translateX(3px);
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


        /* =========================================================
           CONTENU PRINCIPAL
        ========================================================= */

        .main-content {

            margin-left: 255px;

            min-height: 100vh;

            display: flex;

            flex-direction: column;
        }


        /* =========================================================
           HEADER
        ========================================================= */

        .top-header {

            height: 85px;

            background:
                rgba(255, 255, 255, 0.96);

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 30px;

            border-bottom:
                1px solid #e3e6e9;

            box-shadow:
                0 2px 8px
                rgba(0, 0, 0, 0.06);

            position: sticky;

            top: 0;

            z-index: 500;
        }


        .ministry-info {

            display: flex;

            align-items: center;

            gap: 15px;
        }


        .ministry-info img {

            width: 55px;
            height: 55px;

            object-fit: contain;
        }


        .ministry-text h3 {

            color: #123b68;

            font-size: 17px;

            margin-bottom: 2px;
        }


        .ministry-text p {

            color: #666;

            font-size: 12px;
        }


        /* =========================================================
           PROFIL CANDIDAT
        ========================================================= */

        .candidate-profile {

            display: flex;

            align-items: center;

            gap: 10px;

            color: #123b68;

            font-weight: bold;

            font-size: 14px;
        }


        .candidate-avatar {

            width: 38px;
            height: 38px;

            border-radius: 50%;

            background: #123b68;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: bold;

            font-size: 16px;
        }


        /* =========================================================
           CONTENU
        ========================================================= */

        .content {

            padding: 30px;

            flex: 1;
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

            color: #666;

            font-size: 14px;
        }


        /* =========================================================
           LISTE DES OFFRES
        ========================================================= */

        .offres-container {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 22px;
        }


        /* =========================================================
           CARTE OFFRE
        ========================================================= */

        .offre-card {

            background: white;

            border-radius: 10px;

            padding: 24px;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, 0.08);

            border-left:
                5px solid #123b68;

            transition: 0.3s;

            display: flex;

            flex-direction: column;
        }


        .offre-card:hover {

            transform: translateY(-4px);

            box-shadow:
                0 7px 20px
                rgba(0, 0, 0, 0.13);
        }


        .offre-card h2 {

            color: #123b68;

            font-size: 20px;

            margin-bottom: 5px;
        }


        .offre-reference {

            display: inline-block;

            color: #777;

            font-size: 12px;

            margin-bottom: 18px;
        }


        .offre-card p {

            color: #555;

            font-size: 13px;

            margin-bottom: 11px;
        }


        .offre-card p strong {

            color: #123b68;
        }


        .offre-description {

            background: #f7f9fb;

            padding: 12px;

            border-radius: 6px;

            margin-bottom: 14px;
        }


        .offre-informations {

            margin-top: 5px;
        }


        /* =========================================================
           DOCUMENT SUPPLÉMENTAIRE
        ========================================================= */

        .attention {

            background: #fff3cd;

            color: #856404 !important;

            border-left:
                4px solid #d28b00;

            padding: 10px 12px;

            border-radius: 5px;

            font-weight: bold;

            margin-top: 5px;
        }


        /* =========================================================
           ACTIONS
        ========================================================= */

        .offre-actions {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

            margin-top: auto;

            padding-top: 18px;

            border-top:
                1px solid #eeeeee;
        }


        .btn {

            display: inline-block;

            padding: 10px 16px;

            border-radius: 6px;

            font-size: 13px;

            font-weight: bold;

            transition: 0.3s;

            text-align: center;
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

            background: #006f3b;

            transform: translateY(-1px);
        }


        /* =========================================================
           AUCUNE OFFRE
        ========================================================= */

        .empty-state {

            background: white;

            border-radius: 10px;

            padding: 50px 30px;

            text-align: center;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, 0.08);
        }


        .empty-state-icon {

            font-size: 40px;

            margin-bottom: 12px;
        }


        .empty-state h2 {

            color: #123b68;

            font-size: 20px;

            margin-bottom: 8px;
        }


        .empty-state p {

            color: #777;

            font-size: 14px;

            margin-bottom: 20px;
        }


        /* =========================================================
           FOOTER
        ========================================================= */

        .footer {

            background: #123b68;

            color: white;

            text-align: center;

            padding: 15px;

            font-size: 12px;

            margin-top: auto;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1100px) {

            .offres-container {

                grid-template-columns: 1fr;
            }
        }


        @media (max-width: 900px) {

            .sidebar {

                width: 75px;
            }


            .sidebar-logo img {

                width: 50px;
                height: 50px;
            }


            .sidebar-title h2 {

                font-size: 14px;
            }


            .sidebar-title p {

                display: none;
            }


            .sidebar-menu a {

                justify-content: center;

                padding: 13px 5px;
            }


            .sidebar-menu a span:not(.menu-icon) {

                display: none;
            }


            .main-content {

                margin-left: 75px;
            }


            .top-header {

                padding: 0 18px;
            }


            .ministry-text {

                display: none;
            }


            .candidate-profile span {

                display: none;
            }
        }


        @media (max-width: 700px) {

            .content {

                padding: 20px 15px;
            }


            .top-header {

                height: 70px;
            }


            .page-title h1 {

                font-size: 23px;
            }


            .offre-card {

                padding: 20px;
            }


            .offre-actions {

                flex-direction: column;
            }


            .offre-actions .btn {

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


        <a
            href="offres.php"
            class="active"
        >

            <span class="menu-icon">▣</span>

            <span>Offres disponibles</span>

        </a>


        <a href="dossier.php">

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

<div class="main-content">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="top-header">


        <div class="ministry-info">


            <img
                src="../images/ministere.jpg"
                alt="Ministère"
            >


            <div class="ministry-text">

                <h3>
                    Ministère du Commerce,
                    des PME/PMI et de l'Entrepreneuriat
                </h3>

                <p>
                    Direction Générale de la Lutte contre la Pauvreté
                </p>

            </div>


        </div>


        <?php if (isset($_SESSION["id_candidat"])): ?>


            <div class="candidate-profile">


                <div class="candidate-avatar">

                    <?php

                    /*
                    |----------------------------------------------------------
                    | Récupérer les initiales si le candidat est connecté
                    |----------------------------------------------------------
                    */

                    try {

                        $requete_candidat = $pdo->prepare(
                            "SELECT prenom, nom
                             FROM candidat
                             WHERE id_candidat = ?"
                        );

                        $requete_candidat->execute([
                            $_SESSION["id_candidat"]
                        ]);

                        $profil = $requete_candidat->fetch(
                            PDO::FETCH_ASSOC
                        );

                        if ($profil) {

                            echo strtoupper(
                                substr(
                                    $profil["prenom"],
                                    0,
                                    1
                                )
                            );
                        } else {

                            echo "C";
                        }

                    } catch (PDOException $e) {

                        echo "C";
                    }

                    ?>

                </div>


                <span>

                    <?php

                    if (isset($profil)) {

                        echo htmlspecialchars(
                            $profil["prenom"]
                        );

                        echo " ";

                        echo htmlspecialchars(
                            $profil["nom"]
                        );

                    } else {

                        echo "Candidat";
                    }

                    ?>

                </span>


            </div>


        <?php else: ?>


            <a
                href="connexion.php"
                class="btn btn-primary"
            >

                Connexion

            </a>


        <?php endif; ?>


    </header>


    <!-- =====================================================
         CONTENU
    ====================================================== -->

    <main class="content">


        <!-- TITRE -->

        <section class="page-title">

            <h1>
                Offres de services disponibles
            </h1>

            <p>

                Découvrez les offres actuellement proposées
                par la Direction Générale de la Lutte contre la Pauvreté.

            </p>

        </section>


        <!-- =================================================
             OFFRES
        ================================================== -->

        <?php if (empty($offres)): ?>


            <section class="empty-state">


                <div class="empty-state-icon">
                    📋
                </div>


                <h2>
                    Aucune offre disponible
                </h2>


                <p>

                    Il n'y a actuellement aucune offre ouverte
                    à la candidature.

                </p>


                <a
                    href="espacecandidat.php"
                    class="btn btn-primary"
                >

                    Retour au tableau de bord

                </a>


            </section>


        <?php else: ?>


            <section class="offres-container">


                <?php foreach ($offres as $offre): ?>


                    <article class="offre-card">


                        <!-- NOM DE L'OFFRE -->

                        <h2>

                            <?= htmlspecialchars(
                                $offre["nom"]
                            ) ?>

                        </h2>


                        <!-- RÉFÉRENCE -->

                        <span class="offre-reference">

                            Référence :
                            <strong>

                                OFFRE-
                                <?= htmlspecialchars(
                                    $offre["id_offre"]
                                ) ?>

                            </strong>

                        </span>


                        <!-- DESCRIPTION -->

                        <div class="offre-description">

                            <p>

                                <strong>
                                    Description :
                                </strong>

                            </p>


                            <p>

                                <?= nl2br(
                                    htmlspecialchars(
                                        $offre["description"]
                                    )
                                ) ?>

                            </p>

                        </div>


                        <!-- INFORMATIONS -->

                        <div class="offre-informations">


                            <!-- DATE LIMITE -->

                            <p>

                                <strong>
                                    Date limite de candidature :
                                </strong>

                                <?= htmlspecialchars(
                                    $offre[
                                        "date_limite_candidature"
                                    ]
                                ) ?>

                            </p>


                            <!-- NOMBRE DE PLACES -->

                            <p>

                                <strong>
                                    Nombre de places :
                                </strong>

                                <?= htmlspecialchars(
                                    $offre["nombre_place"]
                                ) ?>

                            </p>


                            <!-- CRITÈRES -->

                            <?php if (
                                !empty($offre["critere"])
                            ): ?>

                                <p>

                                    <strong>
                                        Critères :
                                    </strong>

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $offre["critere"]
                                        )
                                    ) ?>

                                </p>

                            <?php endif; ?>


                            <!-- MONTANT -->

                            <?php if (
                                $offre["montant"] !== null
                                &&
                                $offre["montant"] !== ''
                            ): ?>

                                <p>

                                    <strong>
                                        Montant :
                                    </strong>

                                    <?= number_format(
                                        (float)
                                        $offre["montant"],
                                        0,
                                        ',',
                                        ' '
                                    ) ?>

                                    FCFA

                                </p>

                            <?php endif; ?>


                            <!-- DOCUMENT SUPPLÉMENTAIRE -->

                            <?php if (
                                (int)
                                $offre[
                                    "document_supplementaire"
                                ] === 1
                            ): ?>

                                <p class="attention">

                                    Document supplémentaire requis.

                                </p>

                            <?php endif; ?>


                        </div>


                        <!-- ACTIONS -->

                        <div class="offre-actions">


                            <a
                                href="detaille_offre.php?id=<?= urlencode(
                                    $offre["id_offre"]
                                ) ?>"
                                class="btn btn-primary"
                            >

                                Voir les détails

                            </a>


                            <?php if (
                                isset(
                                    $_SESSION["id_candidat"]
                                )
                            ): ?>


                                <a
                                    href="postuler.php?id=<?= urlencode(
                                        $offre["id_offre"]
                                    ) ?>"
                                    class="btn btn-success"
                                >

                                    Postuler

                                </a>


                            <?php else: ?>


                                <a
                                    href="connexion.php"
                                    class="btn btn-primary"
                                >

                                    Se connecter pour postuler

                                </a>


                            <?php endif; ?>


                        </div>


                    </article>


                <?php endforeach; ?>


            </section>


        <?php endif; ?>


    </main>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <footer class="footer">

        <p>

            © 2026 Direction Générale de la Lutte contre la Pauvreté

        </p>

    </footer>


</div>


</body>

</html>