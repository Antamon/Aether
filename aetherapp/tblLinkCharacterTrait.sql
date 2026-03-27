DROP TABLE IF EXISTS `tblLinkCharacterTrait`;

CREATE TABLE `tblLinkCharacterTrait` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idCharacter` int(11) NOT NULL,
  `idTrait` int(11) NOT NULL,
  `rankValue` int(11) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tblLinkCharacterTrait_character_trait` (`idCharacter`, `idTrait`),
  KEY `idx_tblLinkCharacterTrait_character` (`idCharacter`),
  KEY `idx_tblLinkCharacterTrait_trait` (`idTrait`),
  CONSTRAINT `fk_tblLinkCharacterTrait_character`
    FOREIGN KEY (`idCharacter`) REFERENCES `tblCharacter` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_tblLinkCharacterTrait_trait`
    FOREIGN KEY (`idTrait`) REFERENCES `tblTrait` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
