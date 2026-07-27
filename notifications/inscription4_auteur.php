<?php

/**
 * Notifications sécurisées propres à Inscription 4.
 *
 * Ce nom volontairement spécifique évite les surcharges historiques des
 * plugins qui personnalisaient `i3_inscriptionauteur`.
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function notifications_inscription4_auteur($quoi, $id_auteur, $options) {
	include_spip('inc/texte');

	$statut_nouveau = $options['statut'] ?? ($options['statut_nouveau'] ?? false);
	if (($options['statut_ancien'] ?? null) === $statut_nouveau) {
		return;
	}

	$modele = false;
	$modele_admin = false;

	if ($statut_nouveau === '8aconfirmer') {
		$modele = 'notifications/auteur_inscription_confirmer';
		$modele_admin = 'notifications/auteur_inscription_confirmer_admin';
	}

	if (
		($options['statut_ancien'] ?? '') === '8aconfirmer'
		&& $statut_nouveau !== '8aconfirmer'
		&& $statut_nouveau !== '5poubelle'
	) {
		$modele = 'notifications/inscription4_auteur_inscription_valider';
		$modele_admin = 'notifications/auteur_valide_admin';
	}

	if (($options['verifier_confirmer'] ?? '') === 'oui') {
		$modele_admin = 'notifications/auteur_inscription_verifier_admin';
	}

	if ($modele) {
		$options['type'] = 'user';
		$destinataires = pipeline(
			'notifications_destinataires',
			array(
				'args' => array('quoi' => $quoi, 'id' => $id_auteur, 'options' => $options),
				'data' => array(),
			)
		);
		$contexte = array('id_auteur' => $id_auteur);
		if (
			($options['statut_ancien'] ?? '') === '8aconfirmer'
			&& $statut_nouveau !== '5poubelle'
		) {
			include_spip('action/inscrire_auteur');
			$jeton = auteur_lire_jeton((int) $id_auteur, true);
			$contexte['url_reset'] = generer_url_public('spip_pass', 'p=' . $jeton, true);
		}
		$texte = recuperer_fond($modele, $contexte);
		notifications_envoyer_mails($destinataires, $texte);
	}

	if ($modele_admin) {
		$options['type'] = 'admin';
		$destinataires = pipeline(
			'notifications_destinataires',
			array(
				'args' => array('quoi' => $quoi, 'id' => $id_auteur, 'options' => $options),
				'data' => array(),
			)
		);
		$texte = email_notification_objet($id_auteur, 'auteur', $modele_admin);
		notifications_envoyer_mails($destinataires, $texte);
	}
}
