<?php
/**
 * Plugin Inscription3 pour SPIP
 * © cmtmt, BoOz, kent1
 * Licence GPL v3
 *
 * Fichier des options spécifiques du plugin
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Ajout du statut 8aconfirmer dans la liste des statuts possibles de SPIP
 * Des exemples dans i3_validation_methods.js.html
 * cf : crayons_validation.js.html
 */
$GLOBALS['liste_des_statuts']['inscription3:info_aconfirmer'] = '8aconfirmer';

/**
 * Adapter le courriel natif d'inscription au workflow avec validation
 * administrative.
 *
 * SPIP appelle cette fonction surchargeable avant d'envoyer son courriel.
 * Lorsque la validation est requise, aucun lien de confirmation ou de choix
 * du mot de passe ne doit être transmis avant la décision de l'administrateur.
 *
 * @param array $desc
 * @param string $nom
 * @param string $mode
 * @param array $options
 * @return array
 */
function envoyer_inscription($desc, $nom, $mode, $options = array()) {
	include_spip('inc/config');

	if (lire_config('inscription3/valider_comptes') === 'on') {
		$options['modele_mail'] = 'notifications/inscription4_auteur_attente';
		set_request('_inscription4_mail_attente_natif', 1);
	}

	return envoyer_inscription_dist($desc, $nom, $mode, $options);
}
