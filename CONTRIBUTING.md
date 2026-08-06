# Contributing

## Setup

```bash
git clone https://github.com/dotsunited/simple-login-for-doccheck.git
cd simple-login-for-doccheck
composer install
```

Clone or symlink the repository into `wp-content/plugins/` for WordPress testing.

## Issues

Bug reports should include the WordPress, PHP, and plugin versions; caching setup; expected and actual behavior; and any `doccheck_error` code.

Never publish DocCheck credentials. Send security reports privately to <info@dotsunited.de>.

## Changes

Follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/). Prefix globals with `simple_login_for_doccheck`, escape output, sanitize input, and use the `simple-login-for-doccheck` text domain. Keep [PHPDoc](https://developer.wordpress.org/coding-standards/inline-documentation-standards/php/) brief, but retain required summaries, `@since`, types, hook docs, and translator notes.

Run `composer dump-autoload` after adding, moving, or renaming PHP classes so the classmap stays current.

Before opening a pull request:

1. Branch from `main` and keep the change focused.
2. Update `README.md` for behavior or setting changes.
3. Run `composer run-script check`.
4. Describe testing, including the DocCheck flow when affected.

Use [Conventional Commits](https://www.conventionalcommits.org/), for example `fix: preserve the stored client secret`.

For local protection tests without DocCheck:

```php
add_filter( 'simple_login_for_doccheck_is_authenticated', '__return_true' );
```

## Translations

```bash
wp i18n make-pot . languages/simple-login-for-doccheck.pot
msgfmt languages/simple-login-for-doccheck-de_DE.po -o languages/simple-login-for-doccheck-de_DE.mo
msgfmt languages/simple-login-for-doccheck-de_DE_formal.po -o languages/simple-login-for-doccheck-de_DE_formal.mo
```

Commit the generated `.pot` and `.mo` files.

## Releases

1. Update the plugin header and `SIMPLE_LOGIN_FOR_DOCCHECK_VERSION`.
2. Regenerate translations.
3. Run `composer install --no-dev --optimize-autoloader` so the production autoloader is included in the archive.
4. Commit as `chore(release): v<version>` and tag `v<version>`.
5. Build the archive using `.distignore` and attach it to the GitHub release.

Releases follow [Semantic Versioning](https://semver.org/). Contributions are licensed under [GPL-2.0-or-later](LICENSE).
