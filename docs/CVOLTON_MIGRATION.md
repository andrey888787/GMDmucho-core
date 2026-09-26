# Cvolton Migration

MuchoCore includes a destination-side migration wizard for Cvolton/GMDprivateServer.

## Supported source data

The current database-to-database importer transfers:

- accounts → MuchoCore accounts
- users → MuchoCore profiles
- levels → MuchoCore levels
- levelscores → regular level scores
- platscores → platformer scores

Other Cvolton tables are intentionally left untouched until a dedicated field mapping is added.

## Safety

The source database connection is opened read-only at the MariaDB session level. The importer uses SELECT queries only; it does not execute the Cvolton PHP application or source-side shell commands.

Credential handling is conservative:

- recognized PHP password hashes are copied as hashes;
- a Cvolton 40-character GJP2 credential is re-hashed before storage;
- plaintext or unknown password values are never stored;
- such accounts receive a random unusable password hash and are recorded as needing account recovery;
- Cvolton administrator flags are never promoted to MuchoCore admin or owner privileges.

Source account IDs and level IDs are kept in persistent mapping tables so repeated imports remain deterministic.

## Recommended operator workflow

Use the **MuchoCore Migration Kit** instead of invoking the low-level importer directly:

    sudo ./tools/migration/mucho-migrate.sh       --source-host=SOURCE_DB_HOST       --source-db=SOURCE_DB_NAME       --source-user=SOURCE_DB_USER

The default run is a read-only preflight. It checks the running MuchoCore target, verifies the migration mapping tables and reports source row counts.

## Apply

After reviewing the preflight output, run:

    sudo ./tools/migration/mucho-migrate.sh       --source-host=SOURCE_DB_HOST       --source-db=SOURCE_DB_NAME       --source-user=SOURCE_DB_USER       --apply --confirm=COVOLTON

The kit creates a verified target database backup before importing. The source is opened read-only, the destination import runs inside one transaction, and a post-migration healthcheck is performed.

## Passwords

Avoid putting the source password in shell history:

    export CVOLTON_SOURCE_PASS='your-source-db-password'
    sudo -E ./tools/migration/mucho-migrate.sh       --source-host=SOURCE_DB_HOST       --source-db=SOURCE_DB_NAME       --source-user=SOURCE_DB_USER

Or let the kit prompt for the password without echoing it.

Unset the environment variable after use:

    unset CVOLTON_SOURCE_PASS

## Verification

Migration reports are stored under:

    /opt/mucho-core/backups/migrations/

Keep the verified backup and report until the production cutover is validated.

Then test representative migrated accounts, levels and scores in the matching Geometry Dash client.

See **[MIGRATION_KIT.md](MIGRATION_KIT.md)** for the full operator workflow and current migration scope.

## Current limitations

Comments, comment likes, private messages, friendships, gauntlets, map packs, lists, rewards and source-specific moderation data are not migrated yet. This is intentional so no ambiguous source field is silently mapped to the wrong target field.
