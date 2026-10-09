<?php

session_start();

require_once "../../database.php";


/* =========================================================
   VÉRIFIER SI L'ADMINISTRATEUR EST DÉJÀ CONNECTÉ
   ========================================================= */

if (isset($_SESSION["id_admin"])) {
    header("Location: dashboard.php");
    exit;
}


$erreur = "";


/* =========================================================
   TRAITEMENT DE LA CONNEXION
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $mot_de_passe = $_POST["mot_de_passe"] ?? "";


    if ($email === "" || $mot_de_passe === "") {

        $erreur = "Veuillez remplir tous les champs.";

    } else {

        $requete = $pdo->prepare("
            SELECT
                id_admin,
                nom,
                prenom,
                email,
                mot_de_passe,
                role
            FROM admin
            WHERE email = ?
        ");

        $requete->execute([$email]);

        $admin = $requete->fetch(PDO::FETCH_ASSOC);


        if (
            $admin &&
            password_verify(
                $mot_de_passe,
                $admin["mot_de_passe"]
            )
        ) {

            session_regenerate_id(true);

            $_SESSION["id_admin"] = $admin["id_admin"];
            $_SESSION["nom_admin"] = $admin["nom"];
            $_SESSION["prenom_admin"] = $admin["prenom"];
            $_SESSION["email_admin"] = $admin["email"];
            $_SESSION["role_admin"] = $admin["role"];

            header("Location: offres.php");
            exit;

        } else {

            $erreur = "Email ou mot de passe incorrect.";
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

    <title>
        Connexion administration - DGLP
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


        /* =====================================================
           BODY
        ===================================================== */

        body {

            min-height: 100vh;

            font-family: Arial, sans-serif;

            background:
                linear-gradient(
                    rgba(18, 59, 104, 0.88),
                    rgba(13, 45, 80, 0.94)
                ),
                url("../images/dglp.jpg")
                center center / cover no-repeat fixed;

            display: flex;
            flex-direction: column;

            color: #263746;
        }


        /* =====================================================
           HEADER
        ===================================================== */

        header {

            height: 82px;

            background: white;

            display: flex;
            align-items: center;

            padding: 0 45px;

            box-shadow:
                0 2px 12px
                rgba(0, 0, 0, 0.15);

            position: relative;
            z-index: 2;
        }


        .header-content {

            width: 100%;
            max-width: 1250px;

            margin: auto;

            display: flex;
            align-items: center;
            justify-content: space-between;
        }


        /* =====================================================
           LOGO
        ===================================================== */

        .brand {

            display: flex;
            align-items: center;
            gap: 14px;
        }


        .brand img {

            width: 55px;
            height: 55px;

            object-fit: cover;

            border-radius: 50%;

            border: 3px solid #123b68;

            background: white;
        }


        .brand-text h2 {

            color: #123b68;

            font-size: 20px;

            margin-bottom: 2px;
        }


        .brand-text p {

            color: #6e7b87;

            font-size: 12px;
        }


        .secure-label {

            color: #123b68;

            font-size: 13px;

            font-weight: bold;

            display: flex;

            align-items: center;

            gap: 7px;
        }


        .secure-icon {

            width: 28px;
            height: 28px;

            border-radius: 50%;

            background: #eaf1f8;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 14px;
        }


        /* =====================================================
           MAIN
        ===================================================== */

        main {

            flex: 1;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 45px 20px;
        }


        /* =====================================================
           CONTENEUR CONNEXION
        ===================================================== */

        .connexion-container {

            width: 100%;

            max-width: 950px;

            display: grid;

            grid-template-columns: 1fr 1fr;

            background: white;

            border-radius: 16px;

            overflow: hidden;

            box-shadow:
                0 20px 55px
                rgba(0, 0, 0, 0.25);
        }


        /* =====================================================
           PARTIE GAUCHE
        ===================================================== */

        .connexion-info {

            background: #123b68;

            color: white;

            padding: 45px 40px;

            display: flex;

            flex-direction: column;

            justify-content: center;
        }


        .connexion-info img {

            width: 105px;
            height: 105px;

            object-fit: cover;

            border-radius: 50%;

            background: white;

            padding: 5px;

            margin-bottom: 25px;
        }


        .connexion-info h1 {

            font-size: 29px;

            line-height: 1.25;

            margin-bottom: 15px;
        }


        .connexion-info h1 span {

            color: #61c993;
        }


        .connexion-info p {

            color: rgba(255, 255, 255, 0.82);

            font-size: 14px;

            line-height: 1.8;

            max-width: 390px;

            margin-bottom: 25px;
        }


        .info-line {

            display: flex;

            align-items: center;

            gap: 10px;

            font-size: 13px;

            color: rgba(255, 255, 255, 0.88);

            margin-bottom: 10px;
        }


        .info-dot {

            width: 8px;
            height: 8px;

            border-radius: 50%;

            background: #008c4a;

            flex-shrink: 0;
        }


        /* =====================================================
           PARTIE FORMULAIRE
        ===================================================== */

        .connexion {

            padding: 45px 40px;

            display: flex;

            flex-direction: column;

            justify-content: center;
        }


        .connexion-header {

            margin-bottom: 28px;
        }


        .connexion-header h2 {

            color: #123b68;

            font-size: 25px;

            margin-bottom: 7px;
        }


        .connexion-header p {

            color: #7a8791;

            font-size: 13px;
        }


        /* =====================================================
           MESSAGE ERREUR
        ===================================================== */

        .message-erreur {

            background: #fff0f0;

            color: #a12828;

            border: 1px solid #f0c7c7;

            border-left: 4px solid #c93636;

            border-radius: 7px;

            padding: 12px 14px;

            margin-bottom: 20px;

            font-size: 13px;

            line-height: 1.5;
        }


        /* =====================================================
           CHAMPS
        ===================================================== */

        .champ {

            margin-bottom: 19px;
        }


        .champ label {

            display: block;

            color: #344654;

            font-size: 13px;

            font-weight: bold;

            margin-bottom: 7px;
        }


        .input-wrapper {

            position: relative;
        }


        .input-icon {

            position: absolute;

            left: 13px;

            top: 50%;

            transform: translateY(-50%);

            color: #7c8994;

            font-size: 15px;

            pointer-events: none;
        }


        .champ input {

            width: 100%;

            height: 47px;

            padding: 0 14px 0 40px;

            border: 1px solid #d7dfe6;

            border-radius: 8px;

            background: #fafbfc;

            color: #263746;

            font-family: Arial, sans-serif;

            font-size: 14px;

            outline: none;

            transition: 0.2s ease;
        }


        .champ input:focus {

            background: white;

            border-color: #123b68;

            box-shadow:
                0 0 0 3px
                rgba(18, 59, 104, 0.09);
        }


        /* =====================================================
           BOUTON
        ===================================================== */

        .btn-connexion {

            width: 100%;

            height: 48px;

            border: none;

            border-radius: 8px;

            background: #123b68;

            color: white;

            font-size: 14px;

            font-weight: bold;

            cursor: pointer;

            transition: 0.25s ease;

            margin-top: 5px;
        }


        .btn-connexion:hover {

            background: #0d2d50;

            transform: translateY(-1px);

            box-shadow:
                0 5px 12px
                rgba(18, 59, 104, 0.2);
        }


        /* =====================================================
           NOTE SÉCURITÉ
        ===================================================== */

        .security-note {

            display: flex;

            align-items: flex-start;

            gap: 9px;

            margin-top: 20px;

            padding: 12px;

            background: #f4f7fa;

            border-radius: 7px;

            color: #687681;

            font-size: 11px;

            line-height: 1.5;
        }


        .security-note strong {

            color: #123b68;
        }


        /* =====================================================
           FOOTER
        ===================================================== */

        footer {

            background: #0d2d50;

            color: rgba(255, 255, 255, 0.8);

            text-align: center;

            padding: 15px;

            font-size: 11px;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 800px) {

            header {

                padding: 0 20px;
            }


            .secure-label {

                display: none;
            }


            .connexion-container {

                grid-template-columns: 1fr;

                max-width: 520px;
            }


            .connexion-info {

                padding: 30px;

                text-align: center;

                align-items: center;
            }


            .connexion-info img {

                width: 80px;
                height: 80px;

                margin-bottom: 15px;
            }


            .connexion-info h1 {

                font-size: 24px;
            }


            .connexion-info p {

                margin-bottom: 15px;
            }


            .info-line {

                justify-content: center;
            }


            .connexion {

                padding: 35px 30px;
            }

        }


        @media (max-width: 480px) {

            header {

                height: 70px;

                padding: 0 15px;
            }


            .brand img {

                width: 44px;
                height: 44px;
            }


            .brand-text h2 {

                font-size: 16px;
            }


            .brand-text p {

                font-size: 10px;
            }


            main {

                padding: 25px 12px;
            }


            .connexion-info {

                padding: 28px 22px;
            }


            .connexion-info h1 {

                font-size: 22px;
            }


            .connexion {

                padding: 30px 22px;
            }


            .connexion-header h2 {

                font-size: 22px;
            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     HEADER
     ===================================================== -->

<header>

    <div class="header-content">


        <div class="brand">

            <img
                src="../images/dglp.jpg"
                alt="Logo DGLP"
            >

            <div class="brand-text">

                <h2>
                    DGLP Administration
                </h2>

                <p>
                    Direction Générale de la Lutte contre la Pauvreté
                </p>

            </div>

        </div>


        <div class="secure-label">

            <span class="secure-icon">
                🔒
            </span>

            Accès sécurisé

        </div>


    </div>

</header>


<!-- =====================================================
     CONTENU
     ===================================================== -->

<main>


    <div class="connexion-container">


        <!-- =================================================
             INFORMATIONS
             ================================================= -->

        <section class="connexion-info">

            <img
                src="../images/dglp.jpg"
                alt="Logo DGLP"
            >


            <h1>

                Espace
                <span>administration</span>

            </h1>


            <p>

                Connectez-vous à votre espace d'administration
                pour gérer les offres de services, les candidatures,
                les classements et le suivi des opérations de la DGLP.

            </p>


            <div class="info-line">

                <span class="info-dot"></span>

                Gestion des offres de services

            </div>


            <div class="info-line">

                <span class="info-dot"></span>

                Gestion des candidatures

            </div>


            <div class="info-line">

                <span class="info-dot"></span>

                Contrôle et classement des candidats

            </div>


        </section>


        <!-- =================================================
             FORMULAIRE
             ================================================= -->

        <section class="connexion">


            <div class="connexion-header">

                <h2>
                    Se connecter
                </h2>

                <p>
                    Utilisez vos identifiants administrateur
                    pour accéder à la plateforme.
                </p>

            </div>


            <?php if ($erreur !== ""): ?>

                <div class="message-erreur">

                    <?= htmlspecialchars($erreur) ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                autocomplete="on"
            >


                <!-- EMAIL -->

                <div class="champ">

                    <label for="email">
                        Adresse email
                    </label>

                    <div class="input-wrapper">

                        <span class="input-icon">
                            ✉
                        </span>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="admin@dglp.ga"
                            value="<?= htmlspecialchars(
                                $_POST["email"] ?? ""
                            ) ?>"
                            autocomplete="username"
                            required
                        >

                    </div>

                </div>


                <!-- MOT DE PASSE -->

                <div class="champ">

                    <label for="mot_de_passe">
                        Mot de passe
                    </label>

                    <div class="input-wrapper">

                        <span class="input-icon">
                            🔒
                        </span>

                        <input
                            type="password"
                            id="mot_de_passe"
                            name="mot_de_passe"
                            placeholder="Votre mot de passe"
                            autocomplete="current-password"
                            required
                        >

                    </div>

                </div>


                <!-- BOUTON -->

                <button
                    type="submit"
                    class="btn-connexion"
                >

                    Se connecter

                </button>


            </form>


            <div class="security-note">

                <span>
                    🔐
                </span>

                <div>

                    <strong>
                        Connexion sécurisée
                    </strong>

                    <br>

                    Vos identifiants sont utilisés uniquement
                    pour accéder à votre espace d'administration.

                </div>

            </div>


        </section>


    </div>


</main>


<!-- =====================================================
     FOOTER
     ===================================================== -->

<footer>

    © 2026 Direction Générale de la Lutte contre la Pauvreté -
    Administration

</footer>


</body>

</html>