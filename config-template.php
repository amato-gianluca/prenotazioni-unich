<?php
define('DB_USER_ZEUS', '');
define('DB_PASSWORD_ZEUS', '');
define('DB_DSN_ZEUS', 'mysql:host=zeus.unich.it;dbname=sitodec_esse3');

define('DB_USER_PRENOTAZIONI', '');
define('DB_PASSWORD_PRENOTAZIONI', '');
define('DB_DSN_PRENOTAZIONI', 'pgsql:host=localhost;port=5431;dbname=postgres');

define('SESSION_NAME', 'prenotazioni2020');
define('BASE', 'http://teledidattica.unich.it/prenotazioni');

define('DEBUG_KEY', '');
define('DEBUG_VISUAL_KEY', '');

define('TRACKING_USERS', [ 'username1', 'username2' ]);
define('SUPER_USERS', [ 'username1', 'username2' ]);
define('ADMINISTRATIVE_USERS', [ 'username1' => [ 'cdscod1', 'cdscod2' ],
                                 'username2' => [ 'cdscod3', 'cdscod4' ] ]);
?>
