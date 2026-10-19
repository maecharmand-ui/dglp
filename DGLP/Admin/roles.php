
<?php
session_start();

require_once "../../database.php";

/* =====================================================
   1. VERIFIER LA CONNEXION
===================================================== */

if (!isset($_SESSION["id_admin"])) {
    header("Location: connexion.php");
    exit;
}

/* =====================================================
   2. VERIFIER LE ROLE DE L'ADMINISTRATEUR
===================================================== */

$requeteAdmin = $pdo->prepare("
    SELECT id_admin, nom, prenom, role
    FROM admin
    WHERE id_admin = ?
");

$requeteAdmin->execute([$_SESSION["id_admin"]]);
$adminConnecte = $requeteAdmin->fetch(PDO::FETCH_ASSOC);

if (!$adminConnecte) {
    session_destroy();
    header("Location: connexion.php");
    exit;
}

/* Seul le super administrateur gere les permissions */
if ($adminConnecte["role"] !== "super_admin") {
    http_response_code(403);
    exit("Accès refusé : réservé au super administrateur.");
}

/* =====================================================
   3. DEFINIR LES ROLES ET LES PAGES
===================================================== */

$pdo->exec("
    INSERT IGNORE INTO role (nom_role, description)
    VALUES
    ('super_admin', 'Administration principale'),
    ('admin', 'Administration de l application')
");

$pages = [
    "dashboard.php"          => "Tableau de bord",
    "offres.php"             => "Gestion des offres",
    "candidatures.php"       => "Gestion des candidatures",
    "examiner-candidature.php" => "Examen des candidatures",
    "classement.php"         => "Classement",
    "decision.php"           => "Décisions",
    "historique.php"         => "Historique",
    "roles.php"              => "Gestion des rôles"
];

/* Charger les rôles */
$roles = $pdo->query("
    SELECT id_role, nom_role, description
    FROM role
    ORDER BY id_role
")->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   4. INITIALISER LES PERMISSIONS MANQUANTES
===================================================== */

$insertion = $pdo->prepare("
    INSERT IGNORE INTO permission
        (id_role, page, autorise)
    VALUES (?, ?, 0)
");

foreach ($roles as $role) {
    foreach ($pages as $nomPage => $libelle) {
        $insertion->execute([
            $role["id_role"],
            $nomPage
        ]);
    }
}

/* Le super_admin conserve tous les accès */
$requeteSuperAdmin = $pdo->prepare("
    UPDATE permission
    SET autorise = 1
    WHERE id_role = ?
");

$idSuperAdmin = null;

foreach ($roles as $role) {
    if ($role["nom_role"] === "super_admin") {
        $idSuperAdmin = (int) $role["id_role"];
        break;
    }
}

if ($idSuperAdmin !== null) {
    $requeteSuperAdmin->execute([$idSuperAdmin]);
}

/* =====================================================
   5. ENREGISTRER LES PERMISSIONS
===================================================== */

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"], $_SESSION["csrf_roles"]) ||
        !hash_equals($_SESSION["csrf_roles"], $_POST["csrf_token"])
    ) {
        $message = "Session du formulaire invalide. Recharge la page.";
    } else {
        try {
            $pdo->beginTransaction();

            foreach ($roles as $role) {
                $idRole = (int) $role["id_role"];

                /* Le super_admin ne peut pas perdre ses accès */
                if ($role["nom_role"] === "super_admin") {
                    continue;
                }

                foreach ($pages as $nomPage => $libelle) {
                    $autorise = isset(
                        $_POST["permissions"][$idRole][$nomPage]
                    ) ? 1 : 0;

                    $maj = $pdo->prepare("
                        UPDATE permission
                        SET autorise = ?
                        WHERE id_role = ?
                          AND page = ?
                    ");

                    $maj->execute([
                        $autorise,
                        $idRole,
                        $nomPage
                    ]);
                }
            }

            $pdo->commit();

            $message = "Les permissions ont été enregistrées.";

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $message = "Une erreur est survenue pendant l'enregistrement.";
        }
    }
}

/* Jeton de sécurité du formulaire */
if (empty($_SESSION["csrf_roles"])) {
    $_SESSION["csrf_roles"] = bin2hex(random_bytes(32));
}

/* Recharger les permissions */
$permissions = $pdo->query("
    SELECT id_role, page, autorise
    FROM permission
")->fetchAll(PDO::FETCH_ASSOC);

$matrice = [];

foreach ($permissions as $permission) {
    $matrice[(int) $permission["id_role"]]
            [$permission["page"]] =
        (int) $permission["autorise"];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestion des rôles - DGLP</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #263445;
        }

        .entete {
            background: #123b68;
            color: white;
            padding: 24px 30px;
        }

        .entete h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .contenu {
            max-width: 1250px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .retour {
            display: inline-block;
            margin-bottom: 20px;
            color: #123b68;
            text-decoration: none;
            font-weight: bold;
        }

        .carte {
            background: white;
            border-radius: 10px;
            padding: 24px;
            box-shadow: 0 2px 10px #0000000d;
        }

        .message {
            padding: 14px;
            margin-bottom: 20px;
            border-radius: 6px;
            background: #e4f6e9;
            color: #176534;
        }

        .table-conteneur {
            overflow-x: auto;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 14px 12px;
            border-bottom: 1px solid #e5e9ef;
            text-align: center;
        }

        th {
            background: #edf2f8;
            color: #123b68;
        }

        th:first-child, td:first-child {
            text-align: left;
        }

        .nom-role {
            font-weight: bold;
        }

        .description {
            color: #64748b;
            font-size: 13px;
            margin-top: 5px;
        }

        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #087c45;
        }

        .verrou {
            color: #087c45;
            font-weight: bold;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .bouton {
            display: inline-block;
            border: none;
            border-radius: 6px;
            padding: 12px 20px;
            background: #123b68;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-weight: bold;
        }

        .bouton:hover {
            background: #0c2d50;
        }

        .note {
            margin-top: 18px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }

        footer {
            text-align: center;
            color: #64748b;
            padding: 25px;
        }
    </style>
</head>

<body>

<header class="entete">
    <h1>Gestion des rôles et des permissions</h1>
    <p>Direction Générale de la Lutte contre la Pauvreté</p>
</header>

<main class="contenu">

    <a href="dashboard.php" class="retour">
        ← Retour au tableau de bord
    </a>

    <section class="carte">

        <h2>Matrice des autorisations</h2>

        <p style="margin-top:10px;">
            Connecté en tant que
            <strong>
                <?= htmlspecialchars(
                    $adminConnecte["prenom"] . " " .
                    $adminConnecte["nom"]
                ) ?>
            </strong>
        </p>

        <?php if ($message !== ""): ?>
            <div class="message" style="margin-top:20px;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="roles.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["csrf_roles"]) ?>"
            >

            <div class="table-conteneur">
                <table>
                    <thead>
                        <tr>
                            <th>Rôle</th>

                            <?php foreach ($pages as $libelle): ?>
                                <th><?= htmlspecialchars($libelle) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td>
                                <div class="nom-role">
                                    <?= htmlspecialchars($role["nom_role"]) ?>
                                </div>

                                <div class="description">
                                    <?= htmlspecialchars(
                                        $role["description"] ?? ""
                                    ) ?>
                                </div>
                            </td>

                            <?php foreach ($pages as $nomPage => $libelle): ?>
                                <td>
                                    <?php if (
                                        $role["nom_role"] === "super_admin"
                                    ): ?>
                                        <span class="verrou"
                                              title="Accès conservé">
                                            ✓
                                        </span>
                                    <?php else: ?>
                                        <input
                                            type="checkbox"
                                            name="permissions[<?= (int) $role["id_role"] ?>][<?= htmlspecialchars($nomPage, ENT_QUOTES) ?>]"
                                            value="1"
                                            aria-label="<?= htmlspecialchars($libelle) ?>"
                                            <?= !empty(
                                                $matrice[(int) $role["id_role"]][$nomPage]
                                            ) ? "checked" : "" ?>
                                        >
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="actions">
                <button type="submit" class="bouton">
                    Enregistrer les permissions
                </button>

                <a href="dashboard.php" class="bouton">
                    Retour au tableau de bord
                </a>
            </div>

        </form>

        <p class="note">
            Le rôle super_admin conserve tous ses accès.
            Les cases des autres rôles définissent les autorisations
            enregistrées dans la base de données.
            La vérification de ces autorisations doit également être
            intégrée aux pages PHP concernées.
        </p>

    </section>
</main>

<footer>
    DGLP © 2026
</footer>

</body>
</html>
