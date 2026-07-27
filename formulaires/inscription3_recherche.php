<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Chargement des valeurs par defaut des champs du formulaire
 */
function formulaires_inscription3_recherche_charger_dist() {
	include_spip('inc/autoriser');
	if (!autoriser('voir', 'inscription3adherents')) {
		return array('editable' => false, 'message_erreur' => _T('info_acces_interdit'));
	}
	$data = array('editable' => true);
	$data['ordre'] = _request('ordre');
	$data['desc'] = _request('desc');
	$data['case'] = _request('case');
	$data['valeur'] = _request('valeur');

	$data['exceptions'] = pipeline('i3_exceptions_des_champs_auteurs_elargis', array());

	if (_request('afficher_tous')) {
		set_request('valeur', '');
		set_request('case', '');
	}
	return $data;
}

/**
 * Vérification du formulaire
 * @return
 */
function formulaires_inscription3_recherche_verifier_dist() {
	$erreurs = array();
	include_spip('inc/autoriser');
	if (!autoriser('voir', 'inscription3adherents')) {
		return array('message_erreur' => _T('info_acces_interdit'));
	}
	if (_request('supprimer_auteurs')) {
		$auteurs_checked = _request('check_aut');
		if (is_array($auteurs_checked)) {
			include_spip('inc/autoriser');
			foreach ($auteurs_checked as $val) {
				$id_auteur = (int) $val;
				$statut = sql_fetsel('nom,statut', 'spip_auteurs', 'id_auteur='.$id_auteur);
				if (
					!$statut
					|| !autoriser('modifier', 'auteur', $id_auteur)
					|| $statut['statut'] === '0minirezo'
				) {
					$erreurs['check_aut'.$val] = array(
						'nom' => $statut['nom'] ?? ('#'.$id_auteur),
						'statut' => $statut['statut'] ?? '',
					);
				}
			}
			if (count($erreurs)>0) {
				$infos_erreurs = '';
				foreach ($erreurs as $infos) {
					$infos_erreurs .= '<p>'._T('inscription3:erreur_info_statut', $infos).'</p>';
				}
				$erreurs['message_erreur'] = '<p>'._T('inscription3:erreur_suppression_comptes_impossible').'</p>';
				$erreurs['message_erreur'] .= $infos_erreurs;
			}
		} else {
			$erreurs['message_erreur'] = _T('inscription3:no_user_selected');
		}
	}

	return $erreurs;
}

/**
 * Traitement du formulaire
 * @return
 */
function formulaires_inscription3_recherche_traiter_dist() {

	$retour = array();
	include_spip('inc/autoriser');
	if (!autoriser('voir', 'inscription3adherents')) {
		return array('message_erreur' => _T('info_acces_interdit'));
	}
	if (_request('supprimer_auteurs')) {
		$auteurs_checked = _request('check_aut');
		$nb_auteurs = 0;
		if (is_array($auteurs_checked)) {
			foreach ($auteurs_checked as $val) {
				$id_auteur = (int) $val;
				$statut = sql_getfetsel('statut', 'spip_auteurs', 'id_auteur='.$id_auteur);
				if (
					$statut
					&& $statut !== '0minirezo'
					&& autoriser('modifier', 'auteur', $id_auteur)
				) {
					include_spip('inscription3_pipelines');
					$erreur = inscription4_auteur_modifier_interne($id_auteur, array('statut' => '5poubelle'));
					if ($erreur) {
						$retour['message_erreur'] = $erreur;
						continue;
					}
					$nb_auteurs++;
				}
			}
		} else {
			// Rien à faire
		}
		if (!empty($retour['message_erreur'])) {
			return $retour;
		} elseif ($nb_auteurs > 1) {
			$retour['message_ok'] = _T('inscription3:message_users_supprimes_nb', array('nb' => $nb_auteurs));
		} else {
			$retour['message_ok'] = _T('inscription3:message_users_supprimes_un');
		}
	}
	return $retour;
}
