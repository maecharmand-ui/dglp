<?php

session_start();

/* =========================================================
   SUPPRIMER LES DONNÉES DE SESSION
   ========================================================= */

$_SESSION = [];

/* =========================================================
   SUPPRIMER LE COOKIE DE SESSION
   ========================================================= */

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/* =========================================================
   DÉTRUIRE LA SESSION
   ========================================================= */

session_destroy();

/* =========================================================
   REDIRECTION VERS LA CONNEXION ADMIN
   ========================================================= */

header("Location: connexion.php");
exit;