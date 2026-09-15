# Changelog

All notable changes to `robinsonryan/vendor` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
On `0.x`, every minor release may break.

## [Unreleased]

## [0.2.0] - 2026-09-15

### Changed
- **BREAKING: PostgreSQL 18+ generates every primary key; the package no longer mints them in PHP.** `Vendor` drops Laravel's `HasUuids` for a `HasUuidPrimaryKey` trait that only sets `$incrementing = true` / `$keyType = 'string'`, and the `vendors` migration declares `id` with a `uuidv7()` column default. Installs built by 0.1.x **must run the new upgrade migration** (`2026_09_15_000000_add_default_uuidv7_to_vendors_id`), which sets that default on the existing table; without it the first insert after upgrading fails with a not-null violation. Existing rows keep their UUID4 keys
- **BREAKING: requires `robinsonryan/taxon` `^0.6.0`**, consumed by tag from GitHub rather than a local path repository. Taxon 0.6 ships `uuid7` as its default `id_type`, which is what this package's uuid-keyed `taggables.taggable_id` needs
- The test suite runs against real PostgreSQL 18 (DDEV `db`, database `testing`) instead of SQLite in-memory, because the schema now depends on a database-side function SQLite cannot express

## [0.1.1] - 2026-08-08

Previously untracked. See git history.
