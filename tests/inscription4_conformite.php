<?php

/**
 * Contrôles statiques du socle natif SPIP 4.
 *
 * Exécution : php tests/inscription4_conformite.php
 */

$racine = dirname(__DIR__);
$erreurs = array();

$paquet = simplexml_load_file($racine . '/paquet.xml');
if ((string) $paquet['prefix'] !== 'inscription4') {
	$erreurs[] = 'Le préfixe du paquet doit être inscription4.';
}
if ((string) $paquet['compatibilite'] !== '[4.1.0;4.*]') {
	$erreurs[] = 'La compatibilité doit être limitée à SPIP 4.';
}
if ((string) $paquet['schema'] !== '4.1.0') {
	$erreurs[] = 'Le schéma doit inclure la migration vers les options CExtras natives.';
}
$procure_inscription3 = false;
foreach ($paquet->procure as $procure) {
	if ((string) $procure['nom'] === 'inscription3') {
		$procure_inscription3 = true;
		break;
	}
}
if (!$procure_inscription3) {
	$erreurs[] = 'Inscription 4 doit procurer l’API inscription3 aux plugins dépendants.';
}
$necessite_iextras = false;
foreach ($paquet->necessite as $necessite) {
	if ((string) $necessite['nom'] === 'iextras') {
		$necessite_iextras = true;
		break;
	}
}
if (!$necessite_iextras) {
	$erreurs[] = 'L’écriture directe des définitions CExtras nécessite le plugin IExtras.';
}
if (
	!is_file($racine . '/lang/inscription4_fr.php')
	|| strpos(file_get_contents($racine . '/paquet.xml'), 'titre="inscription4:icone_configurer"') === false
) {
	$erreurs[] = 'L’identité visible du plugin doit utiliser le module de langue Inscription 4.';
}

$code_php = '';
$iterateur = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($racine, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterateur as $fichier) {
	if ($fichier->getExtension() === 'php') {
		$code_php .= file_get_contents($fichier->getPathname());
	}
}
foreach ($paquet->pipeline as $declaration) {
	$action = (string) $declaration['action'];
	if ($action && strpos($code_php, "function $action(") === false) {
		$erreurs[] = "Action de pipeline introuvable : $action.";
	}
}
foreach (array(
	'i3_exceptions_des_champs_auteurs_elargis',
	'i3_exceptions_chargement_champs_auteurs_elargis',
	'i3_verifications_specifiques',
	'i3_definition_champs',
	'declarer_champs_extras',
	'autoriser',
	'pre_insertion',
	'affiche_droite',
	'recuperer_fond',
	'formulaire_charger',
	'formulaire_verifier',
	'formulaire_traiter',
	'editer_contenu_objet',
	'notifications_destinataires',
	'taches_generales_cron',
	'openid_recuperer_identite',
	'openid_inscrire_redirect',
	'pre_edition',
	'post_edition',
	'saisies_construire_formulaire_config',
) as $pipeline_inscription4) {
	if (strpos($code_php, "function inscription4_$pipeline_inscription4(") === false) {
		$erreurs[] = "Point d’entrée Inscription 4 absent pour le pipeline $pipeline_inscription4.";
	}
}
foreach ($paquet->pipeline as $declaration) {
	if (strpos((string) $declaration['action'], 'inscription3_') === 0) {
		$erreurs[] = 'Un handler de pipeline Inscription 3 serait doublement préfixé par le paquet Inscription 4.';
		break;
	}
}

