<?php

/**
 * Installation d'Inscription 4.
 *
 * Les fonctions historiques restent déléguées à Inscription 3 afin de
 * conserver la configuration et les migrations existantes.
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('inscription3_administrations');

function inscription4_upgrade($nom_meta_base_version, $version_cible) {
	return inscription3_upgrade($nom_meta_base_version, $version_cible);
}

function inscription4_vider_tables($nom_meta_base_version) {
	return inscription3_vider_tables($nom_meta_base_version);
}
