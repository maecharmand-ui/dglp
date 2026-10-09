  <?php 
   if(isset($_POST["email"])){
     if(empty($_POST["email"])){
        echo "Email obligatoire";
     } else{
        echo "Email renseigné";
     }
   }else{
        echo "Email absent";
   }
        
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Formulaire candidat</title>
</head>

<body>

    <h1>Informations du candidat</h1>

    <form method="POST">

        <label for="nom">Nom :</label>
        <input type="text" name="nom" id="nom">

        <br><br>

        <label for="prenom">Prénom :</label>
        <input type="text" name="prenom" id="prenom">

        <br><br>

        <label for="age">Âge :</label>
        <input type="text" name="age" id="age">

        <br><br>

        <button type="submit">Envoyer</button>

    </form>
    <a href="detaille_ofre.php?id=25"> voir l'offre</a>

</body>

</html>