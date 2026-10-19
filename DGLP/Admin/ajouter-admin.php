
<?php
session_start();

require_once "../../database.php";

/* Réserver cette opération au super administrateur */
if (
    !isset($_SESSION["id_admin"]) ||
    ($_SESSION["role_admin"] ?? "") !== "super_admin"
) {
    http_response_code(403);
    exit("Accès refusé.");
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nom = trim($_POST["nom"] ?? "");
    $prenom = trim($_POST["prenom"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $motDePasse = $_POST["mot_de_passe"] ?? "";

    if (
        $nom === "" ||
        $prenom === "" ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($motDePasse) < 12
    ) {
        $message = "Remplis correctement tous les champs. "
            . "Le mot de passe doit contenir au moins 12 caractères.";
    } else {
        $verification = $pdo->prepare(
            "SELECT id_admin FROM admin WHERE email = ?"
        );
        $verification->execute([$email]);

        if ($verification->fetch()) {
            $message = "Cette adresse email est déjà utilisée.";
        } else {
            $hash = password_hash($motDePasse, PASSWORD_DEFAULT);

            $insertion = $pdo->prepare("
                INSERT INTO admin
                    (nom, prenom, email, mot_de_passe, role)
                VALUES (?, ?, ?, ?, 'admin')
            ");

            $insertion->execute([
                $nom,
                $prenom,
                $email,
                $hash
            ]);

            $message = "Le compte administrateur a été créé.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ajouter un administrateur - DGLP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 30px 15px;
            color: #263445;
        }
        main {
            max-width: 500px;
            margin: 30px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 15px #0001;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        input {
            box-sizing: border-box;
            width: 100%;
            padding: 12px;
            margin-top: 6px;
            border: 1px solid #ccd4dd;
            border-radius: 6px;
        }
        button {
            margin-top: 22px;
            padding: 12px 18px;
            border: 0;
            border-radius: 6px;
            background: #123b68;
            color: white;
            cursor: pointer;
        }
        .message {
            padding: 12px;
            background: #edf6ee;
            border-radius: 6px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
<main>
    <h1>Ajouter un administrateur</h1>

    <?php if ($message !== ""): ?>
        <p class="message"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label for="nom">Nom</label>
        <input id="nom" name="nom" required>

        <label for="prenom">Prénom</label>
        <input id="prenom" name="prenom" required>

        <label for="email">Adresse email</label>
        <input id="email" name="email" type="email" required>

        <label for="mot_de_passe">Mot de passe temporaire</label>
        <input
            id="mot_de_passe"
            name="mot_de_passe"
            type="password"
            minlength="12"
            autocomplete="new-password"
            required
        >

        <button type="submit">Créer le compte</button>
    </form>
</main>
</body>
</html>
