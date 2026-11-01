# Changelog

All notable changes to `robinsonryan/vendor` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
On `0.x`, every minor release may break.

## [Unreleased]

## [0.4.2] - 2026-11-01

### Fixed
- A form can clear a vendor's contact. New `VendorContact::fromGroups()` takes a form's contact and address groups and works out what to clear, carried in the new `$cleared` list: a group not sent changes nothing, a field sent empty clears, and an address sent replaces the address as a whole, so a field it leaves out clears too. Moving the street no longer keeps the old suite and postal code
- `SyncVendorContacts` retires a cleared contact point the way it retires a changed value, keeping its history, and retires an address cleared entirely. A document's point a person had promoted goes back to being non-primary and stays; what documents added is otherwise untouched. A cleared country falls back to the required `country_code` default `US` and loses its wording

### Unchanged
- A `VendorContact` without `$cleared` behaves as before: an empty value leaves its field alone, which is what `copy_vendor_contacts_to_parties` and partial writes rely on

## [0.4.1] - 2026-11-01

The release 0.4.0 was meant to be. Take this one, not 0.4.0.

### Removed
- **BREAKING, DESTRUCTIVE: the contact columns are gone from `vendors`.** New migration `drop_vendor_contact_columns`, published with the rest, drops `contact_name`, `contact_email`, `contact_phone`, `address_line_1`, `address_line_2`, `city`, `state`, `postal_code` and `country`, destroying their contents. It falls under the destructive-migration rule: a verified backup, every vendor confirmed to have its party, and the owner's explicit yes. `down()` puts the columns back empty and cannot restore their contents
- **BREAKING:** `Vendor` no longer has those attributes, nor `Vendor::CONTACT_COLUMNS`, and a saved vendor no longer syncs anything: it only gets its party. `VendorData` loses its nine contact fields. The package factory no longer makes up a contact; `withContact()` does

### Changed
- **BREAKING:** `SyncVendorContacts::execute()` takes a new `RobinsonRyan\Vendor\Data\VendorContact` as its second argument, and `VendorData` carries one as `contact` for `CreateVendor` and `UpdateVendor` to pass on. An empty value still leaves the party alone
- `copy_vendor_contacts_to_parties` reads the columns off the row itself and hands them to the sync, so a database that has not run it yet still gets its data copied before the drop. **Upgrade note:** an application that published the migration must take this version of it, or it fails on the new signature. On 0.x this is why the release left the 0.3 line: `^0.3` will not pick it up
- `primaryEmail()`, `primaryPhone()` and `primaryAddress()` read the party's rows through the relations, so eager loading `party.contactPoints` and `party.addresses` serves a whole page. They keep what they answer, and read a loaded relation rather than asking again

### Added
- `Vendor::contact()` returns the contact a person set, the rows `SyncVendorContacts` owns, as a `VendorContact`. With `fromDocuments: true` it falls back to contacts read off documents, which are the purchasing record's, so showing them is the caller's choice
- `Vendor::primaryContactName()`

## [0.4.0] - 2026-11-01

Tagged by mistake: the merge failed and the release went out without it. This tag holds the same code as 0.3.1 and only a changelog entry describing a change it does not contain. Do not consume it.

## [0.3.1] - 2026-10-31

### Fixed
- Changing a mirrored contact value no longer hands the old value's verification, consent or delivery status to the new one. `SyncVendorContacts` retires the old contact point (soft-deleted, keeping its verification events and consents) and writes a new row; naming the old value again restores it. A mirrored address whose location changes goes back to `unverified` and loses its geocode
- `PostVendorDocument` takes a transaction-scoped PostgreSQL advisory lock on (source, source ref), so two simultaneous first posts of one document no longer fail on the unique index: the second waits, then compares versions
- A vendor whose only address field is `country` now gets a party address, so `copy_vendor_contacts_to_parties` and the sync no longer leave that value only in a column the held drop destroys. **Upgrade note:** the copy is safe to rerun, so a 0.3.0 install can run `SyncVendorContacts` to pick up such a vendor
- `VendorItem` stores the normalized part number on every model write, through a mutator rather than in `ResolveVendorItem` alone, so `pr60-asf` can no longer sit beside `PR60-ASF` for one vendor. The `desc:` prefix of a description key is kept. New `VendorPartNumber::key()`. The unique index is unchanged, so a query-builder write still has to pass a normalized key
- `RecordVendorContact` compares both street lines when it dedupes a remit-to address, so `123 Main St, Suite 4` read off a document matches a migrated address holding the suite on line two, and two suites at one street address stay apart

