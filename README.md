# Inscription 4

Inscription 4 étend la gestion native des auteurs de SPIP 4 avec :

- des champs de profil configurables ;
- l’intégration directe des Champs Extras ;
- une validation administrative facultative des comptes ;
- des notifications d’inscription, d’acceptation et de refus ;
- un tableau privé des utilisateurs ;
- la validation des données d’inscription et de profil.

Cette branche est exclusivement compatible avec **SPIP 4.1 à 4.x**. Elle
succède à Inscription 3 et conserve ses principaux points d’extension pour
faciliter la migration des plugins dépendants.

## Prérequis

- SPIP `[4.1.0;4.*]`
- Saisies `4.2.0` ou supérieur
- Champs Extras et Interface Champs Extras `[4.3.0;4.*]`
- API de vérification `1.0.6` ou supérieur
- Pays `4.2.1` ou supérieur
- Notifications `3.2.0` ou supérieur

Les plugins Z, Médiabox, Pages, OpenID restent facultatifs.

## Installation

1. Sauvegarder les fichiers et la base de données.
2. Désactiver l’ancienne copie d’Inscription 3 si elle est installée dans un
   autre répertoire.
3. Installer Inscription 4 dans `plugins/inscription`.
4. Activer le plugin depuis la gestion des plugins.
5. Ouvrir **Configuration → Configurer Inscription 4** et contrôler les
   réglages migrés.
6. Vider le cache SPIP.

Le paquet utilise le préfixe `inscription4`, mais il déclare :

```xml
<procure nom="inscription3" version="4.1.0" />
```

Les dépendances historiques sur `inscription3` restent donc satisfaites. Les
noms des formulaires, métas et principales API historiques sont également
conservés.

## Migration depuis Inscription 3

L’installation exécute automatiquement les migrations suivantes :

- reprise de la méta historique `inscription3` ;
- conversion éventuelle de la configuration d’Inscription 2 ;
- migration des anciens pays vers le plugin Pays par code ISO ;
- conversion des réglages CExtras historiques `*_nocreation` ;
- écriture directe des options `inscription4_formulaire` et `obligatoire`
  dans les déclarations Champs Extras.

Après la migration, vérifier en particulier :

- les colonnes **Formulaire**, **Fiche**, **Table** et **Obligatoire** ;
- les conditions d’affichage signalées par la mention **Conditionnel** ;
- le statut cible des nouveaux comptes ;
- l’activation éventuelle de la validation administrative ;
- les administrateurs et adresses supplémentaires destinataires des
  notifications.

Le détail des changements se trouve dans [CHANGELOG.md](CHANGELOG.md).

## Fonctionnement des inscriptions

### Sans validation administrative

Inscription 4 laisse SPIP gérer le parcours natif :

1. SPIP crée l’auteur avec le statut `nouveau`.
2. Le statut final demandé est conservé dans `prefs`.
3. SPIP envoie un lien sécurisé permettant de choisir le mot de passe.
4. À la première authentification, SPIP applique le statut final.

Aucun mot de passe n’est généré ou envoyé par Inscription 4.

### Avec validation administrative

1. Le compte est placé en `8aconfirmer`.
2. L’utilisateur reçoit un message indiquant que sa demande attend une
   décision administrative.
3. Les administrateurs configurés reçoivent une notification.
4. En cas d’acceptation, le compte repasse par le cycle natif SPIP :
   `8aconfirmer → nouveau`, avec le statut cible dans `prefs`.
5. L’utilisateur reçoit alors un lien sécurisé de définition du mot de passe.
6. En cas de refus, le compte passe en `5poubelle` et les notifications de
   refus sont envoyées.

Une nouvelle soumission utilisant l’adresse d’un compte existant ne peut pas
remplacer son profil. Le noyau SPIP reste responsable du parcours de
réinscription ou de récupération.

## Champs Extras

Les colonnes **Formulaire** et **Obligatoire** de la configuration agissent
directement sur la déclaration Champs Extras :

- `options.inscription4_formulaire` détermine la présence dans le formulaire
  d’inscription ;
- `options.obligatoire` est l’unique source de l’obligation.

Les vérifications sont exécutées une seule fois par l’API Saisies. Les
conditions `afficher_si`, directes ou héritées d’un fieldset, sont respectées
et signalées dans le tableau de configuration.

## Téléphones

L’option de validation internationale accepte tous les pays. Elle impose un
numéro compatible E.164 :

- préfixe `+` obligatoire ;
- indicatif pays ne commençant pas par zéro ;
- 7 à 15 chiffres après normalisation ;
- espaces, parenthèses, points et tirets acceptés pour la présentation.

Exemples valides : `+33 6 12 34 56 78`, `+1 (202) 555-0123`,
`+420 777 123 456`.

## Tableau privé

La page `?exec=inscription3_adherents` permet la recherche et les actions en
masse sur les auteurs. Elle expose des données personnelles et est donc
réservée aux administrateurs (`0minirezo`). Les changements de statut passent
par l’API auteurs de SPIP.

## Compatibilité pour les plugins dépendants

Les API historiques suivantes sont explicitement préservées :

- `inscription3_taches_generales_cron`
- `inscription3_ajouter_menus`
- `inscription3_formulaire_charger`

Les pipelines `i3_*` restent disponibles. Les handlers `inscription4_*`
délèguent aux implémentations historiques lorsque cela est nécessaire.

Voir [docs/TECHNIQUE.md](docs/TECHNIQUE.md) pour la liste des points
d’extension et les règles d’intégration.

## Tests

Depuis la racine du plugin :

```bash
php tests/inscription4_conformite.php
```

Pour contrôler la syntaxe de tous les fichiers PHP :

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

Le test de conformité vérifie notamment le paquet, les pipelines, le cycle des
statuts, les notifications, la configuration CExtras et la validation
téléphonique.

## Licence

GPL v3. Auteurs historiques : cmtmt2003, kent1 et BoOz.
