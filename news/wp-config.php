<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, WordPress Language, and ABSPATH. You can find more information
 * by visiting {@link http://codex.wordpress.org/Editing_wp-config.php Editing
 * wp-config.php} Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'brickswf_wp498');

/** MySQL database username */
define('DB_USER', 'brickswf_wp498');

/** MySQL database password */
define('DB_PASSWORD', '79@!Sp9PH7');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'aaheqpkuet4wwtpqyzk2fp5w9nnxdpj1f7k6j7pykuj9jduljd9ug3akgvqqitua');
define('SECURE_AUTH_KEY',  'nqb4wo46lrkwyssw8qqe3tutpfk2wmel7gg8nkv5yjit2lgidgvixcxu5kgurjhe');
define('LOGGED_IN_KEY',    'pcn2bynldtwps1jmx1cdhm3f7e6ugvrltxdrxmivghfrhpbcqffuksvdqprd7kob');
define('NONCE_KEY',        '4xvr6agvwawsj5dzvygxgvfin7szk6lrm8lt5lg9c8anli98kd2glhujizgfylxy');
define('AUTH_SALT',        'gxsqmsptkucmbsokp9qwi3acpkcnrdq4ucdtdsxc5gxw3ng2yyqc9uvomx5za3qf');
define('SECURE_AUTH_SALT', 'hvcr0u3ovmnevliydrsh006xf3jcuc5aod2tonhig2o7rwf5avnohvnot4zgknpi');
define('LOGGED_IN_SALT',   'z4vpwdy3xxjhurf4dde3pif4hs1zkovs4wvnok0m01yy4rmgs0xmnqbqvmigkzfq');
define('NONCE_SALT',       'b1yze0ejyc6rsj1vrhd39enddqpsd4vpg1mu39kqe77ttqfbe9b4kmioqhbwhys0');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * WordPress Localized Language, defaults to English.
 *
 * Change this to localize WordPress. A corresponding MO file for the chosen
 * language must be installed to wp-content/languages. For example, install
 * de_DE.mo to wp-content/languages and set WPLANG to 'de_DE' to enable German
 * language support.
 */
define('WPLANG', '');

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
