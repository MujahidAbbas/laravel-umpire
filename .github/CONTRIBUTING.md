# Contributing

Thanks for helping with Umpire for Laravel.

## Bugs, questions and ideas

Report bugs in [Issues](https://github.com/MujahidAbbas/laravel-umpire/issues/new/choose), with
the bug report form. A good bug report names the Umpire, laravel/mcp, Laravel and PHP versions,
the guard (Passport, Sanctum or other), and the smallest tool or server that shows the problem.

Questions and ideas go to
[GitHub Discussions](https://github.com/MujahidAbbas/laravel-umpire/discussions).

Security problems never go to an issue or a discussion: see [SECURITY](SECURITY.md).

## Before you open a pull request

Open an issue (for a bug) or a discussion (for anything else) before you open a pull request,
and wait until the change is agreed there. A pull request without one may be closed. This saves you from building something that does not
fit the package.

## AI disclosure

If an AI tool wrote or changed any part of your pull request, say so in its description: which
tool, and what it did. AI-assisted pull requests are welcome; undisclosed ones are not.

## Pull requests

- One change per pull request, branched from `main`.
- Add tests for every fix and feature. Tests use Pest and Testbench, and fixtures use a help-desk
  domain with Faker data.
- Umpire supports only the latest Laravel and PHP majors.
- Update the README when behavior changes.

## Running the checks

```bash
composer install
composer test
```

`composer test` runs Larastan, Pint in check mode, type coverage (100% required) and the test
suite. Run `composer lint` to fix code style. CI also runs the suite on PHP 8.3 to 8.5, on the
lowest dependency versions, against laravel/mcp `dev-main`, on PostgreSQL and MySQL, and
without Passport installed.
