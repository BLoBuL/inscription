<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Retourne les Champs Extras auteurs en excluant ceux déclarés par Inscription.
 *
 * La structure des fieldsets est conservée pour le rendu du formulaire.
 */
function inscription4_cextras_saisies_disponibles() {
	include_spip('cextras_pipelines');
	include_spip('base/inscription3');
	$saisies = champs_extras_objet('spip_auteurs');
	$champs_inscription = inscription3_declarer_champs_extras(array());
	$champs_exclus = array_keys($champs_inscription['spip_auteurs'] ?? array());
	return inscription4_cextras_filtrer_saisies($saisies, null, $champs_exclus);
}

/**
 * Filtre récursivement les saisies et supprime les conteneurs vides.
 */
function inscription4_cextras_filtrer_saisies($saisies, $noms = null, $exclus = array()) {
	$resultat = array();
	foreach ((array) $saisies as $cle => $saisie) {
		if (!is_array($saisie)) {
			continue;
		}
		$enfants = inscription4_cextras_filtrer_saisies($saisie['saisies'] ?? array(), $noms, $exclus);
		$nom = $saisie['options']['nom'] ?? '';
		$est_conteneur = !empty($saisie['saisies']) || in_array($saisie['saisie'] ?? '', array('fieldset', 'explication'));
		$est_inscription = ($saisie['source'] ?? '') === 'inscription3' || in_array($nom, $exclus, true);
		$est_selectionnee = $noms === null || in_array($nom, $noms, true);

		if ($est_conteneur) {
			if ($enfants) {
				$saisie['saisies'] = $enfants;
				$resultat[$cle] = $saisie;
			}
		} elseif (!$est_inscription && $nom && !empty($saisie['options']['sql']) && $est_selectionnee) {
			$resultat[$cle] = $saisie;
		}
	}
	return $resultat;
}

/**
 * Liste plate des Champs Extras configurables.
 */
function inscription4_cextras_liste_configurable() {
	include_spip('inc/cextras');
	return champs_extras_saisies_lister_avec_sql(inscription4_cextras_saisies_disponibles());
}

/**
 * Normalise les différentes écritures booléennes utilisées par Saisies.
 */
function inscription4_cextras_est_obligatoire($valeur) {
	return in_array($valeur, array(true, 1, '1', 'on', 'oui', 'true'), true);
}

/**
 * Lit la nouvelle configuration et reprend les anciennes clés au besoin.
 */
function inscription4_cextras_configuration() {
	include_spip('inc/config');
	$config = lire_config('inscription3/cextras_inscription', array());
	$config_globale = lire_config('inscription3', array());

	foreach (inscription4_cextras_liste_configurable() as $nom => $saisie) {
		$nom = $saisie['options']['nom'] ?? $nom;
		if (!array_key_exists($nom, $config)) {
			$config[$nom] = array(
				'afficher' => (($config_globale[$nom . '_nocreation'] ?? '') === 'on') ? 'on' : '',
				'fiche' => (($config_globale[$nom . '_fiche_nocreation'] ?? '') === 'on') ? 'on' : '',
				'table' => (($config_globale[$nom . '_table_nocreation'] ?? '') === 'on') ? 'on' : '',
				'obligatoire' => array_key_exists($nom . '_obligatoire_nocreation', $config_globale)
					? (($config_globale[$nom . '_obligatoire_nocreation'] === 'on') ? 'on' : '')
					: (inscription4_cextras_est_obligatoire($saisie['options']['obligatoire'] ?? false) ? 'on' : ''),
			);
		}
	}
	return $config;
}

/**
 * Saisies CExtras activées dans un contexte d'affichage.
 */
function inscription4_cextras_saisies_contexte($contexte) {
	if (!in_array($contexte, array('fiche', 'table'), true)) {
		return array();
	}
	$config = inscription4_cextras_configuration();
	$noms = array();
	foreach ($config as $nom => $reglage) {
		if (($reglage[$contexte] ?? '') === 'on') {
			$noms[] = $nom;
		}
	}
	return inscription4_cextras_filtrer_saisies(inscription4_cextras_saisies_disponibles(), $noms);
}

/**
 * Prépare les saisies CExtras réellement rendues par le formulaire.
 */
function inscription4_cextras_saisies_inscription() {
	$config = inscription4_cextras_configuration();
	$noms = array();
	foreach ($config as $nom => $reglage) {
		if (($reglage['afficher'] ?? '') === 'on') {
			$noms[] = $nom;
		}
	}

	$saisies = inscription4_cextras_filtrer_saisies(inscription4_cextras_saisies_disponibles(), $noms);
	return inscription4_cextras_appliquer_obligation($saisies, $config);
}

/**
 * Applique l'obligation propre au formulaire sans modifier CExtras en base.
 */
function inscription4_cextras_appliquer_obligation($saisies, $config) {
	foreach ($saisies as &$saisie) {
		if (!empty($saisie['saisies'])) {
			$saisie['saisies'] = inscription4_cextras_appliquer_obligation($saisie['saisies'], $config);
			continue;
		}
		$nom = $saisie['options']['nom'] ?? '';
		$saisie['options']['obligatoire'] = (($config[$nom]['obligatoire'] ?? '') === 'on') ? 'oui' : '';
	}
	unset($saisie);
	return $saisies;
}

/**
 * Noms des Champs Extras effectivement présents dans l'inscription.
 */
function inscription4_cextras_champs_inscription() {
	include_spip('inc/cextras');
	return array_keys(champs_extras_saisies_lister_avec_sql(inscription4_cextras_saisies_inscription()));
}

/**
 * Alias historique utilisé par les plugins Blobul dépendants.
 */
function champs_extras_objet_aplati($objet) {
	include_spip('cextras_pipelines');
	include_spip('inc/cextras');
	return array_values(champs_extras_saisies_lister_avec_sql(champs_extras_objet($objet)));
}
