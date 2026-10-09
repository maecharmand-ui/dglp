<?php

session_start();

require_once "../../database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nom = trim($_POST["nom"] ?? "");
    $prenom = trim($_POST["prenom"] ?? "");
    $date_naissance = $_POST["date_naissance"] ?? "";
    $province = trim($_POST["province"] ?? "");
    $ville = trim($_POST["ville"] ?? "");
    $telephone = trim($_POST["tel"] ?? "");
    $email = trim($_POST["Adresse_email"] ?? "");
    $mot_de_passe = $_POST["mot_de_passe"] ?? "";
    $confirmation = $_POST["confirmation_mot_de_passe"] ?? "";

    /*
    |--------------------------------------------------------------------------
    | VERIFICATION DES CHAMPS
    |--------------------------------------------------------------------------
    */

    if (
        empty($nom) ||
        empty($prenom) ||
        empty($date_naissance) ||
        empty($province) ||
        empty($ville) ||
        empty($telephone) ||
        empty($email) ||
        empty($mot_de_passe) ||
        empty($confirmation)
    ) {

        $message = "Veuillez remplir tous les champs obligatoires.";

    } elseif ($mot_de_passe !== $confirmation) {

        $message = "Les mots de passe ne correspondent pas.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Adresse email invalide.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | VERIFICATION EMAIL
        |--------------------------------------------------------------------------
        */

        $verification = $pdo->prepare(
            "SELECT id_compte
             FROM compte
             WHERE email = ?"
        );

        $verification->execute([$email]);

        if ($verification->fetch()) {

            $message = "Cette adresse email est déjà utilisée.";

        } else {

            try {

                $pdo->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | CREATION DU CANDIDAT
                |--------------------------------------------------------------------------
                */

                $requete = $pdo->prepare(
                    "INSERT INTO candidat
                    (
                        nom,
                        prenom,
                        date_naissance,
                        province,
                        ville,
                        telephone
                    )
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                $requete->execute([
                    $nom,
                    $prenom,
                    $date_naissance,
                    $province,
                    $ville,
                    $telephone
                ]);

                $id_candidat = $pdo->lastInsertId();

                /*
                |--------------------------------------------------------------------------
                | HASH DU MOT DE PASSE
                |--------------------------------------------------------------------------
                */

                $mot_de_passe_hash = password_hash(
                    $mot_de_passe,
                    PASSWORD_DEFAULT
                );

                /*
                |--------------------------------------------------------------------------
                | CREATION DU COMPTE
                |--------------------------------------------------------------------------
                */

                $requete = $pdo->prepare(
                    "INSERT INTO compte
                    (
                        email,
                        mot_de_passe,
                        id_candidat
                    )
                    VALUES (?, ?, ?)"
                );

                $requete->execute([
                    $email,
                    $mot_de_passe_hash,
                    $id_candidat
                ]);

                $pdo->commit();

                /*
                |--------------------------------------------------------------------------
                | REDIRECTION
                |--------------------------------------------------------------------------
                */

                header("Location: connexion.php?inscription=success");
                exit;

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                die("VRAIE ERREUR SQL : " . $e->getMessage());
            }
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

    <title>DGLP - Création de compte</title>

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


        /* =========================================================
           HEADER
        ========================================================= */

        header {
            height: 82px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 50px;
            border-bottom: 1px solid #e1e6eb;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 14px;
            color: #123b68;
            font-size: 27px;
            font-weight: 800;
        }

        .logo img {
            width: 52px;
            height: 52px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #123b68;
        }

        nav {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        nav a {
            color: #34495e;
            padding: 11px 17px;
            border-radius: 7px;
            font-size: 14px;
            font-weight: 600;
            transition: 0.25s ease;
        }

        nav a:hover {
            background: #edf3f8;
            color: #123b68;
        }

        nav a:last-child {
            background: #123b68;
            color: #ffffff;
        }

        nav a:last-child:hover {
            background: #0d2d50;
            color: #ffffff;
        }


        /* =========================================================
           CONTENU PRINCIPAL
        ========================================================= */

        main {
            min-height: calc(100vh - 150px);
            padding: 50px 20px 70px;
        }

        .inscription {
            width: 100%;
            max-width: 850px;
            margin: 0 auto;
        }

        .titre-page {
            text-align: center;
            margin-bottom: 30px;
        }

        .titre-page .badge {
            display: inline-block;
            background: #e8f1f8;
            color: #123b68;
            padding: 7px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .titre-page h1 {
            color: #123b68;
            font-size: 32px;
            margin-bottom: 8px;
        }

        .titre-page p {
            color: #667788;
            font-size: 15px;
        }


        /* =========================================================
           MESSAGE ERREUR
        ========================================================= */

        .message-erreur {
            background: #fff1f1;
            color: #a52828;
            border: 1px solid #efb7b7;
            border-left: 5px solid #c0392b;
            padding: 14px 17px;
            border-radius: 8px;
            margin-bottom: 22px;
            font-size: 14px;
        }


        /* =========================================================
           FORMULAIRE
        ========================================================= */

        .formulaire-inscription {
            background: #ffffff;
            border: 1px solid #e0e6eb;
            border-radius: 14px;
            padding: 35px 40px;
            box-shadow: 0 8px 25px rgba(18, 59, 104, 0.08);
        }

        .formulaire-titre {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 18px;
            border-bottom: 1px solid #e7ebef;
        }

        .formulaire-titre .icone {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eaf3ee;
            color: #008c4a;
            border-radius: 9px;
            font-size: 20px;
            font-weight: bold;
        }

        .formulaire-titre h2 {
            color: #123b68;
            font-size: 20px;
        }

        .formulaire-titre p {
            color: #7a8793;
            font-size: 13px;
        }

        form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px 22px;
        }

        .champ {
            display: flex;
            flex-direction: column;
        }

        .champ.plein {
            grid-column: 1 / -1;
        }

        label {
            color: #34495e;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        .obligatoire {
            color: #c0392b;
        }

        input {
            width: 100%;
            height: 45px;
            padding: 0 13px;
            border: 1px solid #ccd5dd;
            border-radius: 7px;
            background: #ffffff;
            color: #263238;
            font-size: 14px;
            outline: none;
            transition: 0.2s ease;
        }

        input:focus {
            border-color: #123b68;
            box-shadow: 0 0 0 3px rgba(18, 59, 104, 0.10);
        }

        input::placeholder {
            color: #a0aab3;
        }

        .aide {
            margin-top: 5px;
            color: #89949d;
            font-size: 12px;
        }


        /* =========================================================
           BOUTON
        ========================================================= */

        .zone-bouton {
            grid-column: 1 / -1;
            margin-top: 10px;
            padding-top: 22px;
            border-top: 1px solid #e7ebef;
            display: flex;
            justify-content: flex-end;
        }

        button {
            border: none;
            background: #008c4a;
            color: #ffffff;
            padding: 13px 28px;
            border-radius: 7px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.25s ease;
        }

        button:hover {
            background: #006f3b;
            transform: translateY(-1px);
            box-shadow: 0 5px 12px rgba(0, 140, 74, 0.20);
        }


        /* =========================================================
           BAS DU FORMULAIRE
        ========================================================= */

        .connexion-existante {
            text-align: center;
            margin-top: 22px;
            color: #6f7d88;
            font-size: 14px;
        }

        .connexion-existante a {
            color: #123b68;
            font-weight: 700;
        }

        .connexion-existante a:hover {
            text-decoration: underline;
        }


        /* =========================================================
           FOOTER
        ========================================================= */

        footer {
            background: #123b68;
            color: #ffffff;
            text-align: center;
            padding: 22px 20px;
            font-size: 13px;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 700px) {

            header {
                height: auto;
                padding: 15px 20px;
                flex-direction: column;
                gap: 12px;
            }

            nav {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }

            nav a {
                font-size: 13px;
                padding: 9px 11px;
            }

            main {
                padding: 35px 15px 50px;
            }

            .titre-page h1 {
                font-size: 26px;
            }

            .formulaire-inscription {
                padding: 25px 20px;
            }

            form {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .champ.plein {
                grid-column: auto;
            }

            .zone-bouton {
                grid-column: auto;
            }

            button {
                width: 100%;
            }
        }

    </style>

</head>

<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header>

    <div class="logo">

        <img
            src="../images/dglp.jpg"
            alt="Logo DGLP"
        >

        <span>DGLP</span>

    </div>


    <nav>

        <a href="../index.html">
            Accueil
        </a>

        <a href="offres.php">
            Offres
        </a>

        <a href="connexion.php">
            Connexion
        </a>

    </nav>

</header>


<!-- =========================================================
     CONTENU
========================================================= -->

<main>

    <section class="inscription">


        <div class="titre-page">

            <span class="badge">
                Plateforme DGLP
            </span>

            <h1>
                Création d'un compte
            </h1>

            <p>
                Créez votre compte candidat pour accéder aux offres
                de services et déposer vos candidatures en ligne.
            </p>

        </div>


        <?php if (!empty($message)): ?>

            <p class="message-erreur">
                <?= htmlspecialchars($message) ?>
            </p>

        <?php endif; ?>


        <article class="formulaire-inscription">


            <div class="formulaire-titre">

                <div class="icone">
                    ✓
                </div>

                <div>

                    <h2>
                        Informations du candidat
                    </h2>

                    <p>
                        Tous les champs sont obligatoires.
                    </p>

                </div>

            </div>


            <form action="" method="POST">


                <!-- NOM -->

                <div class="champ">

                    <label for="nom">
                        Nom <span class="obligatoire">*</span>
                    </label>

                    <input
                        type="text"
                        id="nom"
                        name="nom"
                        value="<?= htmlspecialchars($_POST["nom"] ?? "") ?>"
                        placeholder="Ex. : NGOMA"
                        required
                    >

                </div>


                <!-- PRENOM -->

                <div class="champ">

                    <label for="prenom">
                        Prénom <span class="obligatoire">*</span>
                    </label>

                    <input
                        type="text"
                        id="prenom"
                        name="prenom"
                        value="<?= htmlspecialchars($_POST["prenom"] ?? "") ?>"
                        placeholder="Ex. : Jean"
                        required
                    >

                </div>


                <!-- DATE DE NAISSANCE -->

                <div class="champ">

                    <label for="date_naissance">
                        Date de naissance <span class="obligatoire">*</span>
                    </label>

                    <input
                        type="date"
                        id="date_naissance"
                        name="date_naissance"
                        value="<?= htmlspecialchars($_POST["date_naissance"] ?? "") ?>"
                        required
                    >

                </div>


                <!-- TELEPHONE -->

                <div class="champ">

                    <label for="tel">
                        Téléphone <span class="obligatoire">*</span>
                    </label>

                    <input
                        type="tel"
                        id="tel"
                        name="tel"
                        value="<?= htmlspecialchars($_POST["tel"] ?? "") ?>"
                        placeholder="Ex. : 06 XX XX XX XX"
                        required
                    >

                </div>


                <!-- PROVINCE -->

                <div class="champ">

                    <label for="province">
                        Province <span class="obligatoire">*</span>
                    </label>

                    <input
                        type="text"
                        id="province"
                        name="province"
                        value="<?= htmlspecialchars($_POST["province"] ?? "") ?>"
                        placeholder="Ex. : Estuaire"
                        required
                    >

                </div>


                <!-- VILLE -->

                <div class="champ">

                    <label for="ville">
                        Ville <span class="obligatoire">*</span>
                    </label>

                    <input
                        type="text"
                        id="ville"
                        name="ville"
                        value="<?= htmlspecialchars($_POST["ville"] ?? "") ?>"
                        placeholder="Ex. : Libreville"
                        required
                    >

                </div>


                <!-- EMAIL -->

                <div class="champ plein">

                    <label for="Adresse_email">
                        Adresse email <span class="obligatoire">*</span>
                    </label>

                    <input
                        type="email"
                        id="Adresse_email"
                        name="Adresse_email"
                        value="<?= htmlspecialchars($_POST["Adresse_email"] ?? "") ?>"
                        placeholder="Ex. : candidat@email.com"
                        required
                    >

                    <span class="aide">
                        Cette adresse sera utilisée pour vous connecter à votre espace candidat.
                    </span>

                </div>


                <!-- MOT DE PASSE -->

                <div class="champ">

                    <label for="mot_de_passe">
                        Mot de passe <span class="obligatoire">*</span>
                    </label>

                    <input
                        type="password"
                        id="mot_de_passe"
                        name="mot_de_passe"
                        placeholder="Votre mot de passe"
                        required
                    >

                </div>


                <!-- CONFIRMATION -->

                <div class="champ">

                    <label for="confirmation_mot_de_passe">
                        Confirmation du mot de passe <span class="obligatoire">*</span>
                    </label>

                    <input
                        type="password"
                        id="confirmation_mot_de_passe"
                        name="confirmation_mot_de_passe"
                        placeholder="Confirmez votre mot de passe"
                        required
                    >

                </div>


                <!-- BOUTON -->

                <div class="zone-bouton">

                    <button type="submit">
                        Créer mon compte
                    </button>

                </div>


            </form>


            <div class="connexion-existante">

                Vous avez déjà un compte ?

                <a href="connexion.php">
                    Se connecter
                </a>

            </div>


        </article>

    </section>

</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    <p>
        © 2026 Direction Générale de la Lutte contre la Pauvreté -
        Tous droits réservés.
    </p>

</footer>


</body>
</html>