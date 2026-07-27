<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Vérifie une syntaxe téléphonique internationale compatible E.164.
 *
 * Les séparateurs de présentation courants sont acceptés, mais la valeur doit
 * commencer par "+" et contenir ensuite de 7 à 15 chiffres, avec un indicatif
 * international qui ne commence pas par zéro.
 */
function inscription4_telephone_international_valide($valeur) {
	if (!is_scalar($valeur)) {
		return false;
	}
	$numero = preg_replace('/[\s.\-()]/u', '', trim((string) $valeur));
	return (bool) preg_match('/^\+[1-9][0-9]{6,14}$/D', $numero);
}

/**
 * Fonction de validation d'un numéro de téléphone
 *
 * @return false|string retourne false si pas de valeurs ou si la valeur est correcte,
 * un message d'erreur dans le cas contraire
 * @param string $valeur Numéro à tester
 * @param array $options [optional]
 */
function verifier_telephone_dist($valeur, $options = array()) {
	if (!$valeur) {
		return false;
	}

	$validation_internationale = lire_config('inscription3/validation_numero_international') === 'on';
	if ($validation_internationale) {
		return inscription4_telephone_international_valide($valeur)
			? false
			: _T('inscription3:erreur_numero_valide_international');
	}

	if (
		preg_match('/^[0-9\+\. \-]+$/', $valeur)
		&& strlen(str_replace(array(' ', '.', '+'), '', $valeur)) > 6
	) {
		return false;
	}

	return _T('inscription3:erreur_numero_valide');
}
