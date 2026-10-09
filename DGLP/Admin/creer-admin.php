<?php

require_once "../../database.php";

/* =========================================================
   INFORMATIONS DU COMPTE ADMINISTRATEUR
   ========================================================= */

$nom = "Administrateur";
$prenom = "Principal";
$email = "admin@dglp.ga";
$mot_de_passe = "Admin1234";
$role = "admin";


/* =========================================================
   VÉRIFIER SI L'ADMINISTRATEUR EXISTE DÉJÀ
   ========================================================= */

$verification = $pdo->prepare("
    SELECT id_admin
    FROM admin
    WHERE email = ?
    LIMIT 1
");

$verification->execute([$email]);

if ($verification->fetch()) {

    echo "Cet administrateur existe déjà.";

    exit;
}


/* =========================================================
   HASH DU MOT DE PASSE
   ========================================================= */

$mot_de_passe_hash = password_hash(
    $mot_de_passe,
    PASSWORD_DEFAULT
);


/* =========================================================
   CRÉER L'ADMINISTRATEUR
   ========================================================= */

$requete = $pdo->prepare("
    INSERT INTO admin
    (
        nom,
        prenom,
        email,
        mot_de_passe,
        role
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        ?
    )
");

$requete->execute([
    $nom,
    $prenom,
    $email,
    $mot_de_passe_hash,
    $role
]);


echo "Administrateur créé avec succès.";