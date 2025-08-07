<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Inclusions nécessaires au bon fonctionnement de formulaires/inscription3_cextras.html
 *
 * Notamment pour la fonction champs_extras_objet
 *
 */
if (defined('_DIR_PLUGIN_CEXTRAS')) {
	include_spip('cextras_pipelines');
	include_spip('inc/cextras');
}

/**
 * Aplati les champs extras des fieldsets d'un objet.
 *
 * Cette fonction prend en entrée un objet contenant des champs extras, identifie les champs de type "fieldset",
 * et les remplace par les champs qu'ils contiennent.
 *
 * @param string $objet Le type d'objet SPIP (par exemple, 'spip_auteurs', 'spip_articles', etc.).
 * @return array Un tableau contenant tous les champs extras de l'objet, sans les fieldsets.
 */
function champs_extras_objet_aplati($objet) {

    // Récupère les champs extras de l'objet
    $champs = champs_extras_objet($objet);
    $champs_aplatis = array();

    // Parcourt chaque champ pour vérifier s'il s'agit d'un fieldset
    foreach ($champs as $champ) {
        if ($champ['saisie'] == 'fieldset' AND !empty($champ['saisies'])) {

            // Si le champ est un fieldset, on  garde les champs qu'il contient
            $sous_champs = $champ['saisies'];
            unset($champ['saisies']); // on vide les saisies du fieldset

            // Ajoute le fieldset (sans ses sous-champs) au tableau aplati
            $champs_aplatis[] = $champ;

            // Ajoute chaque sous-champ du fieldset au tableau aplati
            foreach ($sous_champs as $sous_champ) {
                $champs_aplatis[] = $sous_champ;
            }
        } else {
            // Si ce n'est pas un fieldset, ajoute le champ directement au tableau aplati
            $champs_aplatis[] = $champ;
        }
    }

    // Retourne le tableau des champs aplatis
    return $champs_aplatis;
}
