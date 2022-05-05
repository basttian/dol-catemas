DROP TABLE IF EXISTS `llx_calculo_categorias`;

CREATE TABLE `llx_calculo_categorias` (
	
	`rowid` int(11) NOT NULL AUTO_INCREMENT,
	
	`fk_categorie` int(11) NOT NULL,
	
	`porcentaje` double DEFAULT NULL,
	
	`fecha` int(11)DEFAULT NULL,
  
	PRIMARY KEY (`rowid`),
  
	UNIQUE fk_categorie(fk_categorie)

)ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;