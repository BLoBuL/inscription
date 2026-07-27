<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

$GLOBALS[$GLOBALS['idx_lang']] = array(
	'message_auteur_inscription_attente_contenu_admin' => '@nom@ požádal(a) o vytvoření účtu. Tento účet zatím nelze používat. Schválením žádosti této osobě automaticky odešlete zabezpečený odkaz pro nastavení hesla; žádost můžete také zamítnout.',
	'message_auteur_inscription_attente_contenu_user' => 'vaše registrace byla přijata. V tuto chvíli nemusíte nic dělat. Váš účet musí nejprve schválit správce. Poté obdržíte zabezpečený odkaz pro nastavení hesla.',
	'message_auteur_inscription_attente_titre_admin' => '[@nom_site_spip@] Účet uživatele @nom@ čeká na schválení',
	'message_auteur_inscription_attente_titre_user' => '[@nom_site_spip@] Registrace přijata — čeká na schválení',
	'message_auteur_inscription4_valider_contenu_user' => 'váš účet byl schválen správcem webu. Pro dokončení přístupu si nyní nastavte heslo pomocí zabezpečeného odkazu níže.',
	'message_auteur_inscription4_valider_titre_user' => '[@nom_site_spip@] Váš účet byl schválen — nastavte si heslo',
	'message_auteur_inscription4_validation_lien' => 'Pomocí tohoto dočasného zabezpečeného odkazu si nastavte heslo:',
);
