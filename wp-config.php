<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'poralekha' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '7:x/U/cTWiY?:tin/HGL2+|_&1*w;lYm5@|#=Y!SMO6~-n/tF>*o)@]O0S-g)mjg' );
define( 'SECURE_AUTH_KEY',  'OP:_aP%&B_:stk/dqU~RYcbjp(f/:|jBAm0C|1C(&g%U)k64Je bqu;AZBj6/5Iw' );
define( 'LOGGED_IN_KEY',    'SC^+0scj^kh)W}~d* ksq6,x;]1w:6QabIF@a(A^YvM&~ g</7*r#$ejj!2M9Er.' );
define( 'NONCE_KEY',        '+)l>[>]a-mEpH34cp[sV]!j(tS_6#:>y_tHQM}=XN8jr{L7akK1tK+phGS}|yz|B' );
define( 'AUTH_SALT',        '^ZC_GsiNYP)jG:!uE/Q|I^$)nSf|g|8f]F3/ (^J7/n40sdOFq?38HR7E68ymf,;' );
define( 'SECURE_AUTH_SALT', '%Z8.qhDNJoM.uz6cBJm?H_gZD^=Zo2;f)7H0/uZlDgxq.QG{Xl/Fp&7MpE8;flFz' );
define( 'LOGGED_IN_SALT',   ',78O%*ti^j8%E#49L}rn(>);X`FOAtBQc:X[~aL!c CK%smI?qzhxbkzUl;ZR0^n' );
define( 'NONCE_SALT',       'I-[GBv3oT@XET*J`#-I-;k9Q,MCyy}mLbZ,TZ)SofJ6zxhanD)DMt>M71x87XYMR' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
