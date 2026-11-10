-- VoteCampus : script de creation de la base de donnees

CREATE DATABASE IF NOT EXISTS votecampus
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE votecampus;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  mot_de_passe VARCHAR(255) NOT NULL,
  universite VARCHAR(150) NOT NULL,
  filiere VARCHAR(150) NOT NULL,
  classe VARCHAR(100) NOT NULL,
  role ENUM('etudiant', 'enseignant') NOT NULL,
  est_valide TINYINT(1) NOT NULL DEFAULT 1,
  est_premier_enseignant TINYINT(1) NOT NULL DEFAULT 0,
  date_inscription DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table des elections
CREATE TABLE IF NOT EXISTS elections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titre VARCHAR(200) NOT NULL,
  type ENUM('publique', 'privee') NOT NULL DEFAULT 'publique',
  classe VARCHAR(100) NOT NULL,
  image VARCHAR(255) DEFAULT NULL,
  createur_id INT NOT NULL,
  date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (createur_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table des candidats
CREATE TABLE IF NOT EXISTS candidats (
  id INT AUTO_INCREMENT PRIMARY KEY,
  election_id INT NOT NULL,
  nom VARCHAR(150) NOT NULL,
  FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table des votes (un seul vote par utilisateur et par election)
CREATE TABLE IF NOT EXISTS votes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  election_id INT NOT NULL,
  candidat_id INT NOT NULL,
  user_id INT NOT NULL,
  date_vote DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY un_vote_par_election (election_id, user_id),
  FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
  FOREIGN KEY (candidat_id) REFERENCES candidats(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
