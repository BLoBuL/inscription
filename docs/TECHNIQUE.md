# Documentation technique d’Inscription 4

## 1. Identité et compatibilité

| Élément | Valeur |
| --- | --- |
| Préfixe du paquet | `inscription4` |
| Version documentée | `4.1.11` |
| Schéma | `4.1.0` |
| SPIP | `[4.1.0;4.*]` |
| Compatibilité fournie | `inscription3` version `4.1.0` |
| Méta de configuration | `inscription3` |

Les noms historiques sont volontairement conservés pour les formulaires, la
méta, les pipelines d’extension et certaines fonctions publiques. Le préfixe
du paquet détermine cependant les handlers appelés par SPIP ; les fonctions
`inscription4_*` servent donc de pont vers les implémentations historiques.

## 2. Organisation du code

| Emplacement | Responsabilité |
| --- | --- |
| `paquet.xml` | dépendances, pipelines, menus et compatibilité |
| `base/inscription3.php` | déclaration des Champs Extras historiques |
| `inscription3_administrations.php` | installation et migrations |
| `inscription3_options.php` | statut `8aconfirmer` et adaptation du mail natif |
| `inscription3_autoriser.php` | autorisations de profil et du tableau privé |
| `inscription3_pipelines.php` | orchestration CVT, statuts et compatibilité |
| `formulaires/inscription3_cextras_fonctions.php` | lecture et écriture CExtras |
| `notifications/` | modèles et dispatcher Inscription 4 |
| `verifier/` | vérificateurs spécialisés |
| `tests/inscription4_conformite.php` | contrôles statiques et tests purs |

Les traitements de formulaires, notifications et changements de statut
doivent rester concentrés dans les helpers du plugin. Une surcharge ne doit
pas recopier le parcours d’authentification du noyau.

## 3. Cycle d’inscription

### 3.1 Parcours natif sans validation administrative

```text
Soumission
    ↓
SPIP crée l’auteur
statut = nouveau
prefs = 6forum ou 1comite
    ↓
SPIP attribue un jeton chiffré
    ↓
Courriel avec lien spip_pass
    ↓
Définition du mot de passe
    ↓
Première authentification
    ↓
statut = valeur conservée dans prefs
```

Inscription 4 complète le profil uniquement pour l’identifiant `id_auteur`
retourné par `formulaires_inscription_traiter_dist()`. Il ne recherche jamais
la cible de mise à jour à partir d’une adresse postée.

### 3.2 Parcours avec validation administrative

```text
Soumission
    ↓
statut = 8aconfirmer
    ↓
Notifications d’attente
    ├── refus → statut = 5poubelle → notifications de refus
    └── accord
          ↓
          prefs = statut final
          statut = nouveau
          ↓
          lien sécurisé de définition du mot de passe
          ↓
          première authentification
          ↓
          statut final
```

Le pipeline `pre_edition` intercepte uniquement une institution depuis
`8aconfirmer` vers `6forum` ou `1comite`. Il déplace la cible dans `prefs` et
remplace le statut demandé par `nouveau`. Le refus `5poubelle` n’est pas
transformé.

### 3.3 Réinscription

Le pipeline `formulaire_verifier` mémorise côté serveur l’identifiant et le
statut d’un auteur déjà associé à l’adresse soumise. Après le traitement natif :

- le profil existant n’est jamais mis à jour depuis les données publiques ;
- un compte `8aconfirmer` reste en attente administrative ;
- les comptes déjà utilisables restent soumis aux décisions du noyau ;
- le mail natif de récupération n’est pas remplacé pour un compte actif.

Les marqueurs internes `_inscription4_*` ne doivent jamais être acceptés
depuis les options des vérificateurs ou utilisés comme autorisation.

## 4. Modifications d’auteurs

Toute modification interne passe par :

```php
inscription4_auteur_modifier_interne($id_auteur, $set);
```

Ce helper :

1. charge `action/editer_auteur` ;
2. ouvre une exception `modifier` et `instituer` limitée à l’auteur ciblé ;
3. appelle `auteur_modifier()` ;
4. referme systématiquement les exceptions dans un bloc `finally`.

