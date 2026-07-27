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
	include_spip('inc/iextras');
	return iextras_champs_extras_definis('spip_auteurs');
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
		$reglage = $config[$nom] ?? array();
		$config[$nom] = array(
			'afficher' => inscription4_cextras_est_obligatoire($saisie['options']['inscription4_formulaire'] ?? false) ? 'on' : '',
			'fiche' => array_key_exists('fiche', $reglage)
				? (($reglage['fiche'] === 'on') ? 'on' : '')
				: ((($config_globale[$nom . '_fiche_nocreation'] ?? '') === 'on') ? 'on' : ''),
			'table' => array_key_exists('table', $reglage)
				? (($reglage['table'] === 'on') ? 'on' : '')
				: ((($config_globale[$nom . '_table_nocreation'] ?? '') === 'on') ? 'on' : ''),
			'obligatoire' => inscription4_cextras_est_obligatoire($saisie['options']['obligatoire'] ?? false) ? 'on' : '',
		);
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
	$noms = array();
	foreach (inscription4_cextras_liste_configurable() as $nom => $saisie) {
		$nom = $saisie['options']['nom'] ?? $nom;
		if (inscription4_cextras_est_obligatoire($saisie['options']['inscription4_formulaire'] ?? false)) {
			$noms[] = $nom;
		}
	}

	return inscription4_cextras_filtrer_saisies(inscription4_cextras_saisies_disponibles(), $noms);
}

/**
 * Met à jour récursivement les options natives des définitions CExtras.
 */
function inscription4_cextras_mettre_a_jour_options($saisies, $reglages, &$modifie = false) {
	foreach ($saisies as &$saisie) {
		if (!empty($saisie['saisies'])) {
			$saisie['saisies'] = inscription4_cextras_mettre_a_jour_options(
				$saisie['saisies'],
				$reglages,
				$modifie
			);
			continue;
		}
		$nom = $saisie['options']['nom'] ?? '';
		if (!$nom || empty($saisie['options']['sql']) || !array_key_exists($nom, $reglages)) {
			continue;
		}

		$formulaire = (($reglages[$nom]['afficher'] ?? '') === 'on') ? 'on' : '';
		$obligatoire = (($reglages[$nom]['obligatoire'] ?? '') === 'on') ? 'oui' : '';
		if (($saisie['options']['inscription4_formulaire'] ?? '') !== $formulaire) {
			$saisie['options']['inscription4_formulaire'] = $formulaire;
			$modifie = true;
		}
		if (($saisie['options']['obligatoire'] ?? '') !== $obligatoire) {
			$saisie['options']['obligatoire'] = $obligatoire;
			$modifie = true;
		}
	}
	unset($saisie);
	return $saisies;
}

/**
 * Enregistre Formulaire et Obligatoire dans la définition CExtras elle-même.
 *
 * La configuration Inscription ne conserve que Fiche et Table, qui sont des
 * contextes propres au plugin et non des propriétés natives du champ.
 */
function inscription4_cextras_enregistrer_options_natives($reglages) {
	if (!is_array($reglages)) {
		return false;
	}

	$meta = $GLOBALS['meta']['champs_extras_spip_auteurs'] ?? '';
	$saisies = $meta ? @unserialize($meta) : array();
	if (!is_array($saisies)) {
		return false;
	}

	$disponibles = inscription4_cextras_liste_configurable();
	$reglages = array_intersect_key($reglages, $disponibles);
	$modifie = false;
	$saisies = inscription4_cextras_mettre_a_jour_options($saisies, $reglages, $modifie);

	if ($modifie) {
		ecrire_meta('champs_extras_spip_auteurs', serialize($saisies));
	}

	$config_contextuelle = array();
	foreach ($reglages as $nom => $reglage) {
		$config_contextuelle[$nom] = array(
			'fiche' => (($reglage['fiche'] ?? '') === 'on') ? 'on' : '',
			'table' => (($reglage['table'] ?? '') === 'on') ? 'on' : '',
		);
	}
	include_spip('inc/config');
	ecrire_config('inscription3/cextras_inscription', $config_contextuelle);

	if ($modifie) {
		include_spip('inc/invalideur');
		suivre_invalideur('1');
	}

	return true;
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
