# Changelog — Inscription 3 vers Inscription 4

Ce document décrit la branche native SPIP 4. Les versions 3.x restent
destinées aux anciennes branches de SPIP et ne doivent pas être remplacées
sur un site qui n’utilise pas SPIP 4.

## Ruptures principales avec Inscription 3

### Plateforme

- Le préfixe du paquet devient `inscription4`.
- La compatibilité est limitée à SPIP `[4.1.0;4.*]`.
- Inscription 4 procure toujours `inscription3` pour les plugins dépendants.
- Les anciennes API publiques indispensables et les pipelines `i3_*` sont
  conservés.

### Authentification

- Le plugin ne crée, ne stocke et n’envoie plus de mot de passe.
- La confirmation et la définition du mot de passe utilisent les jetons
  chiffrés et les liens natifs de SPIP 4.
- Le statut `nouveau` et la colonne `prefs` retrouvent leur rôle natif.
- La validation administrative utilise `8aconfirmer`, puis repasse par
  `nouveau` avant la première connexion.

### Champs Extras

- La configuration n’utilise plus une mécanique parallèle pour décider si un
  Champ Extra apparaît ou est obligatoire.
- **Formulaire** écrit dans `options.inscription4_formulaire`.
- **Obligatoire** écrit dans `options.obligatoire`.
- Les conditions `afficher_si` sont respectées et signalées implicitement dans
  l’interface.
- Les champs sont vérifiés par Saisies, une seule fois.

### Sécurité

- Les requêtes de recherche et de validation sont quotées.
- Les paramètres GET ne sont plus fusionnés dans les options des vérificateurs.
- Une réinscription ne peut plus écraser les informations d’un auteur existant.
- La page privée des utilisateurs et ses actions sont protégées par une
  autorisation administrateur.
- Les changements de statut et suppressions logiques utilisent l’API auteurs
  de SPIP.

## Versions Inscription 4

### 4.1.11

- Sécurisation des recherches SQL et de la vérification des signatures.
- Suppression de l’injection des paramètres GET dans les vérificateurs.
- Ciblage exclusif de l’auteur retourné par le formulaire natif SPIP.
- Protection contre l’écrasement d’un profil lors d’une réinscription.
- Conservation d’un compte existant en `8aconfirmer`.
- Utilisation de `auteur_modifier()` avec exceptions d’autorisation limitées.
- Protection administrateur de `inscription3_adherents`.
- Sécurisation des suppressions individuelles et en masse.
- Ajout des notifications explicites de refus.
- Ajout des traductions tchèques des notifications de refus.
- Correction de l’affichage du statut réel `8aconfirmer`.
- Correction du libellé libre du formulaire d’identification.

### 4.1.10

- Alignement de l’acceptation administrative sur le cycle natif SPIP 4.
- Stockage du statut final dans `prefs`.
- Passage intermédiaire par `nouveau` jusqu’à la première authentification.
- Conservation directe de `5poubelle` lors d’un refus.

### 4.1.9

- Ajout des notifications tchèques pour l’attente administrative et
  l’acceptation du compte.

### 4.1.8

- Sécurisation du parcours de validation.
- Notification d’acceptation propre à Inscription 4, non dépendante des
  anciennes surcharges.
- Génération du lien de définition du mot de passe par l’API de jetons SPIP.
- Suppression des mots de passe et secrets dans les modèles de courriel.
- Prévention du double envoi de la notification d’attente.

### 4.1.7

- Fiabilisation du JavaScript compilé de validation téléphonique.
- Alignement des contrôles navigateur sur la validation serveur.

### 4.1.6

- Remplacement de la saisie historique de création par la saisie `date`
  native, avec gestion de l’heure.
- Validation réellement internationale des numéros, compatible E.164.
- Explication explicite du format dans la configuration.

### 4.1.5

- Retrait de la mention redondante indiquant que l’obligation provient de la
  déclaration Champs Extras.

### 4.1.4

- Compatibilité du filtre de détection des conditions Champs Extras avec les
  appels de filtres SPIP sans argument explicite.

### 4.1.3

- Signalement visuel des champs dont l’affichage est conditionnel.
- Prise en compte des conditions directes et de celles héritées des groupes.

### 4.1.2

- Vérification des Champs Extras confiée à l’API Saisies.
- Suppression de la double vérification parallèle.

### 4.1.1

- Finalisation des libellés et de la lecture des options CExtras natives.

### 4.1.0

- Les colonnes **Formulaire** et **Obligatoire** modifient directement les
  déclarations Champs Extras.
- Migration versionnée des anciens réglages vers les options natives.
- Conservation d’une configuration contextuelle uniquement pour **Fiche** et
  **Table**.

### 4.0.0

- Création de la branche Inscription 4 native SPIP 4.
- Nouveau préfixe `inscription4` et compatibilité limitée à SPIP 4.
- Conservation des points d’entrée historiques nécessaires.
- Remplacement du mot de passe envoyé par un lien sécurisé SPIP.
- Première migration de la configuration Champs Extras.
- Migration des pays historiques vers le plugin Pays.

## Points à contrôler après migration

1. Ouvrir la configuration Inscription 4 sans l’enregistrer immédiatement.
2. Comparer les champs affichés avec l’ancienne configuration.
3. Vérifier les options Champs Extras dans Interface Champs Extras.
4. Vérifier les champs conditionnels et leurs champs pilotes.
5. Contrôler le statut final choisi : `6forum` ou `1comite`.
6. Contrôler les destinataires des notifications.
7. Tester une inscription neuve avec une adresse de test.
8. Tester séparément acceptation et refus si la validation administrative est
   activée.
9. Vérifier le lien reçu et la première connexion.
10. Contrôler les éventuelles surcharges de thème ou de plugin des formulaires
    Inscription 3.

Les surcharges FO, BO ou CORE ne sont pas automatiquement adaptées par le
plugin : elles doivent être auditées et mises à niveau séparément.
