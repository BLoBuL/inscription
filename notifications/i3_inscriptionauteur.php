<?php
/**
 * Plugin Inscription3 pour SPIP
 * © cmtmt, BoOz, kent1
 * Licence GPL v3
 *
 * Notifications d'inscription d'un auteur
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Notifier lors de l'inscription d'un auteur
 *
 * @param string $quoi
 * @param int $id_auteur
 * @param array $options
 */
function notifications_i3_inscriptionauteur($quoi, $id_auteur, $options) {
	include_spip('inc/texte');

	$modele = false;

	/**
	 * Si l'ancien statut est 8aconfirmer
	 * - on notifie la validation s'il n'est pas mis à la poubelle
	 * - on notifie l'invalidation s'il est mis à la poubelle
	 *
	 * S'il est validé, on lui transmet un lien sécurisé SPIP.
	 */
    if (isset($options['statut']) ){
        $statut_nouveau = $options['statut'];
    } elseif (isset($options['statut_nouveau'])) {
        $statut_nouveau = $options['statut_nouveau'];
    } else {
        $statut_nouveau = false;
    }
    if (isset($options['statut_ancien']) && $statut_nouveau == $options['statut_ancien']) {
        // statut auteur inchange on ne notifie pas
        return;
    }

    if (isset($statut_nouveau) && $statut_nouveau == '8aconfirmer') {
		$modele = 'notifications/auteur_inscription_confirmer';
		$modele_admin = 'notifications/auteur_inscription_confirmer_admin';
	}

	if (isset($options['statut_ancien']) && $options['statut_ancien'] == '8aconfirmer' && $statut_nouveau != '8aconfirmer' && $statut_nouveau != '5poubelle') {
		$modele = 'notifications/auteur_inscription_valider';
		$modele_admin = 'notifications/auteur_valide_admin';
	}

	/**
	 * Vérification régulière (via Cron) des comptes à valider ou invalider
	 */
	if (isset($options['verifier_confirmer']) && $options['verifier_confirmer'] == 'oui') {
		$modele_admin = 'notifications/auteur_inscription_verifier_admin';
	}
	if ($modele) {
		$options['type'] = 'user';
		$destinataires = array();
		$destinataires = pipeline(
			'notifications_destinataires',
			array(
				'args'=>array('quoi'=>$quoi,'id'=>$id_auteur,'options'=>$options),
				'data'=>$destinataires
			)
		);
		$contexte = array('id_auteur' => $id_auteur);
		if (($options['statut_ancien'] ?? '') == '8aconfirmer' && $statut_nouveau != '5poubelle') {
			include_spip('action/inscrire_auteur');
			$jeton = auteur_lire_jeton((int) $id_auteur, true);
			$contexte['url_reset'] = generer_url_public('spip_pass', 'p=' . $jeton, true);
		}
		$texte = recuperer_fond($modele, $contexte);
		notifications_envoyer_mails($destinataires, $texte);
	}

	if (isset($modele_admin)) {
		$options['type'] = 'admin';
		$destinataires = array();

		$destinataires = pipeline(
			'notifications_destinataires',
			array(
				'args'=>array('quoi'=>$quoi,'id'=>$id_auteur,'options'=>$options),
				'data'=>$destinataires
			)
		);
		$texte = email_notification_objet($id_auteur, 'auteur', $modele_admin);
		notifications_envoyer_mails($destinataires, $texte);
	}
}
