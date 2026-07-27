<?php
/**
 * Plugin Inscription3 pour SPIP
 * © cmtmt, BoOz, kent1
 * Licence GPL v3
 *
 * Fonctions d'installation et de désinstallation du plugin
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Fonction d'installation et de mise à jour du plugin
 * @return
 */
function inscription3_upgrade($nom_meta_base_version, $version_cible) {
	include_spip('inc/config');

	/**
	 *  A t on une meta d'installation déjà?
	 */
	$inscription3_meta_brute = $GLOBALS['meta']['inscription3'] ?? false;
	$inscription2_meta = lire_config('inscription2');
	$configuration_historique_invalide = (
		$inscription3_meta_brute
		&& !is_array(@unserialize($inscription3_meta_brute))
	);

	/**
	 * Certaines montées de version ont oublié de corriger la meta de I2
	 * si ce n'est pas un array alors il faut supprimer la meta pour la réinstaller
	 */
	if ($configuration_historique_invalide) {
		effacer_meta('inscription3');
	}
	$inscription3_meta = lire_config('inscription3');

	/**
	 * Inscription2 semble installé, on tranfère sa configuration vers inscription3
	 */
	if (isset($inscription2_meta) and is_array($inscription2_meta)) {
		ecrire_meta('inscription3', $inscription2_meta);
		$inscription3_meta = $inscription2_meta;
		effacer_meta('inscription2');
	}

	include_spip('inc/cextras');
	include_spip('base/inscription3');

	$maj = array();

	$maj['create'] = array();
	if (!is_array($inscription3_meta)) {
		$maj['create'][] = array('ecrire_meta','inscription3',serialize(array(
						'nom_fiche_mod' => 'on',
						'nom_table' => 'on',
						'email_fiche_mod' => 'on',
						'email_table' => 'on',
						'pass_fiche_mod' => 'on',
						'bio_fiche_mod' => 'on',
						'login_fiche_mod' => 'on',
						'nom_site_fiche_mod' => 'on',
						'url_site_fiche_mod' => 'on',
						'nom_fiche_mod' => 'on',
						'statut_nouveau' => '6forum',
						'statut_interne' => ''
					)));
	}

	$maj['create'][] = array('i3_migrer_pays');
	cextras_api_upgrade(inscription3_declarer_champs_extras(), $maj['create']);
	$maj['create'][] = array('inscription4_migrer_configuration_cextras');
	if ($configuration_historique_invalide) {
		$maj['create'][] = array('inscription3_transfert_infos_auteurs');
	}

	$maj['3.0.2'] = array();
	$maj['3.1.0'] = array(
		array('i3_migrer_pays'),
	);
	$maj['4.0.0'] = array(
		array('inscription4_migrer_configuration_cextras'),
	);
	$maj['4.0.1'] = array(
		array('i3_migrer_pays'),
	);
	$maj['4.1.0'] = array(
		array('inscription4_migrer_cextras_options_natives'),
	);

	include_spip('base/upgrade');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);
}

/**
 * Convertit les réglages plats historiques des Champs Extras.
 */
function inscription4_migrer_configuration_cextras() {
	include_spip('inc/config');
	include_spip('formulaires/inscription3_cextras_fonctions');
	$config = lire_config('inscription3', array());
	$nouvelle = lire_config('inscription3/cextras_inscription', array());
	$disponibles = array();

	foreach (inscription4_cextras_liste_configurable() as $cle => $saisie) {
		$nom = $saisie['options']['nom'] ?? $cle;
		if ($nom) {
			$disponibles[$nom] = $saisie;
		}
	}
	$nouvelle = array_intersect_key($nouvelle, $disponibles);

	foreach ($config as $cle => $valeur) {
		if (
			preg_match('/^(.+)_nocreation$/', $cle, $match)
			&& !preg_match('/_(?:fiche|table|obligatoire)_nocreation$/', $cle)
			&& isset($disponibles[$match[1]])
		) {
			$nom = $match[1];
			if (!isset($nouvelle[$nom])) {
				$saisie = $disponibles[$nom];
				$nouvelle[$nom] = array(
					'afficher' => ($valeur === 'on') ? 'on' : '',
					'fiche' => (($config[$nom . '_fiche_nocreation'] ?? '') === 'on') ? 'on' : '',
					'table' => (($config[$nom . '_table_nocreation'] ?? '') === 'on') ? 'on' : '',
					'obligatoire' => array_key_exists($nom . '_obligatoire_nocreation', $config)
						? (($config[$nom . '_obligatoire_nocreation'] === 'on') ? 'on' : '')
						: (inscription4_cextras_est_obligatoire($saisie['options']['obligatoire'] ?? false) ? 'on' : ''),
				);
			}
		}
	}

	return inscription4_cextras_enregistrer_options_natives($nouvelle);
}

/**
 * Migre la surcouche Formulaire/Obligatoire vers les options CExtras natives.
 */
function inscription4_migrer_cextras_options_natives() {
	return inscription4_migrer_configuration_cextras();
}