$pipeline = file_get_contents($racine . '/inscription3_pipelines.php');
foreach (array('_nano_sha256', 'generer_htpass') as $motif) {
	if (strpos($pipeline, $motif) !== false) {
		$erreurs[] = "Construction manuelle de mot de passe détectée : $motif.";
	}
}
if (preg_match('/\\$val\\[(?:[\'"])(?:pass|htpass|alea_actuel|alea_futur)(?:[\'"])\\]\\s*=/', $pipeline)) {
	$erreurs[] = 'Écriture directe d’un champ d’authentification détectée.';
}
if (strpos($pipeline, '$_GET') !== false) {
	$erreurs[] = 'Les paramètres GET ne doivent jamais être fusionnés aux options de vérification.';
}
if (
	strpos($pipeline, "\$flux['data']['id_auteur']") === false
	|| strpos($pipeline, '_inscription4_id_auteur_existant') === false
	|| strpos($pipeline, "sql_fetsel('*', 'spip_auteurs', 'email='") !== false
) {
	$erreurs[] = 'Le traitement doit cibler exclusivement l’auteur retourné par le formulaire natif.';
}
if (
	!preg_match(
		'/\\$flux\\[\'creation\'\\]\\s*=\\s*array\\((.*?)\\n\\t\\);\\n\\t\\$flux\\[\'naissance\'\\]/s',
		$pipeline,
		$definition_creation
	)
	|| strpos($definition_creation[1] ?? '', "'saisie' => 'date'") === false
	|| strpos($definition_creation[1] ?? '', "'horaire' => 'oui'") === false
	|| strpos($definition_creation[1] ?? '', "'normaliser' => 'date_ou_datetime'") === false
	|| strpos($definition_creation[1] ?? '', "'saisie' => 'date_jour_mois_annee'") !== false
) {
	$erreurs[] = 'Le champ creation doit utiliser la saisie date native avec son horaire.';
}

$options = file_get_contents($racine . '/inscription3_options.php');
if (
	!preg_match('/function\s+envoyer_inscription\s*\(/', $options)
	|| strpos($options, 'return envoyer_inscription_dist($desc, $nom, $mode, $options);') === false
) {
	$erreurs[] = 'L’adaptation du courriel natif doit toujours déléguer son rendu final à SPIP.';
}
$fonctions = file_get_contents($racine . '/inscription3_fonctions.php');
if (
	!preg_match('/function\\s+envoyer_inscription3\\([^)]*\\$options\\s*=/', $fonctions)
	|| strpos($fonctions, 'envoyer_inscription_dist($desc, $nom, $mode, $options)') === false
) {
	$erreurs[] = 'Le pont historique envoyer_inscription3 doit conserver les options de SPIP 4.';
}

$suppression = file_get_contents($racine . '/formulaires/supprimer_visiteur.php');
if (strpos($suppression, "cookie_oubli=") !== false) {
	$erreurs[] = 'La suppression de compte doit vérifier le jeton avec l’API SPIP 4.';
}
if (strpos($suppression, "sql_delete('spip_auteurs'") !== false) {
	$erreurs[] = 'La suppression de compte ne doit pas supprimer directement une ligne auteur.';
}
$recherche_privee = file_get_contents($racine . '/formulaires/inscription3_recherche.php');
if (
	strpos($recherche_privee, "autoriser('voir', 'inscription3adherents')") === false
	|| strpos($recherche_privee, "sql_updateq('spip_auteurs'") !== false
) {
	$erreurs[] = 'Le tableau adhérents doit contrôler son autorisation et utiliser l’API auteurs.';
}
$contenu_adherents = file_get_contents($racine . '/prive/squelettes/contenu/inscription3_adherents.html');
if (strpos($contenu_adherents, '#AUTORISER{voir,inscription3adherents}') === false) {
	$erreurs[] = 'La page privée des adhérents doit être protégée dans son squelette.';
}

foreach (array(
	'inscription3_taches_generales_cron',
	'inscription3_ajouter_menus',
	'inscription3_formulaire_charger',
) as $api) {
	if (strpos($pipeline, "function $api(") === false) {
		$erreurs[] = "API publique historique absente : $api.";
	}
}

