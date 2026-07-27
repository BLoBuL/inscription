<?php
/**
 * Plugin Inscription3 pour SPIP
 * © cmtmt, BoOz, kent1
 * Licence GPL v3
 *
 * Notifications au changement de statut d'un auteur
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Notifier lors du changement de statut d'un auteur
 *
 * Basée sur :
 * https://code.spip.net/@notifications_instituerarticle_dist
 *
 * @param string $quoi
 * @param int $id_auteur
 * @param array $options
 */
function notifications_instituerauteur($quoi, $id_auteur, $options) {

	// ne devrait jamais se produire
    if (isset($options['statut']) ){
        $statut_nouveau = $options['statut'];
    } elseif (isset($options['statut_nouveau'])) {
        $statut_nouveau = $options['statut_nouveau'];
    } else {
        $statut_nouveau = false;
    }

	include_spip('inc/texte');
	include_spip('inc/config');

    $modele = '';

    if (isset($options['statut_ancien']) && $statut_nouveau == $options['statut_ancien']) {
        return;
    }

	/**
	 * Si l'ancien statut est 8aconfirmer
	 * - on notifie la validation s'il n'est pas mis à la poubelle
	 * - on notifie l'invalidation s'il est mis à la poubelle
	 *
	 * S'il est validé, on lui transmet un lien sécurisé SPIP.
	 */
	if (($options['statut_ancien'] ?? '') == '8aconfirmer' && $statut_nouveau != '8aconfirmer') {

		if ($statut_nouveau == '5poubelle') {
			$modele = 'notifications/auteur_invalide';
			$modele_admin = 'notifications/auteur_invalide_admin';

		} else {
			$modele = 'notifications/auteur_inscription_valider';
			$modele_admin = 'notifications/auteur_valide_admin';
		}
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
		include_spip('action/inscrire_auteur');
		$jeton = auteur_lire_jeton((int) $id_auteur, true);
		$url_reset = generer_url_public('spip_pass', 'p=' . $jeton, true);
		$texte = recuperer_fond($modele, array('id_auteur' => $id_auteur, 'url_reset' => $url_reset));
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
