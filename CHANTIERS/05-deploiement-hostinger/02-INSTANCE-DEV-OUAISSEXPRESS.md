# Configuration de l'Instance dev.ouaissexpress.com

## 1. Structure des Répertoires sur Hostinger
```
/home/u405480831/domains/ouaissexpress.com/
├── public_html/                     # Ancien site existant (PHP 7.4 protégé)
└── public_html/dev/                 # Nouveau projet Laravel 13 / React 19 (PHP 8.4)
    ├── .env                         # Variables de production sécurisées
    ├── .htaccess                    # Réécriture vers public/
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── database/
    ├── public/                      # Point d'entrée web (index.php, build/...)
    ├── resources/
    ├── routes/
    ├── storage/
    └── vendor/
```

## 2. Fichier .htaccess Racine de dev/
Permet à Apache d'acheminer les requêtes HTTP/HTTPS directement dans le dossier `public/` de Laravel sans exposer les fichiers sensibles (`.env`, `storage`, `app`).