$configuration_cextras = file_get_contents($racine . '/fonds/inscription3_cextras.html');
if (
	strpos($configuration_cextras, 'cextras_inscription[') === false
	|| strpos($configuration_cextras, 'name="cextras_inscription[]"') === false
	|| strpos($configuration_cextras, '[fiche]') === false
	|| strpos($configuration_cextras, '[table]') === false
	|| strpos($configuration_cextras, 'inscription4-conditionnel') === false
	|| strpos($configuration_cextras, '_nocreation"') !== false
) {
	$erreurs[] = 'Le tableau CExtras doit utiliser la configuration structurée Inscription 4.';
}
if (strpos($configuration_cextras, 'cextra_obligatoire_natif') !== false) {
	$erreurs[] = 'Le tableau ne doit pas répéter que l’obligation provient de la déclaration CExtras.';
}
$fonctions_cextras = file_get_contents($racine . '/formulaires/inscription3_cextras_fonctions.php');
if (strpos($fonctions_cextras, "\$saisies === null || \$saisies === ''") === false) {
	$erreurs[] = 'Le filtre SPIP doit pouvoir appeler la détection des conditions sans argument explicite.';
}
$configuration_contextuelle_directe = '';
if (preg_match(
	'/\\$config_contextuelle\\[\\$nom\\]\\s*=\\s*array\\((.*?)\\);/s',
	$fonctions_cextras,
	$match_configuration_contextuelle
)) {
	$configuration_contextuelle_directe = $match_configuration_contextuelle[1];
}
if (
	strpos($fonctions_cextras, "['options']['inscription4_formulaire']") === false
	|| strpos($fonctions_cextras, "['options']['obligatoire']") === false
	|| strpos($fonctions_cextras, "ecrire_meta('champs_extras_spip_auteurs'") === false
	|| !$configuration_contextuelle_directe
	|| strpos($configuration_contextuelle_directe, "'afficher'") !== false
	|| strpos($configuration_contextuelle_directe, "'obligatoire'") !== false
) {
	$erreurs[] = 'Formulaire et Obligatoire doivent être enregistrés dans la définition CExtras native.';
}
$configuration = file_get_contents($racine . '/formulaires/configurer_inscription3.html');
if (
	strpos($configuration, 'password_complexite') !== false
	|| strpos($configuration, 'password_reset') !== false
) {
	$erreurs[] = 'La configuration ne doit plus proposer de contrôles de mot de passe obsolètes.';
}
if (strpos($configuration, 'inscription4:explication_validation_numero_international') === false) {
	$erreurs[] = 'La configuration doit préciser que la validation téléphonique accepte tous les pays.';
}

$validation_js = file_get_contents($racine . '/formulaires/inscription3_validation.js.html');
$validation_crayons_js = file_get_contents($racine . '/i3_validation_methods.js.html');
foreach (array($validation_js, $validation_crayons_js) as $javascript_telephone) {
	if (
		strpos($javascript_telephone, '^\\+[1-9][0-9]{6,14}$') === false
		|| strpos($javascript_telephone, 'validation_numero_international') === false
		|| strpos($javascript_telephone, 'i3ValidationNumeroInternational') === false
	) {
		$erreurs[] = 'La validation JavaScript doit suivre la règle téléphonique internationale du serveur.';
		break;
	}
}

