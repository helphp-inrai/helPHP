<?php

// la variable $tl_module_name est définie à l'avance en globale dans la classe spade_module qui importe ce fichier

$tl_data = array(
    'fr'=>array(
        'module_name'=>'Connexion',

        'connection'=>'Connexion',
        'connect'=>'Connecte',
        'bienvenue'=>'Bienvenue',
        'login'=>'Identifiant :',
        'password'=>'Mot de passe :',
        'ok'=>'Ok',
        'deconnection'=>'Déconnexion',
        'inscription'=>'Inscription',

        'placeholder_login'=>'Identifiant',
        'placeholder_password'=>'Mot de passe',

        'logfail'=>'Erreur, tentatives restantes:', // TODO : à virer, vérifier si ça se retrouve dans le code
        'toomuchfail'=>'Trop de tentatives. Attendez 24H SVP', // TODO : à virer, vérifier si ça se retrouve dans le code

        'connection_active' => 'La connexion est déjà active.',
        'incorrect_parameters' => 'Les paramètres de connexion sont incorrects',
        'account_banned' => 'Compte banni !',
        'connexion_success' => 'Connexion réussie.',
        'attemptstockage_left' => 'Tentatives restantes : $1',
        'disconnect_success' => 'Déconnexion réussie.',
        'no_connection' => 'Aucun compte connecté.',
        'account_already_active' => 'Ce compte a déjà été activé.',
        'activation_success' => 'Votre compte a bien été activé.',
        'account_not_activated' => 'Connexion impossible, votre compte est en attente d\'activation',
        'invalid_activation_key' => 'Cette clé d\'activation est invalide ou a déjà été utilisée !',

        'autoHelp'=>'Ne plus afficher au démarrage :',

    ),
    'en'=>array(
        'module_name'=>'Connection',

        'connection'=>'Connection',
        'connect'=>'Connect',
        'bienvenue'=>'Welcome',
        'login'=>'Login:',
        'password'=>'Password:',
        'ok'=>'Ok',
        'deconnection'=>'Disconnect',
        'inscription'=>'Registration',

        'placeholder_login'=>'Login',
        'placeholder_password'=>'Password',

        'log_fail'=>'Problem with your credentials. Remaining attempts: $1',
        'toomuchfail'=>'Too many attempts. Please wait 24 hours.',

        'connection_active' => 'The connection is already activated.',
        'incorrect_parameters' => 'Connection parameters are incorrect',
        'account_banned' => 'Account banned!',
        'connexion_success' => 'Connection successful.',
        'attemptstockage_left' => 'Remaining attempts: $1',
        'disconnect_success' => 'Disconnection successful.',
        'no_connection' => 'No account connected.',
        'account_already_active' => 'This account has already been activated.',
        'activation_success' => 'Your account has been activated.',
        'account_not_activated' => 'Connection impossible; your account is awaiting activation.',
        'invalid_activation_key' => 'This activation key is invalid or has already been used.',

        'autoHelp'=>'Do not display again on startup:',

    ),
    'es'=>array(
        'connection'=>'Conexión',
        'bienvenue'=>'Bienvenida',
        'login'=>'Login :',
        'password'=>'Contraseña :',
        'ok'=>'Ok',
        'deconnexion'=>'Desconexión',
        'inscription'=>'Inscripción',

        'placeholder_login'=>'Información de acceso',
        'placeholder_password'=>' Contraseña',

        'logfail'=>'Error, intentos restantes :',
        'toomuchfail'=>'Demasiados intentos, por favor espere 24H',

        'connection_active' => 'Conexión ya activada.',
        'incorrect_parameters' => 'Los ajustes de conexión son incorrectos',
        'account_banned' => 'Cuenta prohibida!',
        'connexion_success' => 'Conexión sucesiva.',
        'attemptstockage_left' => 'Intentos de remanencia: $1',
        'disconnect_success' => 'Desconexión exitosa.',
        'no_connection' => 'Ninguna cuenta conectada.',
        'account_already_active' => 'Cuenta ya activada. ',
        'activation_success' => 'Cuenta activada.',
        'account_not_activated' => 'Conexión imposible, su activación está pendiente',
        'invalid_activation_key' => 'Esta clave de activación no es válida o ya está siendo utilizada!',

        'autoHelp'=>'No mostrar al inicio :',

    ),
    'de'=>array(
        'connection'=>'Verbindung',
        'bienvenue'=>'Willkommen',
        'login'=>'Login :',
        'password'=>'Passwort :',
        'ok'=>'Okay',
        'deconnexion'=>'Trennung',
        'inscription'=>'Eintragung',
        'logfail'=>'Fehler, verbleibende Versuche :',
        'toomuchfail'=>'Zu viele Versuche, bitte warten 24H'

    )
);
?>
