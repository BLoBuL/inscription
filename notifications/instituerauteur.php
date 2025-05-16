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
    spip_log('statut auteur inchange', 'notifications'. _LOG_CRITIQUE);
	// ne devrait jamais se produire
    if (isset($options['statut']) ){
        $statut_nouveau = $options['statut'];
    } elseif (isset($options['statut_nouveau'])) {
        $statut_nouveau = $options['statut_nouveau'];
    } else {
        spip_log('statut auteur inchange', 'notifications');
        $statut_nouveau = false;
    }
    spip_log($options, 'notifications');
    spip_log('statut auteur : '.$statut_nouveau, 'notifications'. _LOG_CRITIQUE);
	include_spip('inc/texte');
	include_spip('inscription3_mes_fonctions');

	$modele = '';

	/**
	 * Si l'ancien statut est 8aconfirmer
	 * - on notifie la validation s'il n'est pas mis à la poubelle
	 * - on notifie l'invalidation s'il est mis à la poubelle
	 *
	 * S'il est validé, on lui recrée un pass que l'on met dans le mail avec son login
	 */
	if ($options['statut_ancien'] == '8aconfirmer' && $statut_nouveau != '8aconfirmer') {
		if ($statut_nouveau == '5poubelle') {
			$modele = 'notifications/auteur_invalide';
			$modele_admin = 'notifications/auteur_invalide_admin';
		} else {
			/**
			 * Dans le cas d'une validation, on envoit le pass
			 * On regénère le mot de passe également
			 */
			include_spip('inc/acces');
			$pass = creer_pass_aleatoire(8, $id_auteur);
			include_spip('action/editer_auteur');
			instituer_auteur($id_auteur, array('pass' => $pass));

			$modele = 'notifications/auteur_valide';
			$fonction_user = 'auteur_pass';
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
		if ($modele) {
            spip_log($modele, 'notifications'. _LOG_CRITIQUE);
			if ($fonction_user == 'auteur_pass') {
				$texte = email_notification_auteur_pass($id_auteur, $modele, $pass);
			} else {
				$texte = email_notification_objet($id_auteur, 'auteur', $modele);
			}
		}
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

function email_notification_auteur_pass($id_auteur, $modele, $pass) {
	charger_fonction('envoyer_mail', 'inc'); // pour nettoyer_titre_email
	return recuperer_fond($modele, array('id_auteur' => $id_auteur, 'pass' => $pass));
}