$pipeline_cextras = file_get_contents($racine . '/inscription3_pipelines.php');
if (substr_count($pipeline_cextras, 'saisies_verifier($saisies_cextras)') !== 1) {
	$erreurs[] = 'Les Champs Extras doivent être vérifiés une seule fois par l’API Saisies.';
}
if (substr_count($pipeline_cextras, "champs_extras_objet('spip_auteurs')") !== 1) {
	$erreurs[] = 'Le pipeline de vérification ne doit plus reparcourir tous les Champs Extras auteurs.';
}
if (
	strpos($pipeline_cextras, "config['password_reset']") !== false
	|| strpos($pipeline_cextras, "config['inscription3/password_complexite']") !== false
) {
	$erreurs[] = 'Inscription 4 ne doit pas masquer ou réécrire le parcours de mot de passe natif.';
}
if (
	strpos($pipeline_cextras, "unset(\$val['statut']);") === false
	|| strpos($pipeline_cextras, "\$flux['data']['prefs'] = \$flux['data']['statut'];") === false
	|| strpos($pipeline_cextras, "\$flux['data']['statut'] = 'nouveau';") === false
) {
	$erreurs[] = 'Le cycle de validation doit conserver le statut final dans prefs et repasser par nouveau.';
}
$notification_securisee = file_get_contents($racine . '/notifications/inscription4_auteur.php');
$modele_securise = file_get_contents($racine . '/notifications/inscription4_auteur_inscription_valider.html');
$options_inscription4 = file_get_contents($racine . '/inscription3_options.php');
$modele_attente = file_get_contents($racine . '/notifications/inscription4_auteur_attente.html');
$modele_attente_admin = file_get_contents($racine . '/notifications/inscription4_auteur_attente_admin.html');
if (
	strpos($pipeline_cextras, "\$notifications('inscription4_auteur'") === false
	|| strpos($notification_securisee, 'auteur_lire_jeton((int) $id_auteur, true)') === false
	|| strpos($notification_securisee, 'inscription4_auteur_inscription_valider') === false
	|| strpos($modele_securise, '#ENV{url_reset}') === false
	|| strpos($modele_securise, 'message_auteur_inscription4_valider_contenu_user') === false
	|| preg_match('/#ENV\\{(?:pass|password)\\}/i', $modele_securise)
) {
	$erreurs[] = 'La validation doit utiliser une notification Inscription 4 non surchargeable et un lien SPIP sécurisé.';
}
if (
	strpos($pipeline_cextras, "'notifier_utilisateur' => !_request('_inscription4_mail_attente_natif')") === false
	|| strpos($notification_securisee, "\$options['notifier_utilisateur'] ?? true") === false
	|| strpos($options_inscription4, 'function envoyer_inscription(') === false
	|| strpos($options_inscription4, "lire_config('inscription3/valider_comptes') === 'on'") === false
	|| strpos($options_inscription4, "'notifications/inscription4_auteur_attente'") === false
	|| strpos($options_inscription4, "set_request('_inscription4_mail_attente_natif', 1)") === false
) {
	$erreurs[] = 'Le mail d’attente natif doit empêcher le doublon utilisateur sans supprimer la notification administrateur.';
}
if (
	strpos($notification_securisee, 'notifications/inscription4_auteur_attente') === false
	|| strpos($notification_securisee, 'notifications/inscription4_auteur_attente_admin') === false
	|| preg_match('/#ENV\\{(?:pass|password|url_reset|url_confirm)\\}/i', $modele_attente)
	|| preg_match('/#ENV\\{(?:pass|password|url_reset|url_confirm)\\}/i', $modele_attente_admin)
) {
	$erreurs[] = 'Les messages d’attente doivent être propres à Inscription 4 et ne contenir aucun secret ni lien utilisable.';
}
if (
	strpos($notification_securisee, "statut_nouveau === '5poubelle'") === false
	|| strpos($notification_securisee, 'notifications/auteur_invalide') === false
	|| strpos($notification_securisee, 'notifications/auteur_invalide_admin') === false
) {
	$erreurs[] = 'Le refus administratif doit notifier explicitement l’utilisateur et les administrateurs.';
}
$cles_notifications = array(
	'message_auteur_inscription_attente_contenu_admin',
	'message_auteur_inscription_attente_contenu_user',
	'message_auteur_inscription_attente_titre_admin',
	'message_auteur_inscription_attente_titre_user',
	'message_auteur_inscription4_valider_contenu_user',
	'message_auteur_inscription4_valider_titre_user',
	'message_auteur_inscription4_validation_lien',
);
foreach (array('fr', 'en', 'es', 'de', 'nl', 'cs') as $langue_notification) {
	$contenu_langue = file_get_contents($racine . '/lang/inscription3_' . $langue_notification . '.php');
	foreach ($cles_notifications as $cle_notification) {
		if (strpos($contenu_langue, "'$cle_notification'") === false) {
			$erreurs[] = "La notification $cle_notification manque en langue $langue_notification.";
		}
	}
}
foreach (array(
	'message_auteur_invalide_contenu_admin',
	'message_auteur_invalide_contenu_user',
	'message_auteur_invalide_titre_admin',
	'message_auteur_invalide_titre_user',
) as $cle_refus_tcheque) {
	if (strpos(file_get_contents($racine . '/lang/inscription3_cs.php'), "'$cle_refus_tcheque'") === false) {
		$erreurs[] = "La notification de refus $cle_refus_tcheque manque en tchèque.";
	}
}
$administration = file_get_contents($racine . '/inscription3_administrations.php');
if (!preg_match("/\\\$maj\\['create'\\]\\[\\]\\s*=\\s*array\\('i3_migrer_pays'\\)/", $administration)) {
	$erreurs[] = 'Une première activation d’Inscription 4 doit migrer les pays historiques.';
}
if (
	strpos($administration, "sql_showtable('spip_auteurs'") === false
	|| strpos($administration, "['field']['pays']") === false
) {
	$erreurs[] = 'La migration Pays doit vérifier la présence de la colonne historique auteurs.pays.';
}
if (
	strpos($administration, 'inscription4_cextras_liste_configurable()') === false
	|| strpos($administration, 'array_intersect_key($nouvelle, $disponibles)') === false
	|| strpos($administration, "array_key_exists(\$nom . '_obligatoire_nocreation', \$config)") === false
	|| strpos($administration, "saisie['options']['obligatoire']") === false
) {
	$erreurs[] = 'La migration CExtras doit filtrer les champs et reprendre leur obligation native.';
}
if (
	strpos($administration, "maj['4.1.0']") === false
	|| strpos($administration, 'inscription4_migrer_cextras_options_natives') === false
) {
	$erreurs[] = 'La migration vers les options CExtras natives doit être versionnée.';
}
if (
	strpos(file_get_contents($racine . '/modeles/fiche_utilisateur.html'), '_fiche_nocreation') !== false
	|| strpos(file_get_contents($racine . '/prive/table_adherent_auteur.html'), '_table_nocreation') !== false
) {
	$erreurs[] = 'La fiche et le tableau auteurs doivent utiliser la configuration CExtras structurée.';
}
if (
	strpos(file_get_contents($racine . '/prive/table_adherent_auteur.html'), 'onclick=') !== false
	|| strpos(file_get_contents($racine . '/prive/table_adherent_auteur_recherche.html'), 'onclick=') !== false
) {
	$erreurs[] = 'Les liens de tri ne doivent pas contenir de JavaScript inline échappé par SPIP 4.';
}
if (
	strpos(file_get_contents($racine . '/prive/table_adherent_auteur.html'), '|=={aconfirmer}') !== false
	|| strpos(file_get_contents($racine . '/prive/table_adherent_auteur_recherche.html'), '|=={aconfirmer}') !== false
) {
	$erreurs[] = 'Le tableau doit reconnaître le statut réel 8aconfirmer.';
}

