<?php
/**
 * Plugin Inscription3 pour SPIP
 * © cmtmt, BoOz, kent1
 * Licence GPL v3
 *
 * Formulaire de demande d'effacement de compte
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Retrouve un auteur avec l'API de jetons chiffrés de SPIP 4.
 */
function formulaires_supprimer_visiteur_auteur_par_jeton($jeton) {
	if (!is_string($jeton) || !preg_match('/^[0-9a-f.]+$/i', $jeton)) {
		return false;
	}

	include_spip('action/inscrire_auteur');
	$auteur = auteur_verifier_jeton($jeton);
	if (!$auteur || ($auteur['statut'] ?? '') === '5poubelle') {
		return false;
	}

	return $auteur;
}

/**
 * Chargement des valeurs par défaut du formulaire
 */
function formulaires_supprimer_visiteur_charger_dist() {
	$valeurs = array();

	/**
	 * On trouve la correspondance entre le code envoyé dans l'url et un compte utilisateur
	 * éventuellement à supprimer
	 */
	if ($p=_request('s')) {
		$p = preg_replace(',[^0-9a-f.],i', '', $p);
		if ($p && $row = formulaires_supprimer_visiteur_auteur_par_jeton($p)) {
			$valeurs['_hidden'] = '<input type="hidden" name="s" value="'.$p.'" />';
		}
	}

	/**
	 * Si on a un compte valide pour le code fournit en url :
	 * - On doit être connecté pour supprimer le compte;
	 * - On doit être au minimum administrateur si la session actuelle n'est pas la même que le compte à supprimer;
	 * - On ne peut pas supprimer un compte webmestre;
	 * - Si ces conditions sont remplies, on ajoute dans l'environnement les informations nécessaires;
	 */
	if (isset($row) && !empty($row['id_auteur'])) {
		if (!intval($GLOBALS['visiteur_session']['id_auteur'])) {
			$valeurs['message_erreur'] = _T('inscription3:erreur_suppression_compte_connecte');
		} elseif (($row['id_auteur'] != $GLOBALS['visiteur_session']['id_auteur'])
			and $GLOBALS['visiteur_session']['statut'] != '0minirezo') {
			$valeurs['message_erreur'] = _T('inscription3:erreur_suppression_compte_non_auteur');
		} elseif ($row['webmestre'] == 'oui' and $row['statut'] == '0minirezo') {
			$valeurs['message_erreur'] = _T('inscription3:erreur_suppression_compte_webmestre');
		} else {
			$valeurs['id_auteur'] = $row['id_auteur']; // a toutes fins utiles pour le formulaire
			$valeurs['nom'] = $row['nom'];
			$valeurs['email'] = $row['email'];
		}
	} else {
		/**
		 * Si pas d'auteur trouvé, on affiche un message comme quoi le code n'est pas valide
		 */
		$valeurs['message_erreur'] = _T('pass_erreur_code_inconnu');
	}

	/**
	 * Si un message d'erreur envoyé, on n'affiche pas le formulaire, mais juste le message
	 */
	if (isset($valeurs['message_erreur'])) {
		$valeurs['editable'] =  false;
	}

	return $valeurs;
}

/**
 * Vérification du formulaire
 */
function formulaires_supprimer_visiteur_verifier_dist() {
	$erreurs = array();

	if ($p=_request('s')) {
		$auteur = formulaires_supprimer_visiteur_auteur_par_jeton($p);
		if (!$auteur || in_array($auteur['statut'], array('0minirezo', '1comite'))) {
			$erreurs['oubli'] =  _T('inscription3:erreur_effacement_auto_impossible');
		}
	} else {
		$erreurs['inconnu'] = _T('inscription3:erreur_effacement_auto_impossible');
	}

	return $erreurs;
}

/***
 * Traitement du formulaire
 */
function formulaires_supprimer_visiteur_traiter_dist() {
	$auteur = formulaires_supprimer_visiteur_auteur_par_jeton(_request('s'));
	$id_session = (int) ($GLOBALS['visiteur_session']['id_auteur'] ?? 0);
	if (
		!$auteur
		|| !$id_session
		|| in_array($auteur['statut'], array('0minirezo', '1comite'))
		|| (
			(int) $auteur['id_auteur'] !== $id_session
			&& ($GLOBALS['visiteur_session']['statut'] ?? '') !== '0minirezo'
		)
	) {
		return array('message_erreur' => _T('inscription3:erreur_effacement_auto_impossible'));
	}

	include_spip('inscription3_pipelines');
	$erreur = inscription4_auteur_modifier_interne(
		(int) $auteur['id_auteur'],
		array('statut' => '5poubelle')
	);
	if ($erreur) {
		return array('message_erreur' => $erreur);
	}

	include_spip('inc/session');
	supprimer_sessions((int) $auteur['id_auteur']);

	$message = _T('inscription3:message_compte_efface');
	return array('message_ok' => $message);
}
