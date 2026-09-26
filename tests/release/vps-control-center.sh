#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

assert_contains() {
    local file="$1"
    local needle="$2"
    grep -Fq "$needle" "$file" || {
        echo "FAIL: $file does not contain: $needle" >&2
        exit 1
    }
    echo "PASS: $file contains: $needle"
}

bash -n install.sh
bash -n bin/mucho
bash -n bin/muchodb-password
bash -n bin/mucho-db-backup.sh

assert_contains install.sh 'MuchoCore setup wizard'
assert_contains install.sh 'MUCHO_ENABLE_YOUTUBE_IMPORT'
assert_contains install.sh 'MUCHO_MUSIC_MODERATION_REQUIRED'
assert_contains install.sh 'install -m 755 "$INSTALL_DIR/bin/mucho" /usr/local/bin/mucho'
assert_contains install.sh 'install -m 755 "$INSTALL_DIR/bin/muchodb-password" /usr/local/bin/muchodb-password'

assert_contains bin/mucho 'MUCHOCORE CONTROL CENTER'
assert_contains bin/mucho 'Change database password'
assert_contains bin/mucho 'Change admin password'
assert_contains bin/mucho 'Mucho Doctor'
assert_contains bin/mucho 'Database backup'
assert_contains bin/mucho 'Database & migrations'
assert_contains bin/mucho 'Apply pending migrations'
assert_contains bin/mucho 'Toggle YouTube import'
assert_contains bin/mucho 'Toggle music moderation'

assert_contains bin/muchodb-password 'ALTER USER'
assert_contains bin/muchodb-password 'SECRETS/db_password'

assert_contains bin/mucho-db-backup.sh 'docker compose'
assert_contains bin/mucho-db-backup.sh 'mariadb-dump'
assert_contains bin/mucho-db-backup.sh 'ROOT="$(cd "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")/.." && pwd)"'
! grep -Fq 'ROOT="/var/www/mucho-core"' bin/mucho-db-backup.sh

echo MUCHOCORE_VPS_CONTROL_CENTER_CONTRACT_OK