if (!defined('_ECRIRE_INC_VERSION')) {
	define('_ECRIRE_INC_VERSION', 1);
}
require_once $racine . '/inscription3_pipelines.php';
require_once $racine . '/formulaires/inscription3_cextras_fonctions.php';
require_once $racine . '/verifier/telephone.php';
$validation_native = inscription3_pre_edition(array(
	'args' => array(
		'action' => 'instituer',
		'table' => 'spip_auteurs',
		'statut_ancien' => '8aconfirmer',
	),
	'data' => array('statut' => '6forum'),
));
if (
	($validation_native['data']['statut'] ?? '') !== 'nouveau'
	|| ($validation_native['data']['prefs'] ?? '') !== '6forum'
) {
	$erreurs[] = 'La validation administrative doit produire nouveau avec prefs=6forum.';
}
$validation_native_redacteur = inscription3_pre_edition(array(
	'args' => array(
		'action' => 'instituer',
		'table' => 'spip_auteurs',
		'statut_ancien' => '8aconfirmer',
	),
	'data' => array('statut' => '1comite'),
));
if (
	($validation_native_redacteur['data']['statut'] ?? '') !== 'nouveau'
	|| ($validation_native_redacteur['data']['prefs'] ?? '') !== '1comite'
) {
	$erreurs[] = 'La validation administrative doit aussi préserver la cible 1comite.';
}
$refus_natif = inscription3_pre_edition(array(
	'args' => array(
		'action' => 'instituer',
		'table' => 'spip_auteurs',
		'statut_ancien' => '8aconfirmer',
	),
	'data' => array('statut' => '5poubelle'),
));
if (($refus_natif['data']['statut'] ?? '') !== '5poubelle' || isset($refus_natif['data']['prefs'])) {
	$erreurs[] = 'Le refus administratif doit conserver le statut 5poubelle.';
}
$telephones_internationaux_valides = array(
	'+33 6 12 34 56 78',
	'+1 (202) 555-0123',
	'+420 777 123 456',
);
$telephones_internationaux_invalides = array(
	'06 12 34 56 78',
	'+0123456789',
	'+33 téléphone',
	'+1234567890123456',
);
foreach ($telephones_internationaux_valides as $telephone) {
	if (!inscription4_telephone_international_valide($telephone)) {
		$erreurs[] = "Numéro international valide refusé : $telephone.";
	}
}
foreach ($telephones_internationaux_invalides as $telephone) {
	if (inscription4_telephone_international_valide($telephone)) {
		$erreurs[] = "Numéro international invalide accepté : $telephone.";
	}
}
$jeu = array(
	array(
		'saisie' => 'fieldset',
		'options' => array('nom' => 'groupe'),
		'saisies' => array(
			array(
				'saisie' => 'input',
				'options' => array('nom' => 'externe', 'sql' => "text DEFAULT '' NOT NULL"),
			),
			array(
				'saisie' => 'input',
				'options' => array('nom' => 'interne', 'sql' => "text DEFAULT '' NOT NULL"),
			),
		),
	),
);
$jeu_conditionnel = array(
	array(
		'saisie' => 'fieldset',
		'options' => array('nom' => 'groupe_conditionnel', 'afficher_si' => '@choix@ == "oui"'),
		'saisies' => array(
			array(
				'saisie' => 'input',
				'options' => array('nom' => 'herite', 'sql' => "text DEFAULT '' NOT NULL"),
			),
			array(
				'saisie' => 'input',
				'options' => array(
					'nom' => 'direct',
					'sql' => "text DEFAULT '' NOT NULL",
					'afficher_si' => '@detail@ != ""',
				),
			),
		),
	),
	array(
		'saisie' => 'input',
		'options' => array('nom' => 'inconditionnel', 'sql' => "text DEFAULT '' NOT NULL"),
	),
);
$conditions = inscription4_cextras_conditions_affichage($jeu_conditionnel);
if (
	count($conditions['herite'] ?? array()) !== 1
	|| count($conditions['direct'] ?? array()) !== 2
	|| isset($conditions['inconditionnel'])
) {
	$erreurs[] = 'Les conditions CExtras directes et héritées doivent être signalées dans la configuration.';
}
$filtre = inscription4_cextras_filtrer_saisies($jeu, array('externe'), array('interne'));
$modifie = false;
$configure = inscription4_cextras_mettre_a_jour_options(
	$filtre,
	array('externe' => array('afficher' => 'on', 'obligatoire' => 'on')),
	$modifie
);
if (
	count($configure) !== 1
	|| count($configure[0]['saisies']) !== 1
	|| ($configure[0]['saisies'][0]['options']['obligatoire'] ?? '') !== 'oui'
	|| ($configure[0]['saisies'][0]['options']['inscription4_formulaire'] ?? '') !== 'on'
	|| !$modifie
	|| inscription4_cextras_est_obligatoire('non')
	|| !inscription4_cextras_est_obligatoire('oui')
) {
	$erreurs[] = 'Le filtrage hiérarchique ou l’obligation CExtras est incorrect.';
}
$modifie = false;
$configure = inscription4_cextras_mettre_a_jour_options(
	$configure,
	array('externe' => array('afficher' => '', 'obligatoire' => '')),
	$modifie
);
if (
	!$modifie
	|| ($configure[0]['saisies'][0]['options']['obligatoire'] ?? null) !== ''
	|| ($configure[0]['saisies'][0]['options']['inscription4_formulaire'] ?? null) !== ''
) {
	$erreurs[] = 'La désactivation doit également modifier directement les options CExtras.';
}

if ($erreurs) {
	fwrite(STDERR, implode(PHP_EOL, $erreurs) . PHP_EOL);
	exit(1);
}

echo "Conformité Inscription 4 : OK" . PHP_EOL;
