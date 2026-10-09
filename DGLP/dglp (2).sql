-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : jeu. 24 sep. 2026 à 12:51
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `dglp`
--

-- --------------------------------------------------------

--
-- Structure de la table `admin`
--

CREATE TABLE `admin` (
  `id_admin` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `mot_de_passe` varchar(255) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'agent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `admin`
--

INSERT INTO `admin` (`id_admin`, `nom`, `prenom`, `email`, `mot_de_passe`, `role`) VALUES
(1, 'Administrateur', 'Principal', 'admin@dglp.ga', '$2y$10$BjUBv2ryu088vuaYTX0wgOy3CrIZpti5RdARlJHf2JZxMTrQRtHU2', 'admin');

-- --------------------------------------------------------

--
-- Structure de la table `candidat`
--

CREATE TABLE `candidat` (
  `id_candidat` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `telephone` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `candidat`
--

INSERT INTO `candidat` (`id_candidat`, `nom`, `prenom`, `ville`, `date_naissance`, `province`, `telephone`) VALUES
(1, 'Mvou', 'Jarul', 'libreville', '2000-06-03', 'Estuaire', NULL),
(2, 'NGANKOUA', 'Jeremy', 'Franceville', '2004-07-25', 'Haut-Oguooé', NULL),
(3, 'NGOUNDA', 'Rod', 'libreville', '2000-03-03', 'Estuaire', '066156262'),
(4, 'mjjmj', 'mkmj', 'libreville', '2222-02-22', 'Estuaire', '00000098');

-- --------------------------------------------------------

--
-- Structure de la table `candidature`
--

CREATE TABLE `candidature` (
  `id_candidature` int(11) NOT NULL,
  `date_candidature` date NOT NULL,
  `statut` varchar(50) NOT NULL,
  `id_candidat` int(11) NOT NULL,
  `id_offre` int(11) NOT NULL,
  `numero_candidature` varchar(50) DEFAULT NULL,
  `date_accuse_reception` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `candidature`
--

INSERT INTO `candidature` (`id_candidature`, `date_candidature`, `statut`, `id_candidat`, `id_offre`, `numero_candidature`, `date_accuse_reception`) VALUES
(1, '2026-09-23', 'en attente', 3, 1, 'DGLP-2026-5EF6450E', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `candidature_piece`
--

CREATE TABLE `candidature_piece` (
  `id_candidature_piece` int(11) NOT NULL,
  `id_candidature` int(11) NOT NULL,
  `id_piece` int(11) NOT NULL,
  `fichier` varchar(255) NOT NULL,
  `date_depot` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `classement`
--

CREATE TABLE `classement` (
  `id_classement` int(11) NOT NULL,
  `id_candidature` int(11) NOT NULL,
  `rang` int(11) NOT NULL,
  `score` decimal(5,2) DEFAULT 0.00,
  `statut` varchar(50) DEFAULT NULL,
  `justification` text DEFAULT NULL,
  `date_classement` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `compte`
--

CREATE TABLE `compte` (
  `id_compte` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `id_candidat` int(11) NOT NULL,
  `statut` varchar(30) NOT NULL DEFAULT 'En attente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `compte`
--

INSERT INTO `compte` (`id_compte`, `email`, `mot_de_passe`, `id_candidat`, `statut`) VALUES
(1, 'rodnivenger@gmail.com', '$2y$10$JqlBoUVKRex/jbBlwt4thu6T1EBt3XpVcNI63BlYmRUBY1mXLwFo2', 1, 'En attente'),
(2, 'jeremyNGA@gmail.com', '$2y$10$rmgrwM3kdaTRoaJBAio4euD0c08w8fE9gMcPAmUk6JfbyrO6mxsWC', 2, 'En attente'),
(3, 'NGOUNDA@gmail.com', '$2y$10$Ow.gF87UTAE5pqBL7pgVMObh9p6cBjvWc0C.Pgq8qc0GWrXF2kcxa', 3, 'En attente'),
(4, 'NGOU@gmail.com', '$2y$10$Wsh7BfmFIPubwuJZFB21XenmKiOw.arhVB.HpVzjlSR/LtGSpXCeO', 4, 'En attente');

-- --------------------------------------------------------

--
-- Structure de la table `controle_eligibilite`
--

CREATE TABLE `controle_eligibilite` (
  `id_controle` int(11) NOT NULL,
  `id_candidature` int(11) NOT NULL,
  `score` decimal(5,2) DEFAULT 0.00,
  `statut` varchar(50) NOT NULL DEFAULT 'À examiner',
  `justification` text DEFAULT NULL,
  `date_controle` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `critere`
--

CREATE TABLE `critere` (
  `id_critere` int(11) NOT NULL,
  `id_offre` int(11) NOT NULL,
  `nom` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `valeur_min` decimal(12,2) DEFAULT NULL,
  `valeur_max` decimal(12,2) DEFAULT NULL,
  `valeur_attendue` varchar(255) DEFAULT NULL,
  `poids` decimal(5,2) DEFAULT 1.00,
  `obligatoire` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `decision`
--

CREATE TABLE `decision` (
  `id_decision` int(11) NOT NULL,
  `id_controle` int(11) NOT NULL,
  `id_admin` int(11) DEFAULT NULL,
  `decision_finale` varchar(50) NOT NULL,
  `motif` text DEFAULT NULL,
  `date_decision` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `dossier`
--

CREATE TABLE `dossier` (
  `id_dossier` int(11) NOT NULL,
  `nip` varchar(50) DEFAULT NULL,
  `carte_photo` varchar(255) DEFAULT NULL,
  `acte_naissance` varchar(255) DEFAULT NULL,
  `statut` varchar(50) NOT NULL DEFAULT 'incomplet',
  `id_candidat` int(11) NOT NULL,
  `id_admin` int(11) DEFAULT NULL,
  `situation_matrimoniale` varchar(100) DEFAULT NULL,
  `personn_a_charge` int(11) DEFAULT NULL,
  `situation_profesionnelle` varchar(100) DEFAULT NULL,
  `activite` varchar(100) DEFAULT NULL,
  `type_activite` varchar(100) DEFAULT NULL,
  `description_activite` text DEFAULT NULL,
  `revenu_mensuel` decimal(12,2) DEFAULT NULL,
  `qualification` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `dossier`
--

INSERT INTO `dossier` (`id_dossier`, `nip`, `carte_photo`, `acte_naissance`, `statut`, `id_candidat`, `id_admin`, `situation_matrimoniale`, `personn_a_charge`, `situation_profesionnelle`, `activite`, `type_activite`, `description_activite`, `revenu_mensuel`, `qualification`) VALUES
(1, '12142352', 'photo_3_1790170067.jpg', 'acte_naissance_3_1790170067.pdf', 'complet', 3, NULL, 'celibataire', 0, 'etudiant', 'aucune', 'autre', 'ok', NULL, NULL),
(2, '12142352', 'photo_4_1790239943.jpg', 'acte_naissance_4_1790239943.pdf', 'incomplet', 4, NULL, 'celibataire', 0, 'sans_emploi', 'aucune', '', 'IJHMKHL', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `historique`
--

CREATE TABLE `historique` (
  `id_historique` int(11) NOT NULL,
  `id_admin` int(11) DEFAULT NULL,
  `id_candidature` int(11) DEFAULT NULL,
  `action` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `date_action` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `offre`
--

CREATE TABLE `offre` (
  `id_offre` int(11) NOT NULL,
  `nom` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `date_creation` date NOT NULL,
  `date_limite_candidature` date NOT NULL,
  `nombre_place` int(11) NOT NULL,
  `critere` text DEFAULT NULL,
  `montant` decimal(12,2) DEFAULT NULL,
  `document_supplementaire` tinyint(1) DEFAULT 0,
  `statut` varchar(50) NOT NULL,
  `id_admin` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `offre`
--

INSERT INTO `offre` (`id_offre`, `nom`, `description`, `date_creation`, `date_limite_candidature`, `nombre_place`, `critere`, `montant`, `document_supplementaire`, `statut`, `id_admin`) VALUES
(1, '', 'klhkl', '2026-09-23', '2028-02-23', 38, 'AVOIR 18ans et plus', 2344000.00, 0, 'fermee', 1);

-- --------------------------------------------------------

--
-- Structure de la table `orientation`
--

CREATE TABLE `orientation` (
  `id_orientation` int(11) NOT NULL,
  `id_candidature` int(11) NOT NULL,
  `id_admin` int(11) DEFAULT NULL,
  `orientation` varchar(150) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `date_orientation` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `piece_candidature`
--

CREATE TABLE `piece_candidature` (
  `id_piece_candidature` int(11) NOT NULL,
  `id_piece` int(11) NOT NULL,
  `id_candidature` int(11) NOT NULL,
  `fichier` varchar(255) NOT NULL,
  `statut` varchar(50) DEFAULT 'En attente',
  `date_depot` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `piece_complementaire`
--

CREATE TABLE `piece_complementaire` (
  `id_piece` int(11) NOT NULL,
  `nom_piece` varchar(150) NOT NULL,
  `obligatoire` tinyint(1) DEFAULT 0,
  `id_offre` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `suivi_parcours`
--

CREATE TABLE `suivi_parcours` (
  `id_suivi` int(11) NOT NULL,
  `id_candidature` int(11) NOT NULL,
  `id_admin` int(11) DEFAULT NULL,
  `type_suivi` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `statut` varchar(50) DEFAULT 'En cours',
  `date_suivi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `verification`
--

CREATE TABLE `verification` (
  `id_verification` int(11) NOT NULL,
  `id_controle` int(11) NOT NULL,
  `id_critere` int(11) NOT NULL,
  `resultat` varchar(50) NOT NULL DEFAULT 'Non vérifié',
  `observation` text DEFAULT NULL,
  `date_verification` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `uq_admin_email` (`email`);

--
-- Index pour la table `candidat`
--
ALTER TABLE `candidat`
  ADD PRIMARY KEY (`id_candidat`);

--
-- Index pour la table `candidature`
--
ALTER TABLE `candidature`
  ADD PRIMARY KEY (`id_candidature`),
  ADD UNIQUE KEY `uq_candidat_offre` (`id_candidat`,`id_offre`),
  ADD UNIQUE KEY `numero_candidature` (`numero_candidature`),
  ADD UNIQUE KEY `uq_numero_candidature` (`numero_candidature`),
  ADD KEY `fk_candidature_offre` (`id_offre`);

--
-- Index pour la table `candidature_piece`
--
ALTER TABLE `candidature_piece`
  ADD PRIMARY KEY (`id_candidature_piece`),
  ADD KEY `fk_cp_candidature` (`id_candidature`),
  ADD KEY `fk_cp_piece` (`id_piece`);

--
-- Index pour la table `classement`
--
ALTER TABLE `classement`
  ADD PRIMARY KEY (`id_classement`),
  ADD UNIQUE KEY `id_candidature` (`id_candidature`);

--
-- Index pour la table `compte`
--
ALTER TABLE `compte`
  ADD PRIMARY KEY (`id_compte`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `id_candidat` (`id_candidat`);

--
-- Index pour la table `controle_eligibilite`
--
ALTER TABLE `controle_eligibilite`
  ADD PRIMARY KEY (`id_controle`),
  ADD KEY `fk_controle_candidature` (`id_candidature`);

--
-- Index pour la table `critere`
--
ALTER TABLE `critere`
  ADD PRIMARY KEY (`id_critere`),
  ADD KEY `fk_critere_offre` (`id_offre`);

--
-- Index pour la table `decision`
--
ALTER TABLE `decision`
  ADD PRIMARY KEY (`id_decision`),
  ADD UNIQUE KEY `id_controle` (`id_controle`),
  ADD KEY `fk_decision_admin` (`id_admin`);

--
-- Index pour la table `dossier`
--
ALTER TABLE `dossier`
  ADD PRIMARY KEY (`id_dossier`),
  ADD UNIQUE KEY `id_candidat` (`id_candidat`),
  ADD KEY `fk_dossier_admin` (`id_admin`);

--
-- Index pour la table `historique`
--
ALTER TABLE `historique`
  ADD PRIMARY KEY (`id_historique`),
  ADD KEY `fk_historique_admin` (`id_admin`),
  ADD KEY `fk_historique_candidature` (`id_candidature`);

--
-- Index pour la table `offre`
--
ALTER TABLE `offre`
  ADD PRIMARY KEY (`id_offre`),
  ADD KEY `fk_offre_admin` (`id_admin`);

--
-- Index pour la table `orientation`
--
ALTER TABLE `orientation`
  ADD PRIMARY KEY (`id_orientation`),
  ADD KEY `fk_orientation_candidature` (`id_candidature`),
  ADD KEY `fk_orientation_admin` (`id_admin`);

--
-- Index pour la table `piece_candidature`
--
ALTER TABLE `piece_candidature`
  ADD PRIMARY KEY (`id_piece_candidature`),
  ADD KEY `fk_pc_piece` (`id_piece`),
  ADD KEY `fk_pc_candidature` (`id_candidature`);

--
-- Index pour la table `piece_complementaire`
--
ALTER TABLE `piece_complementaire`
  ADD PRIMARY KEY (`id_piece`),
  ADD KEY `fk_piece_offre` (`id_offre`);

--
-- Index pour la table `suivi_parcours`
--
ALTER TABLE `suivi_parcours`
  ADD PRIMARY KEY (`id_suivi`),
  ADD KEY `fk_suivi_candidature` (`id_candidature`),
  ADD KEY `fk_suivi_admin` (`id_admin`);

--
-- Index pour la table `verification`
--
ALTER TABLE `verification`
  ADD PRIMARY KEY (`id_verification`),
  ADD KEY `fk_verification_controle` (`id_controle`),
  ADD KEY `fk_verification_critere` (`id_critere`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `admin`
--
ALTER TABLE `admin`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `candidat`
--
ALTER TABLE `candidat`
  MODIFY `id_candidat` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `candidature`
--
ALTER TABLE `candidature`
  MODIFY `id_candidature` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `candidature_piece`
--
ALTER TABLE `candidature_piece`
  MODIFY `id_candidature_piece` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `classement`
--
ALTER TABLE `classement`
  MODIFY `id_classement` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `compte`
--
ALTER TABLE `compte`
  MODIFY `id_compte` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `controle_eligibilite`
--
ALTER TABLE `controle_eligibilite`
  MODIFY `id_controle` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `critere`
--
ALTER TABLE `critere`
  MODIFY `id_critere` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `decision`
--
ALTER TABLE `decision`
  MODIFY `id_decision` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `dossier`
--
ALTER TABLE `dossier`
  MODIFY `id_dossier` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `historique`
--
ALTER TABLE `historique`
  MODIFY `id_historique` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `offre`
--
ALTER TABLE `offre`
  MODIFY `id_offre` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `orientation`
--
ALTER TABLE `orientation`
  MODIFY `id_orientation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `piece_candidature`
--
ALTER TABLE `piece_candidature`
  MODIFY `id_piece_candidature` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `piece_complementaire`
--
ALTER TABLE `piece_complementaire`
  MODIFY `id_piece` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `suivi_parcours`
--
ALTER TABLE `suivi_parcours`
  MODIFY `id_suivi` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `verification`
--
ALTER TABLE `verification`
  MODIFY `id_verification` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `candidature`
--
ALTER TABLE `candidature`
  ADD CONSTRAINT `fk_candidature_candidat` FOREIGN KEY (`id_candidat`) REFERENCES `candidat` (`id_candidat`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_candidature_offre` FOREIGN KEY (`id_offre`) REFERENCES `offre` (`id_offre`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `candidature_piece`
--
ALTER TABLE `candidature_piece`
  ADD CONSTRAINT `fk_cp_candidature` FOREIGN KEY (`id_candidature`) REFERENCES `candidature` (`id_candidature`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cp_piece` FOREIGN KEY (`id_piece`) REFERENCES `piece_complementaire` (`id_piece`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `classement`
--
ALTER TABLE `classement`
  ADD CONSTRAINT `fk_classement_candidature` FOREIGN KEY (`id_candidature`) REFERENCES `candidature` (`id_candidature`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `compte`
--
ALTER TABLE `compte`
  ADD CONSTRAINT `fk_compte_candidat` FOREIGN KEY (`id_candidat`) REFERENCES `candidat` (`id_candidat`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `controle_eligibilite`
--
ALTER TABLE `controle_eligibilite`
  ADD CONSTRAINT `fk_controle_candidature` FOREIGN KEY (`id_candidature`) REFERENCES `candidature` (`id_candidature`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `critere`
--
ALTER TABLE `critere`
  ADD CONSTRAINT `fk_critere_offre` FOREIGN KEY (`id_offre`) REFERENCES `offre` (`id_offre`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `decision`
--
ALTER TABLE `decision`
  ADD CONSTRAINT `fk_decision_admin` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id_admin`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_decision_controle` FOREIGN KEY (`id_controle`) REFERENCES `controle_eligibilite` (`id_controle`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `dossier`
--
ALTER TABLE `dossier`
  ADD CONSTRAINT `fk_dossier_admin` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id_admin`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dossier_candidat` FOREIGN KEY (`id_candidat`) REFERENCES `candidat` (`id_candidat`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `historique`
--
ALTER TABLE `historique`
  ADD CONSTRAINT `fk_historique_admin` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id_admin`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historique_candidature` FOREIGN KEY (`id_candidature`) REFERENCES `candidature` (`id_candidature`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `offre`
--
ALTER TABLE `offre`
  ADD CONSTRAINT `fk_offre_admin` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id_admin`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `orientation`
--
ALTER TABLE `orientation`
  ADD CONSTRAINT `fk_orientation_admin` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id_admin`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orientation_candidature` FOREIGN KEY (`id_candidature`) REFERENCES `candidature` (`id_candidature`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `piece_candidature`
--
ALTER TABLE `piece_candidature`
  ADD CONSTRAINT `fk_pc_candidature` FOREIGN KEY (`id_candidature`) REFERENCES `candidature` (`id_candidature`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pc_piece` FOREIGN KEY (`id_piece`) REFERENCES `piece_complementaire` (`id_piece`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `piece_complementaire`
--
ALTER TABLE `piece_complementaire`
  ADD CONSTRAINT `fk_piece_offre` FOREIGN KEY (`id_offre`) REFERENCES `offre` (`id_offre`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `suivi_parcours`
--
ALTER TABLE `suivi_parcours`
  ADD CONSTRAINT `fk_suivi_admin` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id_admin`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_suivi_candidature` FOREIGN KEY (`id_candidature`) REFERENCES `candidature` (`id_candidature`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `verification`
--
ALTER TABLE `verification`
  ADD CONSTRAINT `fk_verification_controle` FOREIGN KEY (`id_controle`) REFERENCES `controle_eligibilite` (`id_controle`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_verification_critere` FOREIGN KEY (`id_critere`) REFERENCES `critere` (`id_critere`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
