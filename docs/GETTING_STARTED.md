# MuchoCore Getting Started

This is the shortest path from a fresh VPS to a working Geometry Dash Private Server.

## 1. Point your domain

Create a DNS record pointing your GDPS domain to the VPS:

\`\`\`text
gdps.example.com → YOUR-VPS-IP
\`\`\`

Wait until the DNS record resolves.

## 2. Install MuchoCore

\`\`\`bash
git clone https://github.com/IZKGMD/GMDmucho-core.git
cd GMDmucho-core
sudo ./install
\`\`\`

The installer installs and configures Docker-based PHP, MariaDB and Caddy. It also creates the database, Cloud Save secret, administrator account and compatibility profile.

For automated profile selection:

\`\`\`bash
export MUCHO_GD_VERSIONS=22
sudo -E bash install.sh
\`\`\`

Use \`all\` for all supported generations.

## 3. Check health

Open:

\`\`\`text
https://YOUR-DOMAIN/health
\`\`\`

Expected:

\`\`\`text
1
\`\`\`

Open the Admin Panel:

\`\`\`text
https://YOUR-DOMAIN/admin/
\`\`\`

The default administrator username is:

\`\`\`text
admin
\`\`\`

The password is created during installation.

## 4. Patch the client

Open the Admin Panel and use:

\`\`\`text
/admin/?page=clientpatcher
\`\`\`

Or use the local Windows patcher documented in [Client Setup](CLIENT_SETUP.md).

The Android patcher produces an unsigned APK. Sign it with your own Android signing key before distribution.

## 5. Migrate an existing GDPS

Already running a Cvolton/GMDprivateServer-style GDPS?

Start with the [Migration Kit](MIGRATION_KIT.md).

The safe flow is:

\`\`\`text
Existing GDPS
    ↓
Dry-run / preflight
    ↓
Review source counts
    ↓
Verified target backup
    ↓
Transactional import
    ↓
Healthcheck
    ↓
Test the migrated client
\`\`\`

Dry-run:

\`\`\`bash
sudo ./tools/migration/mucho-migrate.sh \\
  --source-host=SOURCE_DB_HOST \\
  --source-db=SOURCE_DB_NAME \\
  --source-user=SOURCE_DB_USER
\`\`\`

Apply:

\`\`\`bash
sudo ./tools/migration/mucho-migrate.sh \\
  --source-host=SOURCE_DB_HOST \\
  --source-db=SOURCE_DB_NAME \\
  --source-user=SOURCE_DB_USER \\
  --apply --confirm=COVOLTON
\`\`\`

Never paste a production database password into the command line. Set \`CVOLTON_SOURCE_PASS\` or use the secure prompt.

## 6. Update

\`\`\`bash
sudo /opt/mucho-core/update.sh
\`\`\`

Production updates follow published stable GitHub Releases.

## 7. Backup

Before major maintenance:

\`\`\`bash
sudo /opt/mucho-core/bin/mucho-db-backup.sh
\`\`\`

## 8. Where to go next

- [Full VPS setup](SETUP.md)
- [Client setup](CLIENT_SETUP.md)
- [Cvolton migration details](CVOLTON_MIGRATION.md)
- [Migration Kit](MIGRATION_KIT.md)
- [Version profiles](VERSIONS.md)
- [Custom plugins](CUSTOM_PLUGINS.md)