Les formulaires ne doivent pas utiliser `sql_updateq()` ou `sql_delete()` pour
modifier un auteur. La suppression proposée par l’interface place l’auteur en
`5poubelle` et invalide ses sessions ; elle conserve ainsi les hooks, liens et
invariants SPIP.

## 5. Champs Extras

### 5.1 Sources de vérité

Pour un Champ Extra `telephone`, les réglages natifs sont :

```php
array(
    'options' => array(
        'nom' => 'telephone',
        'inscription4_formulaire' => 'on',
        'obligatoire' => 'oui',
    ),
);
```

- `inscription4_formulaire` pilote le formulaire d’inscription.
- `obligatoire` pilote la validation Saisies.
- La méta `inscription3/cextras_inscription` ne conserve directement que les
  réglages contextuels qui ne font pas partie de la déclaration native,
  notamment **Fiche** et **Table**.

La valeur d’obligation est normalisée par
`inscription4_cextras_est_obligatoire()` afin d’accepter les représentations
historiques compatibles (`on`, `oui`, booléen vrai).

### 5.2 Écriture

`inscription4_cextras_enregistrer_options_natives()` :

1. filtre les noms sur les champs réellement configurables ;
2. parcourt récursivement les saisies et fieldsets ;
3. met à jour `inscription4_formulaire` et `obligatoire` ;
4. écrit la méta `champs_extras_spip_auteurs` ;
5. invalide les caches CExtras ;
6. enregistre séparément les réglages de contexte.

Une erreur d’écriture empêche le CVT de confirmer l’enregistrement de la
configuration.

### 5.3 Lecture et filtrage

- `inscription4_cextras_saisies_inscription()` retourne uniquement les champs
  activés pour l’inscription.
- `inscription4_cextras_saisies_contexte('fiche')` et `('table')` filtrent les
  champs selon leur contexte.
- `inscription4_cextras_conditions_affichage()` remonte les conditions
  `afficher_si` directes et héritées des groupes.
- `saisies_verifier()` effectue la validation du jeu filtré une seule fois.

Le formulaire d’inscription ne repasse pas par `i3_verifications_specifiques` :
les anciens contrôleurs (téléphone, code postal, signature, etc.) sont réservés
au formulaire historique `editer_auteur`. Les Champs Extras d’Inscription 4
déclarent directement leur `verifier` dans la saisie et sont contrôlés une seule
fois par `saisies_verifier()`. Le pipeline public `i3_verifications_specifiques`
reste disponible pour les extensions et pour la compatibilité Inscription 3.

Une surcharge FO doit consommer ces helpers. Elle ne doit plus tester
`*_nocreation` comme source active de configuration.

## 6. Pipelines

### 6.1 Pipelines SPIP utilisés

- `declarer_champs_extras`
- `autoriser`
- `pre_insertion`
- `affiche_droite`
- `recuperer_fond`
- `formulaire_charger`
- `formulaire_verifier`
- `formulaire_traiter`
- `editer_contenu_objet`
- `notifications_destinataires`
- `taches_generales_cron`
- `pre_edition`
- `post_edition`
- `saisies_construire_formulaire_config`
- pipelines OpenID historiques

### 6.2 Pipelines publics `i3_*`

- `i3_charger_formulaire`
- `i3_verifier_formulaire`
- `i3_traiter_formulaire`
- `i3_form_debut`
- `i3_form_fin`
- `i3_validation_methods`
- `i3_cfg_form`
- `i3_exceptions_des_champs_auteurs_elargis`
- `i3_exceptions_chargement_champs_auteurs_elargis`
- `i3_verifications_specifiques`
- `i3_definition_champs`

Un plugin tiers peut enrichir `i3_verifier_formulaire`, mais il doit renvoyer
le tableau d’erreurs CVT reçu. Il ne doit ni écrire un mot de passe, ni
modifier un auteur identifié uniquement par une donnée postée.

### 6.3 API historiques garanties

Les fonctions suivantes doivent rester disponibles :

```php
inscription3_taches_generales_cron($taches);
inscription3_ajouter_menus($menus);
inscription3_formulaire_charger($flux);
```

