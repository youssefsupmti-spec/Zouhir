# Spécifications de l'Authentification & Rôles Utilisateurs

## 1. Règles d'Or de Sécurité
1. **Zéro élévation de privilèges client** : Le formulaire d'inscription ne permet en aucun cas de choisir un rôle administratif. Le rôle `UserRole::CLIENT` est injecté de manière immuable dans l'Action `RegisterClientAction`.
2. **Hachage BCRYPT** : Mots de passe obligatoirement hachés via `Hash::make()` avec coût standard sécurisé.
3. **Activation de compte** : Les comptes créés ont le statut `is_active = true` par défaut pour la Phase 1, avec vérification à chaque connexion.

## 2. Découpage DDD
- `app/Domain/Auth/Enums/UserRole.php` : Enum typée avec libellés et badges officiels.
- `app/Domain/Clients/Enums/ClientType.php` : Enum typée (`e_commerce`, `entreprise`, `particulier`).
- `app/Application/Auth/Actions/RegisterClientAction.php` : Transaction atomique de création du compte utilisateur et des métadonnées client.
- `app/Http/Requests/Auth/LoginRequest.php` : Validation, normalisation des emails et throttling par clé IP.
- `app/Http/Requests/Auth/RegisterRequest.php` : Validation stricte des données d'inscription.
