<?php

session_start();

require_once "../../database.php";

$message = "";

/* =========================================================
   Vérifier si le candidat est déjà connecté
========================================================= */

if (isset($_SESSION["id_candidat"])) {
    header("Location: espacecandidat.php");
    exit;
}

/* =========================================================
   Message après inscription
========================================================= */

if (
    isset($_GET["inscription"]) &&
    $_GET["inscription"] === "success"
) {
    $message = "Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter.";
}

/* =========================================================
   Traitement de la connexion
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $mot_de_passe = $_POST["mot_de_passe"] ?? "";

    if (empty($email) || empty($mot_de_passe)) {

        $message = "Veuillez remplir tous les champs.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Adresse email invalide.";

    } else {

        $requete = $pdo->prepare(
            "SELECT
                id_compte,
                mot_de_passe,
                id_candidat
             FROM compte
             WHERE email = ?"
        );

        $requete->execute([$email]);

        $compte = $requete->fetch(PDO::FETCH_ASSOC);

        if (
            $compte &&
            password_verify(
                $mot_de_passe,
                $compte["mot_de_passe"]
            )
        ) {

            session_regenerate_id(true);

            $_SESSION["id_candidat"] = $compte["id_candidat"];
            $_SESSION["id_compte"] = $compte["id_compte"];
            $_SESSION["email"] = $email;

            header("Location: espacecandidat.php");
            exit;

        } else {

            $message = "Email ou mot de passe incorrect.";

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

    <title>DGLP - Connexion</title>

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

            min-height: 100vh;

            background:
                linear-gradient(
                    rgba(244, 246, 248, 0.93),
                    rgba(244, 246, 248, 0.93)
                ),
                url("../images/dglp.jpg")
                center center / 420px auto
                no-repeat fixed;

            background-color: #f4f6f8;

            color: #263238;

            display: flex;
            flex-direction: column;
        }

        a {
            text-decoration: none;
        }

        /* =====================================================
           HEADER
        ===================================================== */

        header {
            min-height: 82px;

            background: rgba(255, 255, 255, 0.97);

            border-bottom: 1px solid #e3e7eb;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 12px 50px;

            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);

            position: relative;
            z-index: 10;
        }

        /* =====================================================
           LOGO
        ===================================================== */

        .logo-container {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .logo-image {
            width: 52px;
            height: 52px;

            object-fit: contain;
        }

        .logo-text h1 {
            color: #123b68;

            font-size: 21px;

            margin-bottom: 2px;
        }

        .logo-text p {
            color: #6c757d;

            font-size: 11px;
        }

        /* =====================================================
           NAVIGATION
        ===================================================== */

        nav {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        nav a {
            color: #123b68;

            padding: 10px 15px;

            border-radius: 7px;

            font-size: 14px;

            font-weight: 600;

            transition: 0.25s;
        }

        nav a:hover {
            background: #eef3f8;

            color: #008c4a;
        }

        nav a.active {
            background: #123b68;

            color: white;
        }

        /* =====================================================
           CONTENU PRINCIPAL
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

        .connexion {
            width: 100%;
            max-width: 470px;

            text-align: center;
        }

        .connexion-header {
            margin-bottom: 25px;
        }

        .connexion-logo {
            width: 85px;
            height: 85px;

            object-fit: cover;

            border-radius: 50%;

            background: white;

            padding: 5px;

            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.10);

            margin-bottom: 15px;
        }

        .connexion h1 {
            color: #123b68;

            font-size: 29px;

            margin-bottom: 7px;
        }

        .connexion-introduction {
            color: #68737d;

            font-size: 14px;
        }

        /* =====================================================
           MESSAGE
        ===================================================== */

        .message {
            background: #eef7ff;

            border-left: 4px solid #123b68;

            color: #36536d;

            text-align: left;

            padding: 13px 15px;

            border-radius: 7px;

            margin-bottom: 18px;

            font-size: 13px;
        }

        /* =====================================================
           FORMULAIRE
        ===================================================== */

        .formulaire-connexion {
            background: white;

            border-radius: 14px;

            padding: 32px;

            border: 1px solid #e3e7eb;

            box-shadow: 0 7px 25px rgba(0, 0, 0, 0.08);

            text-align: left;

            margin-bottom: 20px;
        }

        .formulaire-connexion form {
            display: flex;

            flex-direction: column;
        }

        .formulaire-connexion label {
            color: #123b68;

            font-size: 13px;

            font-weight: bold;

            margin-bottom: 7px;
        }

        .formulaire-connexion input {
            width: 100%;

            padding: 13px 14px;

            border: 1px solid #d5dce2;

            border-radius: 7px;

            background: #fafbfc;

            color: #263238;

            font-size: 14px;

            outline: none;

            margin-bottom: 18px;

            transition: 0.25s;
        }

        .formulaire-connexion input:focus {
            border-color: #123b68;

            background: white;

            box-shadow: 0 0 0 3px rgba(18, 59, 104, 0.08);
        }

        .formulaire-connexion input::placeholder {
            color: #a1a8ae;
        }

        /* =====================================================
           BOUTON
        ===================================================== */

        .formulaire-connexion button {
            width: 100%;

            border: none;

            border-radius: 7px;

            padding: 13px 20px;

            background: #123b68;

            color: white;

            font-size: 14px;

            font-weight: bold;

            cursor: pointer;

            transition: 0.25s;

            margin-top: 3px;
        }

        .formulaire-connexion button:hover {
            background: #0d2d50;

            transform: translateY(-1px);

            box-shadow: 0 4px 10px rgba(18, 59, 104, 0.18);
        }

        /* =====================================================
           INSCRIPTION
        ===================================================== */

        .inscription-link {
            color: #68737d;

            font-size: 13px;
        }

        .inscription-link a {
            color: #008c4a;

            font-weight: bold;

            margin-left: 4px;
        }

        .inscription-link a:hover {
            text-decoration: underline;
        }

        /* =====================================================
           INFORMATIONS
        ===================================================== */

        .info-connexion {
            margin-top: 18px;

            color: #7a858e;

            font-size: 11px;

            line-height: 1.5;
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
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 750px) {

            header {
                padding: 12px 20px;

                flex-direction: column;

                gap: 12px;
            }

            .logo-container {
                width: 100%;

                justify-content: center;
            }

            nav {
                width: 100%;

                justify-content: center;

                flex-wrap: wrap;
            }

            main {
                padding: 35px 15px;
            }

        }

        @media (max-width: 500px) {

            .logo-image {
                width: 45px;
                height: 45px;
            }

            .logo-text h1 {
                font-size: 17px;
            }

            .logo-text p {
                font-size: 9px;
            }

            nav a {
                padding: 8px 10px;

                font-size: 12px;
            }

            .connexion h1 {
                font-size: 25px;
            }

            .formulaire-connexion {
                padding: 23px 20px;
            }

            .connexion-logo {
                width: 70px;
                height: 70px;
            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     HEADER
====================================================== -->

<header>

    <div class="logo-container">

        <img
            src="../images/ministere.jpg"
            alt="Logo du Ministère"
            class="logo-image"
        >

        <div class="logo-text">

            <h1>
                DGLP
            </h1>

            <p>
                Direction Générale de la Lutte contre la Pauvreté
            </p>

        </div>

    </div>


    <nav>

        <a href="../index.html">
            Accueil
        </a>

        <a href="offres.html">
            Offres
        </a>

        <a
            href="inscription.php"
        >
            Créer un compte
        </a>

    </nav>

</header>


<!-- =====================================================
     CONTENU
====================================================== -->

<main>

    <section class="connexion">

        <div class="connexion-header">

            <img
                src="../images/dglp.jpg"
                alt="Logo DGLP"
                class="connexion-logo"
            >

            <h1>
                Connexion
            </h1>

            <p class="connexion-introduction">
                Connectez-vous à votre espace candidat.
            </p>

        </div>


        <!-- =============================================
             MESSAGE
        ============================================== -->

        <?php if (!empty($message)): ?>

            <p class="message">

                <?= htmlspecialchars($message) ?>

            </p>

        <?php endif; ?>


        <!-- =============================================
             FORMULAIRE
        ============================================== -->

        <article class="formulaire-connexion">

            <form
                action=""
                method="POST"
            >

                <label for="email">
                    Adresse email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="exemple@email.com"
                    value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                    autocomplete="email"
                    required
                >


                <label for="mot_de_passe">
                    Mot de passe
                </label>

                <input
                    type="password"
                    id="mot_de_passe"
                    name="mot_de_passe"
                    placeholder="Votre mot de passe"
                    autocomplete="current-password"
                    required
                >


                <button type="submit">
                    Se connecter
                </button>

            </form>

        </article>


        <!-- =============================================
             CRÉATION DE COMPTE
        ============================================== -->

        <p class="inscription-link">

            Vous n'avez pas encore de compte ?

            <a href="inscription.php">
                Créer un compte
            </a>

        </p>


        <p class="info-connexion">

            L'accès à cet espace est réservé aux candidats
            disposant d'un compte enregistré sur la plateforme DGLP.

        </p>

    </section>

</main>


<!-- =====================================================
     FOOTER
====================================================== -->

<footer>

    © 2026 Direction Générale de la Lutte contre la Pauvreté -
    Tous droits réservés.

</footer>


</body>

</html>