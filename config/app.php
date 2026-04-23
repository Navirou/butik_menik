<?php
// config/app.php

define('APP_NAME',    'Butik Menik Modeste');
define('APP_URL',     'http://localhost/butik-menik');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('SESSION_NAME','bmenik_sess');

// Role constants
define('ROLE_OWNER',    1);
define('ROLE_STAFF',    2);
define('ROLE_SUPPLIER', 3);
define('ROLE_CUSTOMER', 4);

// Role → dashboard path map
define('ROLE_REDIRECT', [
    ROLE_OWNER    => '/modules/owner/dashboard.php',
    ROLE_STAFF    => '/modules/staff/dashboard.php',
    ROLE_SUPPLIER => '/modules/supplier/dashboard.php',
    ROLE_CUSTOMER => '/modules/customer/dashboard.php',
]);
