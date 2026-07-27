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
if ((string) $paquet['schema'] !== '4.0.0') {
	$erreurs[] = 'Le schéma doit inclure la migration de configuration CExtras.';
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

$pipeline = file_get_contents($racine . '/inscription3_pipelines.php');
foreach (array('_nano_sha256', 'generer_htpass') as $motif) {
	if (strpos($pipeline, $motif) !== false) {
		$erreurs[] = "Construction manuelle de mot de passe détectée : $motif.";
	}
}
if (preg_match('/\\$val\\[(?:[\'"])(?:pass|htpass|alea_actuel|alea_futur)(?:[\'"])\\]\\s*=/', $pipeline)) {
	$erreurs[] = 'Écriture directe d’un champ d’authentification détectée.';
}

$options = file_get_contents($racine . '/inscription3_options.php');
if (preg_match('/function\s+envoyer_inscription\s*\(/', $options)) {
	$erreurs[] = 'Le mail natif envoyer_inscription de SPIP ne doit pas être neutralisé.';
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
	|| strpos($configuration_cextras, '_nocreation"') !== false
) {
	$erreurs[] = 'Le tableau CExtras doit utiliser la configuration structurée Inscription 4.';
}
$configuration = file_get_contents($racine . '/formulaires/configurer_inscription3.html');
if (
	strpos($configuration, 'password_complexite') !== false
	|| strpos($configuration, 'password_reset') !== false
) {
	$erreurs[] = 'La configuration ne doit plus proposer de contrôles de mot de passe obsolètes.';
}

$pipeline_cextras = file_get_contents($racine . '/inscription3_pipelines.php');
if (strpos($pipeline_cextras, 'saisies_verifier($saisies_cextras)') === false) {
	$erreurs[] = 'Les Champs Extras doivent être vérifiés par l’API Saisies.';
}
if (
	strpos($pipeline_cextras, "config['password_reset']") !== false
	|| strpos($pipeline_cextras, "config['inscription3/password_complexite']") !== false
) {
	$erreurs[] = 'Inscription 4 ne doit pas masquer ou réécrire le parcours de mot de passe natif.';
}
$notification_securisee = file_get_contents($racine . '/notifications/inscription4_auteur.php');
$modele_securise = file_get_contents($racine . '/notifications/inscription4_auteur_inscription_valider.html');
if (
	strpos($pipeline_cextras, "\$notifications('inscription4_auteur'") === false
	|| strpos($notification_securisee, 'auteur_lire_jeton((int) $id_auteur, true)') === false
	|| strpos($notification_securisee, 'inscription4_auteur_inscription_valider') === false
	|| strpos($modele_securise, '#ENV{url_reset}') === false
	|| preg_match('/#ENV\\{(?:pass|password)\\}/i', $modele_securise)
) {
	$erreurs[] = 'La validation doit utiliser une notification Inscription 4 non surchargeable et un lien SPIP sécurisé.';
}
$administration = file_get_contents($racine . '/inscription3_administrations.php');
if (!preg_match("/\\\$maj\\['create'\\]\\[\\]\\s*=\\s*array\\('i3_migrer_pays'\\)/", $administration)) {
	$erreurs[] = 'Une première activation d’Inscription 4 doit migrer les pays historiques.';
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

if (!defined('_ECRIRE_INC_VERSION')) {
	define('_ECRIRE_INC_VERSION', 1);
}
require_once $racine . '/formulaires/inscription3_cextras_fonctions.php';
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
$filtre = inscription4_cextras_filtrer_saisies($jeu, array('externe'), array('interne'));
$configure = inscription4_cextras_appliquer_obligation(
	$filtre,
	array('externe' => array('obligatoire' => 'on'))
);
if (
	count($configure) !== 1
	|| count($configure[0]['saisies']) !== 1
	|| ($configure[0]['saisies'][0]['options']['obligatoire'] ?? '') !== 'oui'
	|| inscription4_cextras_est_obligatoire('non')
	|| !inscription4_cextras_est_obligatoire('oui')
) {
	$erreurs[] = 'Le filtrage hiérarchique ou l’obligation CExtras est incorrect.';
}

if ($erreurs) {
	fwrite(STDERR, implode(PHP_EOL, $erreurs) . PHP_EOL);
	exit(1);
}

echo "Conformité Inscription 4 : OK" . PHP_EOL;