Tout renommage ou changement de signature est une rupture de compatibilité.

## 7. Notifications

Le dispatcher sécurisé est :

```php
notifications_inscription4_auteur($quoi, $id_auteur, $options);
```

Cas gérés :

| Transition | Utilisateur | Administrateurs |
| --- | --- | --- |
| création en `8aconfirmer` | attente sans secret ni lien | demande à traiter |
| `8aconfirmer` → accepté | lien SPIP de définition du mot de passe | compte accepté |
| `8aconfirmer` → `5poubelle` | compte refusé | compte refusé |

Le lien d’acceptation est construit avec :

```php
$jeton = auteur_lire_jeton($id_auteur, true);
$url = generer_url_public('spip_pass', 'p='.$jeton, true);
```

Les modèles ne doivent jamais recevoir ou afficher `pass`, `password`,
`htpass`, `cookie_oubli` ou une valeur équivalente.

Les destinataires administrateurs proviennent :

- des auteurs sélectionnés dans `inscription3/admin_notifications` ;
- des adresses valides de `inscription3/destinataire_supp_notifications`.

## 8. Autorisations

`autoriser_inscription3adherents_voir_dist()` réserve la page étendue des
utilisateurs au statut `0minirezo`.

La sécurité est vérifiée à trois niveaux :

1. dans le squelette privé avec `#AUTORISER` ;
2. dans `charger()` et `verifier()` du formulaire ;
3. à nouveau dans `traiter()` avant chaque action.

Les administrateurs ne peuvent pas être placés en corbeille par l’action en
masse de ce formulaire.

## 9. Installation et migrations

La fonction historique `inscription3_upgrade()` reste l’entrée d’installation
déclarée par les ponts `inscription4_administrations.php`.

Étapes significatives :

| Schéma | Migration |
| --- | --- |
| `create` | configuration initiale, Pays, CExtras |
| `4.0.0` | conversion de la configuration CExtras historique |
| `4.0.1` | migration des pays |
| `4.1.0` | écriture des options CExtras natives |

La migration Pays compare les codes ISO et ne dépend pas des anciens
identifiants numériques.

La désinstallation efface les métas du plugin. Elle ne doit pas être utilisée
comme procédure de retour arrière sans sauvegarde préalable.

## 10. Règles de sécurité pour les évolutions

- Utiliser `sql_quote()`, `sql_in()` et les identifiants validés pour toute
  requête dynamique.
- Ne jamais fusionner `$_GET`, `$_POST` ou `$_REQUEST` dans des options
  internes.
- Cibler un auteur par l’identifiant produit par SPIP, pas par l’email posté.
- Utiliser `auteur_modifier()` pour les changements de profil ou de statut.
- Refermer toute `autoriser_exception()` dans tous les chemins d’exécution.
- Ne jamais créer, journaliser, envoyer ou recopier un mot de passe.
- Laisser SPIP générer et vérifier les jetons de confirmation.
- Vérifier les autorisations dans `traiter()`, même si le squelette est privé.
- Faire valider les Champs Extras par Saisies, sans seconde mécanique.
- Conserver les API historiques listées dans cette documentation.

## 11. Tests et recette

### Tests automatisés

```bash
php tests/inscription4_conformite.php
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

### Matrice fonctionnelle minimale

| Scénario | Résultat attendu |
| --- | --- |
| inscription neuve sans validation admin | `nouveau`, lien SPIP, activation à la connexion |
| inscription neuve avec validation admin | `8aconfirmer`, mail d’attente |
| acceptation en visiteur | `nouveau`, `prefs=6forum`, lien sécurisé |
| acceptation en rédacteur | `nouveau`, `prefs=1comite`, lien sécurisé |
| refus | `5poubelle`, notifications explicites |
| nouvelle soumission avec email existant | aucun écrasement du profil |
| champ conditionnel masqué | aucune erreur obligatoire indue |
| accès tableau par visiteur | accès refusé |
| suppression en masse d’un administrateur | action refusée |

Les tests d’inscription réels envoient des courriels et modifient la base :
ils doivent utiliser des adresses et comptes de recette dédiés.