## [0.3.0] - 2026-10-31

### Added
- **Purchasing record.** New `vendor_documents`, `purchase_lines` and `vendor_items` tables with models, factories and enums under `RobinsonRyan\Vendor\Purchasing`. Keys are database-generated UUID v7; table names and models come from `config('vendor.tables.*')` and `config('vendor.models.*')`
- `PostVendorDocument`: the idempotent write of a vendor document, keyed on (source, source ref). A repeat or older version writes nothing; a newer version replaces the header and lines and never deletes a vendor item. Each touched vendor item's last price and last seen date are recomputed from its newest-dated line, so posting out of date order cannot regress them
- `ResolveVendor` (paperless correspondent id, then case-insensitive exact name, else create) and `ResolveVendorItem` (normalized part number, or a `desc:` key built from the description when none is printed)
- `RecordVendorContact`: adds a remit-to address, phone, AP email or sales rep read off a document to the vendor's party with its source document, without duplicates. AFWD's account number goes on `metadata.account_number`, is never overwritten, and a differing value lands in `metadata.account_number_seen`
- **Vendors are hey-you organizations.** `Vendor` uses hey-you's `Contactable`, so a new vendor gets a party, recorded in the new nullable `vendors.party_id`. `primaryEmail()`, `primaryPhone()` and `primaryAddress()` read from the party; `ensureParty()` and `isStockVendor()` are new
- Data migration `copy_vendor_contacts_to_parties`: gives every existing vendor a party and copies its contact columns into contact points and an address, marked `metadata.source = vendor_columns`. Additive and safe to rerun

### Changed
- Requires `robinsonryan/hey-you` `^0.2.2`, which in practice means Laravel 12 or 13. A consuming application's test case needs `HeyYouServiceProvider` registered, because creating a vendor now writes a party
- Saving a vendor mirrors its contact columns onto its party through `SyncVendorContacts`. The sync hangs off the model rather than `CreateVendor`/`UpdateVendor`, so a direct model write cannot drift either

### Held
- The contact columns stay on `vendors` in this release, because the shop app still reads them. The migration that drops them is prepared but held at `database/migrations-held/2026_12_01_000000_drop_vendor_contact_columns.php`: it is outside `database/migrations`, not published and not run, and its docblock names exactly the data it destroys. It ships in a later release, only on an explicit yes, under the destructive-migration rule, once the shop app has stopped reading those columns.

## [0.2.0] - 2026-09-15

### Changed
- **BREAKING: PostgreSQL 18+ generates every primary key; the package no longer mints them in PHP.** `Vendor` drops Laravel's `HasUuids` for a `HasUuidPrimaryKey` trait that only sets `$incrementing = true` / `$keyType = 'string'`, and the `vendors` migration declares `id` with a `uuidv7()` column default. Installs built by 0.1.x **must run the new upgrade migration** (`2026_09_15_000000_add_default_uuidv7_to_vendors_id`), which sets that default on the existing table; without it the first insert after upgrading fails with a not-null violation. Existing rows keep their UUID4 keys
- **BREAKING: requires `robinsonryan/taxon` `^0.6.0`**, consumed by tag from GitHub rather than a local path repository. Taxon 0.6 ships `uuid7` as its default `id_type`, which is what this package's uuid-keyed `taggables.taggable_id` needs
- The test suite runs against real PostgreSQL 18 (DDEV `db`, database `testing`) instead of SQLite in-memory, because the schema now depends on a database-side function SQLite cannot express

## [0.1.1] - 2026-08-08

Previously untracked. See git history.
