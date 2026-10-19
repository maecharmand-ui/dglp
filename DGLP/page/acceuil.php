
<?php
require_once "../../database.php";

/* Récupérer les offres ouvertes */
$offres = [];

try {
    $requete = $pdo->query("
        SELECT id_offre, description, date_limite_candidature,
               nombre_place, montant
        FROM offre
        WHERE statut = 'ouverte'
          AND date_limite_candidature >= CURDATE()
        ORDER BY date_limite_candidature ASC
        LIMIT 3
    ");

    $offres = $requete->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // La page reste accessible si les offres ne peuvent pas être chargées.
}

function e($valeur) {
    return htmlspecialchars((string) $valeur, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Accueil | DGLP - Gestion des candidatures</title>

    <style>
        :root {
            --vert: #008c45;
            --vert-fonce: #006b35;
            --jaune: #fcd116;
            --bleu: #0055a4;
            --bleu-fonce: #073763;
            --fond: #f5f8fa;
            --texte: #263445;
            --blanc: #ffffff;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 100px;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: var(--blanc);
            color: var(--texte);
            line-height: 1.7;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .barre-couleurs {
            height: 6px;
            background: linear-gradient(
                to right,
                var(--vert) 0 33.33%,
                var(--jaune) 33.33% 66.66%,
                var(--bleu) 66.66% 100%
            );
        }

        .entete {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.97);
            border-bottom: 1px solid #e6ebf0;
            box-shadow: 0 3px 14px rgba(0, 0, 0, 0.04);
        }

        .entete-interieur {
            max-width: 1200px;
            margin: auto;
            padding: 13px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .identite {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 220px;
        }

        .identite img {
            width: 62px;
            height: 62px;
            object-fit: contain;
        }

        .identite strong {
            display: block;
            color: var(--bleu-fonce);
            font-size: 19px;
        }

        .identite small {
            display: block;
            color: #64748b;
            font-size: 12px;
        }

        .navigation {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .navigation a {
            font-size: 13px;
            font-weight: 700;
            color: #34445a;
            transition: color 0.2s;
        }

        .navigation a:hover {
            color: var(--vert);
        }

        .actions-entete {
            display: flex;
            gap: 9px;
            align-items: center;
        }

        .bouton {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 19px;
            border-radius: 7px;
            font-size: 14px;
            font-weight: 700;
            border: 2px solid transparent;
            transition: 0.2s;
            cursor: pointer;
        }

        .bouton-vert {
            color: white;
            background: var(--vert);
        }

        .bouton-vert:hover {
            background: var(--vert-fonce);
            transform: translateY(-1px);
        }

        .bouton-bleu {
            color: var(--bleu);
            border-color: var(--bleu);
            background: white;
        }

        .bouton-bleu:hover {
            color: white;
            background: var(--bleu);
        }

        .bouton-blanc {
            background: white;
            color: var(--bleu-fonce);
        }

        .bouton-blanc:hover {
            background: var(--jaune);
        }

        .hero {
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(
                    circle at 90% 10%,
                    rgba(252, 209, 22, 0.22),
                    transparent 30%
                ),
                linear-gradient(125deg, #073763, #0055a4 68%, #087f55);
            color: white;
        }

        .hero-interieur {
            max-width: 1200px;
            margin: auto;
            padding: 80px 24px 85px;
            display: grid;
            grid-template-columns: 1.25fr 0.75fr;
            gap: 55px;
            align-items: center;
        }

        .etiquette {
            display: inline-block;
            color: #163d2a;
            background: var(--jaune);
            padding: 6px 13px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .hero h1 {
            max-width: 720px;
            font-size: clamp(32px, 4.2vw, 51px);
            line-height: 1.15;
            margin-bottom: 23px;
            letter-spacing: -1px;
        }

        .hero h1 span {
            color: var(--jaune);
        }

        .hero p {
            max-width: 680px;
            color: #e5effa;
            font-size: 17px;
            margin-bottom: 29px;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .carte-hero {
            background: rgba(255, 255, 255, 0.10);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 18px;
            padding: 30px;
            backdrop-filter: blur(6px);
        }

        .carte-hero .icone {
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            color: var(--bleu-fonce);
            background: var(--jaune);
            font-size: 28px;
            margin-bottom: 20px;
        }

        .carte-hero h2 {
            font-size: 23px;
            margin-bottom: 12px;
        }

        .carte-hero p {
            font-size: 14px;
            margin-bottom: 0;
        }

        .ruban {
            background: var(--jaune);
            color: #273446;
            text-align: center;
            padding: 12px 20px;
            font-size: 13px;
            font-weight: 700;
        }

        .section {
            padding: 76px 24px;
        }

        .section-grise {
            background: var(--fond);
        }

        .conteneur {
            max-width: 1160px;
            margin: auto;
        }

        .titre-section {
            text-align: center;
            max-width: 780px;
            margin: 0 auto 42px;
        }

        .surtitre {
            display: inline-block;
            color: var(--vert-fonce);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 10px;
        }

        .titre-section h2 {
            color: var(--bleu-fonce);
            font-size: clamp(27px, 3vw, 36px);
            line-height: 1.25;
            margin-bottom: 15px;
        }

        .titre-section p {
            color: #64748b;
            font-size: 15px;
        }

        .presentation {
            display: grid;
            grid-template-columns: 0.8fr 1.2fr;
            gap: 35px;
            align-items: stretch;
        }

        .presentation-visuel {
            background: linear-gradient(145deg, #e8f5ed, #e8f1fc);
            border-radius: 18px;
            padding: 35px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-bottom: 5px solid var(--vert);
        }

        .presentation-visuel img {
            display: block;
            max-width: 100%;
            max-height: 180px;
            object-fit: contain;
            margin: 0 auto 24px;
        }

        .presentation-visuel h3 {
            text-align: center;
            color: var(--bleu-fonce);
            font-size: 22px;
        }

        .presentation-texte h3 {
            color: var(--bleu-fonce);
            font-size: 23px;
            margin-bottom: 12px;
        }

        .presentation-texte p {
            color: #536174;
            margin-bottom: 15px;
            font-size: 15px;
        }

        .liste-missions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 22px;
        }

        .mission {
            padding: 18px;
            background: white;
            border: 1px solid #e3eaf0;
            border-radius: 10px;
            border-left: 4px solid var(--vert);
        }

        .mission:nth-child(2n) {
            border-left-color: var(--jaune);
        }

        .mission h4 {
            color: var(--bleu-fonce);
            font-size: 14px;
            margin-bottom: 6px;
        }

        .mission p {
            font-size: 13px;
            margin: 0;
        }

        .objectif {
            margin-top: 25px;
            padding: 20px;
            border-radius: 10px;
            background: #e9f5ed;
            border-left: 5px solid var(--vert);
        }

        .objectif strong {
            display: block;
            color: var(--vert-fonce);
            margin-bottom: 5px;
        }

        .logiciel-grille {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }

        .logiciel-texte h3 {
            font-size: 27px;
            color: var(--bleu-fonce);
            margin-bottom: 18px;
        }

        .logiciel-texte p {
            color: #536174;
            margin-bottom: 15px;
        }

        .logiciel-points {
            list-style: none;
            margin-top: 20px;
        }

        .logiciel-points li {
            padding: 9px 0 9px 30px;
            position: relative;
            color: #34445a;
        }

        .logiciel-points li::before {
            content: "✓";
            position: absolute;
            left: 0;
            top: 8px;
            color: var(--vert);
            font-weight: 900;
            font-size: 18px;
        }

        .logiciel-carte {
            padding: 32px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 17px;
            box-shadow: 0 12px 35px rgba(7, 55, 99, 0.08);
        }

        .logiciel-carte h3 {
            color: var(--bleu-fonce);
            margin-bottom: 20px;
            font-size: 21px;
        }

        .avantage {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            padding: 17px 0;
            border-bottom: 1px solid #edf1f5;
        }

        .avantage:last-child {
            border-bottom: 0;
        }

        .avantage-icone {
            min-width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            color: var(--bleu-fonce);
            background: #e7f1fb;
            font-size: 20px;
        }

        .avantage h4 {
            color: var(--bleu-fonce);
            font-size: 15px;
            margin-bottom: 4px;
        }

        .avantage p {
            color: #64748b;
            font-size: 13px;
            margin: 0;
        }

        .etapes {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .etape {
            background: white;
            border: 1px solid #e4eaf0;
            border-radius: 14px;
            padding: 25px 21px;
            position: relative;
        }

        .numero-etape {
            width: 43px;
            height: 43px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: var(--bleu);
            color: white;
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 18px;
        }

        .etape:nth-child(2) .numero-etape {
            background: var(--vert);
        }

        .etape:nth-child(3) .numero-etape {
            background: #d8ae00;
            color: #172b45;
        }

        .etape:nth-child(4) .numero-etape {
            background: var(--bleu-fonce);
        }

        .etape h3 {
            color: var(--bleu-fonce);
            font-size: 17px;
            margin-bottom: 9px;
        }

        .etape p {
            font-size: 13px;
            color: #64748b;
        }

        .offres-grille {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .offre {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.03);
        }

        .offre-badge {
            display: inline-block;
            align-self: flex-start;
            padding: 5px 10px;
            background: #e8f5ed;
            color: var(--vert-fonce);
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            margin-bottom: 14px;
        }

        .offre h3 {
            color: var(--bleu-fonce);
            font-size: 19px;
            margin-bottom: 12px;
        }

        .offre p {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 17px;
        }

        .offre-infos {
            margin-top: auto;
            padding: 14px 0;
            border-top: 1px solid #edf1f5;
            color: #526174;
            font-size: 12px;
        }

        .offre .bouton {
            width: 100%;
        }

        .aucune-offre {
            grid-column: 1 / -1;
            padding: 30px;
            background: white;
            border-radius: 12px;
            text-align: center;
            color: #64748b;
            border: 1px dashed #cbd5e1;
        }

        .appel-action {
            padding: 58px 24px;
            background: linear-gradient(120deg, var(--vert-fonce), var(--bleu));
            color: white;
            text-align: center;
        }

        .appel-action h2 {
            font-size: clamp(25px, 3vw, 34px);
            margin-bottom: 13px;
        }

        .appel-action p {
            max-width: 700px;
            margin: 0 auto 24px;
            color: #e5effa;
        }

        .pied-page {
            background: #082b4c;
            color: #dce7f2;
            padding: 38px 24px 20px;
        }

        .pied-contenu {
            max-width: 1160px;
            margin: auto;
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr;
            gap: 35px;
        }

        .pied-page h3,
        .pied-page h4 {
            color: white;
            margin-bottom: 12px;
        }

        .pied-page p,
        .pied-page a {
            font-size: 13px;
            color: #c5d4e4;
        }

        .pied-page a:hover {
            color: var(--jaune);
        }

        .pied-liens {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .copyright {
            max-width: 1160px;
            margin: 30px auto 0;
            padding-top: 18px;
            border-top: 1px solid #294763;
            text-align: center;
            font-size: 12px;
            color: #b7c8d9;
        }

        .copyright span {
            color: var(--jaune);
        }

        @media (max-width: 1000px) {
            .entete-interieur {
                flex-wrap: wrap;
            }

            .navigation {
                order: 3;
                width: 100%;
                gap: 14px;
            }

            .hero-interieur {
                grid-template-columns: 1fr;
                gap: 30px;
                padding-top: 60px;
            }

            .carte-hero {
                max-width: 600px;
            }

            .presentation,
            .logiciel-grille {
                grid-template-columns: 1fr;
            }

            .etapes {
                grid-template-columns: repeat(2, 1fr);
            }

            .offres-grille {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .entete-interieur {
                padding: 12px 15px;
            }

            .identite {
                min-width: 0;
            }

            .identite img {
                width: 48px;
                height: 48px;
            }

            .identite strong {
                font-size: 16px;
            }

            .identite small {
                font-size: 10px;
            }

            .actions-entete {
                width: 100%;
            }

            .actions-entete .bouton {
                flex: 1;
                padding: 10px;
            }

            .navigation {
                gap: 10px 15px;
            }

            .navigation a {
                font-size: 12px;
            }

            .hero-interieur {
                padding: 48px 20px;
            }

            .hero h1 {
                font-size: 34px;
            }

            .hero p {
                font-size: 15px;
            }

            .section {
                padding: 52px 18px;
            }

            .liste-missions,
            .etapes,
            .offres-grille,
            .pied-contenu {
                grid-template-columns: 1fr;
            }

            .presentation-visuel {
                padding: 25px;
            }

            .logiciel-carte {
                padding: 22px;
            }

            .hero-actions .bouton {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="barre-couleurs"></div>

<!-- EN-TÊTE -->
<header class="entete">
    <div class="entete-interieur">

        <a href="accueil.php" class="identite">
            <img src="../images/dglp.jpg" alt="Logo de la DGLP">
            <div>
                <strong>DGLP</strong>
                <small>Lutte contre la pauvreté</small>
            </div>
        </a>

        <nav class="navigation" aria-label="Navigation principale">
            <a href="#accueil">Accueil</a>
            <a href="#dglp">La DGLP</a>
            <a href="#logiciel">Le logiciel</a>
            <a href="#fonctionnement">Comment ça marche ?</a>
            <a href="#offres">Offres de services</a>
        </nav>

        <div class="actions-entete">
            <a href="connexion.php" class="bouton bouton-bleu">
                Se connecter
            </a>

            <a href="inscription.php" class="bouton bouton-vert">
                S'inscrire
            </a>
        </div>

    </div>
</header>

<main>

    <!-- BANNIÈRE PRINCIPALE -->
    <section class="hero" id="accueil">
        <div class="hero-interieur">

            <div>
                <span class="etiquette">
                    République Gabonaise
                </span>

                <h1>
                    Des démarches plus simples pour
                    <span>accéder aux offres de services.</span>
                </h1>

                <p>
                    Bienvenue sur la plateforme numérique de gestion des
                    candidatures de la Direction Générale de la Lutte contre
                    la Pauvreté. Consultez les offres, constituez votre dossier
                    et suivez votre candidature depuis un espace personnel.
                </p>

                <div class="hero-actions">
                    <a href="offres.php" class="bouton bouton-blanc">
                        Consulter les offres
                    </a>

                    <a href="inscription.php" class="bouton bouton-vert">
                        Créer un compte
                    </a>
                </div>
            </div>

            <div class="carte-hero">
                <div class="icone">✓</div>

                <h2>Un parcours simplifié</h2>

                <p>
                    Retrouvez les principales étapes de votre candidature
                    dans un même espace : inscription, dossier, dépôt des
                    pièces justificatives et suivi du traitement.
                </p>
            </div>

        </div>
    </section>

    <div class="ruban">
        Une plateforme de service public conçue pour faciliter les démarches
        et améliorer le suivi des candidatures.
    </div>

    <!-- PRÉSENTATION DE LA DGLP -->
    <section class="section" id="dglp">
        <div class="conteneur">

            <div class="titre-section">
                <span class="surtitre">Présentation institutionnelle</span>

                <h2>Qu'est-ce que la DGLP ?</h2>

                <p>
                    Comprendre le rôle de la Direction Générale de la Lutte
                    contre la Pauvreté et son action en faveur des populations.
                </p>
            </div>

            <div class="presentation">

                <div class="presentation-visuel">
                    <img
                        src="../images/ministere.jpg"
                        alt="Ministère de tutelle"
                        onerror="this.style.display='none';"
                    >

                    <h3>
                        Direction Générale de la Lutte contre la Pauvreté
                    </h3>
                </div>

                <div class="presentation-texte">

                    <h3>Définition et rôle</h3>

                    <p>
                        La Direction Générale de la Lutte contre la Pauvreté
                        (DGLP) est une structure administrative de l'État
                        gabonais chargée de contribuer à la conception,
                        à la coordination et au suivi des actions publiques
                        visant à réduire la pauvreté.
                    </p>

                    <p>
                        Elle intervient notamment dans la définition des
                        stratégies de réduction de la pauvreté, l'identification
                        des besoins des populations, l'appui aux initiatives
                        pertinentes et la coordination avec les acteurs publics
                        et non étatiques concernés.
                    </p>

                    <h3>Ses principales missions</h3>

                    <div class="liste-missions">

                        <article class="mission">
                            <h4>Stratégie de réduction</h4>
                            <p>
                                Contribuer à l'élaboration des stratégies et
                                plans d'action de réduction de la pauvreté.
                            </p>
                        </article>

                        <article class="mission">
                            <h4>Coordination et suivi</h4>
                            <p>
                                Coordonner et suivre les projets et actions
                                contribuant à la réduction de la pauvreté.
                            </p>
                        </article>

                        <article class="mission">
                            <h4>Appui aux initiatives</h4>
                            <p>
                                Identifier et accompagner les organisations
                                qui œuvrent dans la lutte contre la pauvreté.
                            </p>
                        </article>

                        <article class="mission">
                            <h4>Études et statistiques</h4>
                            <p>
                                Contribuer à la collecte des données, aux
                                études et au suivi des situations de pauvreté.
                            </p>
                        </article>

                    </div>

                    <div class="objectif">
                        <strong>Objectif général</strong>

                        Contribuer à la mise en œuvre des politiques et
                        initiatives destinées à améliorer les conditions de
                        vie des populations et à réduire les situations de
                        vulnérabilité et de précarité.
                    </div>

                </div>
            </div>
        </div>
    </section>

    <!-- PRÉSENTATION DU LOGICIEL -->
    <section class="section section-grise" id="logiciel">
        <div class="conteneur">

            <div class="titre-section">
                <span class="surtitre">Transformation numérique</span>

                <h2>Le logiciel de gestion dématérialisée des candidatures</h2>

                <p>
                    Une solution numérique destinée à faciliter la gestion
                    des candidatures et le suivi du processus d'accès aux
                    offres de services du programme d'automatisation.
                </p>
            </div>

            <div class="logiciel-grille">

                <div class="logiciel-texte">
                    <h3>Pourquoi cette plateforme ?</h3>

                    <p>
                        Le logiciel a pour objectif de centraliser les
                        informations des candidats et de permettre le dépôt
                        des dossiers en ligne. Il facilite le suivi des
                        candidatures et aide les agents habilités à traiter
                        les dossiers à travers un espace administratif.
                    </p>

                    <p>
                        Il s'inscrit dans une démarche de dématérialisation
                        des procédures, afin de limiter la dépendance aux
                        dossiers papier et de rendre les différentes étapes
                        plus faciles à suivre.
                    </p>

                    <ul class="logiciel-points">
                        <li>Création et gestion d'un compte candidat.</li>
                        <li>Constitution du dossier personnel et socio-économique.</li>
                        <li>Consultation des offres et dépôt des candidatures.</li>
                        <li>Transmission des pièces justificatives.</li>
                        <li>Suivi du statut de la candidature.</li>
                        <li>Examen, classement et gestion des décisions par les agents autorisés.</li>
                    </ul>
                </div>

                <div class="logiciel-carte">

                    <h3>Les avantages de la plateforme</h3>

                    <div class="avantage">
                        <div class="avantage-icone">⌨</div>
                        <div>
                            <h4>Démarches centralisées</h4>
                            <p>
                                Les principales opérations sont accessibles
                                depuis une même plateforme.
                            </p>
                        </div>
                    </div>

                    <div class="avantage">
                        <div class="avantage-icone">▣</div>
                        <div>
                            <h4>Suivi des candidatures</h4>
                            <p>
                                Le candidat peut consulter l'état de son
                                dossier depuis son espace personnel.
                            </p>
                        </div>
                    </div>

                    <div class="avantage">
                        <div class="avantage-icone">✓</div>
                        <div>
                            <h4>Traitement organisé</h4>
                            <p>
                                Les agents habilités disposent d'outils
                                pour examiner les dossiers et enregistrer
                                les décisions.
                            </p>
                        </div>
                    </div>

                    <div class="avantage">
                        <div class="avantage-icone">🔒</div>
                        <div>
                            <h4>Accès par compte</h4>
                            <p>
                                Les espaces personnels et administratifs
                                sont accessibles selon les droits prévus
                                par l'application.
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <!-- FONCTIONNEMENT -->
    <section class="section" id="fonctionnement">
        <div class="conteneur">

            <div class="titre-section">
                <span class="surtitre">Guide du candidat</span>

                <h2>Comment utiliser la plateforme ?</h2>

                <p>
                    Suivez les étapes ci-dessous pour préparer et suivre
                    votre candidature.
                </p>
            </div>

            <div class="etapes">

                <article class="etape">
                    <div class="numero-etape">1</div>
                    <h3>Créer un compte</h3>
                    <p>
                        Inscrivez-vous avec vos informations de connexion
                        afin d'accéder à votre espace personnel.
                    </p>
                </article>

                <article class="etape">
                    <div class="numero-etape">2</div>
                    <h3>Compléter son dossier</h3>
                    <p>
                        Renseignez vos informations personnelles et
                        socio-économiques, puis ajoutez les pièces demandées.
                    </p>
                </article>

                <article class="etape">
                    <div class="numero-etape">3</div>
                    <h3>Déposer sa candidature</h3>
                    <p>
                        Consultez les offres ouvertes, choisissez celle qui
                        vous intéresse et transmettez votre candidature.
                    </p>
                </article>

                <article class="etape">
                    <div class="numero-etape">4</div>
                    <h3>Suivre son dossier</h3>
                    <p>
                        Consultez le statut de votre candidature et les
                        informations disponibles dans votre espace.
                    </p>
                </article>

            </div>
        </div>
    </section>

    <!-- OFFRES DISPONIBLES -->
    <section class="section section-grise" id="offres">
        <div class="conteneur">

            <div class="titre-section">
                <span class="surtitre">Opportunités</span>

                <h2>Offres de services disponibles</h2>

                <p>
                    Découvrez les offres actuellement ouvertes et consultez
                    leurs informations avant de déposer une candidature.
                </p>
            </div>

            <div class="offres-grille">

                <?php if (!empty($offres)): ?>

                    <?php foreach ($offres as $offre): ?>

                        <article class="offre">

                            <span class="offre-badge">
                                Offre ouverte
                            </span>

                            <h3>
                                <?= e($offre["description"]) ?>
                            </h3>

                            <p>
                                Consultez les informations et les conditions
                                de l'offre avant de préparer votre dossier.
                            </p>

                            <div class="offre-infos">
                                <div>
                                    Date limite :
                                    <strong>
                                        <?= e(
                                            date(
                                                "d/m/Y",
                                                strtotime(
                                                    $offre["date_limite_candidature"]
                                                )
                                            )
                                        ) ?>
                                    </strong>
                                </div>

                                <div>
                                    Places annoncées :
                                    <strong>
                                        <?= e($offre["nombre_place"]) ?>
                                    </strong>
                                </div>
                            </div>

                            <a
                                href="offres.php"
                                class="bouton bouton-bleu"
                            >
                                Consulter l'offre
                            </a>

                        </article>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="aucune-offre">
                        <h3>Aucune offre ouverte pour le moment</h3>

                        <p>
                            Revenez prochainement consulter cette page
                            pour découvrir les offres disponibles.
                        </p>

                        <br>

                        <a href="offres.php" class="bouton bouton-bleu">
                            Accéder aux offres
                        </a>
                    </div>

                <?php endif; ?>

            </div>

            <div style="text-align:center; margin-top:30px;">
                <a href="offres.php" class="bouton bouton-vert">
                    Voir toutes les offres
                </a>
            </div>

        </div>
    </section>

    <!-- APPEL À L'ACTION -->
    <section class="appel-action">
        <h2>Prêt à commencer votre démarche ?</h2>

        <p>
            Créez votre compte pour compléter votre dossier, consulter
            les offres disponibles et suivre vos candidatures.
        </p>

        <a href="inscription.php" class="bouton bouton-blanc">
            Créer mon compte
        </a>

        <a href="connexion.php"
           class="bouton"
           style="border:2px solid white; color:white; margin-left:8px;">
            Accéder à mon espace
        </a>
    </section>

</main>

<!-- PIED DE PAGE -->
<footer class="pied-page">
    <div class="pied-contenu">

        <div>
            <h3>DGLP</h3>
            <p>
                Direction Générale de la Lutte contre la Pauvreté.
                Plateforme de gestion dématérialisée des candidatures
                et du processus d'accès aux offres de services.
            </p>
        </div>

        <div>
            <h4>Navigation</h4>
            <div class="pied-liens">
                <a href="#dglp">Présentation de la DGLP</a>
                <a href="#logiciel">Présentation du logiciel</a>
                <a href="#fonctionnement">Guide du candidat</a>
                <a href="offres.php">Offres de services</a>
            </div>
        </div>

        <div>
            <h4>Espace candidat</h4>
            <div class="pied-liens">
                <a href="inscription.php">Créer un compte</a>
                <a href="connexion.php">Se connecter</a>
                <a href="offres.php">Consulter les offres</a>
            </div>
        </div>

    </div>

    <div class="copyright">
        <span>Vert • Jaune • Bleu</span>
        <br>
        DGLP © <?= date("Y") ?> — Tous droits réservés.
    </div>
</footer>

</body>
</html>