/**
 * Fonction de suppession du plugin
 *
 * Supprime la méta de configuration d'inscription3
 * @param string $nom_meta_base_version Le nom de la méta d'installation
 */
function inscription3_vider_tables($nom_meta_base_version) {
	effacer_meta('inscription3');
	effacer_meta($nom_meta_base_version);
}


/**
 * Migre les sites utilisant encore spip_geo_pays vers le plugin Pays.
 *
 * Les correspondances sont établies avec le code ISO afin de ne pas dépendre
 * des identifiants historiques. Les éventuels pays personnalisés sont copiés
 * dans spip_pays avant de remapper les auteurs.
 */
function i3_migrer_pays() {
	$ancienne_table = sql_showtable('spip_geo_pays', '', false);
	if (empty($ancienne_table['field'])) {
		return true;
	}

	$table_auteurs = sql_showtable('spip_auteurs', '', false);
	if (empty($table_auteurs['field']['pays'])) {
		spip_log(
			'Migration vers le plugin Pays ignorée : la colonne historique spip_auteurs.pays est absente.',
			'inscription3.' . _LOG_INFO
		);
		return true;
	}

	$anciens_pays = sql_allfetsel('id_pays, code_iso, nom', 'spip_geo_pays');
	$pays_officiels = sql_allfetsel('id_pays, code', 'spip_pays');
	$ids_par_code = array_column($pays_officiels, 'id_pays', 'code');
	$correspondances = array();
	$erreurs = array();

	foreach ($anciens_pays as $ancien_pays) {
		$ancien_id = intval($ancien_pays['id_pays']);
		$code = strtoupper(trim($ancien_pays['code_iso']));
		if (!$code) {
			$erreurs[] = $ancien_id;
			continue;
		}

		if (!isset($ids_par_code[$code])) {
			$nouvel_id = sql_insertq(
				'spip_pays',
				array(
					'code' => $code,
					'nom' => $ancien_pays['nom'],
				)
			);
			if (!$nouvel_id) {
				$erreurs[] = $ancien_id;
				continue;
			}
			$ids_par_code[$code] = $nouvel_id;
		}

		$correspondances[$ancien_id] = intval($ids_par_code[$code]);
	}

	if ($erreurs) {
		spip_log(
			'Migration vers le plugin Pays interrompue : pays sans code ISO exploitable : '
				. implode(', ', array_unique($erreurs)),
			'inscription3.' . _LOG_ERREUR
		);
		return false;
	}

	$ids_utilises = array_map(
		'intval',
		array_column(
			sql_allfetsel(
				'DISTINCT pays AS id_pays',
				'spip_auteurs',
				'pays IS NOT NULL AND pays != 0'
			),
			'id_pays'
		)
	);
	$ids_sans_correspondance = array_diff($ids_utilises, array_keys($correspondances));
	if ($ids_sans_correspondance) {
		spip_log(
			'Migration vers le plugin Pays interrompue : identifiants hérités introuvables : '
				. implode(', ', $ids_sans_correspondance),
			'inscription3.' . _LOG_ERREUR
		);
		return false;
	}

	foreach ($correspondances as $ancien_id => $nouvel_id) {
		if ($ancien_id !== $nouvel_id) {
			sql_updateq(
				'spip_auteurs',
				array('pays' => $nouvel_id),
				'pays = ' . $ancien_id
			);
		}
	}

	sql_drop_table('spip_geo_pays');
	return true;
}

/**
 * Transfère les données de la tables auteurs_elargis vers spip_auteurs
 * @return unknown_type
 */
function inscription3_transfert_infos_auteurs() {
	include_spip('inc/config');
	$config = lire_config('inscription3', array());
	$exceptions_des_champs_auteurs_elargis = pipeline('i3_exceptions_des_champs_auteurs_elargis', array());

	/**
	 * On récupère un array $champs des champs qui doivent être dans la table
	 */
	$champs = array();
	$champs[] = 'id_auteur';
	if (is_array($config)) {
		foreach ($config as $clef => $val) {
			$cle = preg_replace('/_(obligatoire|fiche|table).*/', '', $clef);
			if (!in_array($cle, $champs)
				and !in_array($cle, $exceptions_des_champs_auteurs_elargis)
				and !preg_match(',(categories|zone|newsletter).*$,', $cle)
				and ($val == 'on')
			) {
				$champs[] = $cle;
			}
		}
	}

	$desc_auteurs_elargis = sql_showtable('spip_auteurs_elargis', '', false);
	if (isset($desc_auteurs_elargis['field'])) {
		$champs = array_intersect(array_keys($desc_auteurs_elargis['field']), $champs);
		$auteurs = sql_select($champs, 'spip_auteurs_elargis');
		while ($auteur = sql_fetch($auteurs)) {
			$id_auteur = $auteur['id_auteur'];
			unset($auteur['id_auteur']);
			sql_updateq('spip_auteurs', $auteur, "id_auteur=$id_auteur");
		}
	}
	return;
}
