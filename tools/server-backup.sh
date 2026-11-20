#!/usr/bin/env bash
#
# Full backup of all sites, databases and server configuration. READ-ONLY for the live system:
# it stops nothing, changes nothing, only creates files in the backup folder.
#
#   sudo bash server-backup.sh                 # backup into /root/backup-DATE
#   sudo bash server-backup.sh /mnt/disk/bk    # backup into another folder
#
# Optional environment variables:
#   WWW_ROOT=/var/www        where the sites live (FastPanel: /var/www/USER/data/www/DOMAIN)
#   MYSQL_OPTS="..."         extra options for mysql/mysqldump, e.g. "-uroot -pSECRET" or "--defaults-file=/root/.my.cnf"
#   FORCE=1                  skip the free-space check
#
set -u
umask 077

WWW_ROOT="${WWW_ROOT:-/var/www}"
OUT="${1:-/root/backup-$(date +%Y%m%d-%H%M)}"
MYSQL_OPTS="${MYSQL_OPTS:-}"
LOG="$OUT/backup.log"
ERRORS=0

mkdir -p "$OUT"/{sites,databases,config}
exec > >(tee -a "$LOG") 2>&1

say()  { printf '\n== %s\n' "$*"; }
fail() { printf 'ERROR: %s\n' "$*" >&2; ERRORS=$((ERRORS + 1)); }
low()  { nice -n 19 ionice -c3 "$@" 2>/dev/null || nice -n 19 "$@"; }   # be gentle to the live sites

[ "$(id -u)" -eq 0 ] || echo "WARNING: not running as root, some files may be unreadable."

# ---------------------------------------------------------------- free space check
need_kb=$(du -sk "$WWW_ROOT" 2>/dev/null | cut -f1); need_kb=${need_kb:-0}
free_kb=$(df -Pk "$OUT" | awk 'NR==2 {print $4}')
echo "Sites: $((need_kb / 1024)) MB   Free space in $OUT: $((free_kb / 1024)) MB"
if [ "${FORCE:-0}" != 1 ] && [ "$free_kb" -lt $((need_kb + need_kb / 5)) ]; then
    echo "Not enough free space (need roughly sites size + 20%). Use another disk/folder, or FORCE=1." >&2
    exit 1
fi

# ---------------------------------------------------------------- 1. every site as its own archive
say "Sites"
: > "$OUT/sites/INVENTORY.txt"
shopt -s nullglob
for dir in "$WWW_ROOT"/*/data/www/*/ "$WWW_ROOT"/*/ ; do
    dir="${dir%/}"
    case "$dir" in
        */data/www/*) name="$(basename "$(dirname "$(dirname "$(dirname "$dir")")")")__$(basename "$dir")" ;;
        */data|*/data/www|*/httpd-cert|*/html) continue ;;
        *) [ -d "$dir/data/www" ] && continue; name="$(basename "$dir")" ;;
    esac
    archive="$OUT/sites/${name}.tar.gz"
    printf '%s\t%s\n' "$name" "$dir" >> "$OUT/sites/INVENTORY.txt"
    echo "-> $name"
    if low tar -czf "$archive" -C "$(dirname "$dir")" "$(basename "$dir")" 2>>"$LOG.tar"; then
        gzip -t "$archive" || fail "corrupt archive $archive"
    else
        # tar exit 1 = "file changed while reading": archive is still usable for live sites
        [ -s "$archive" ] && gzip -t "$archive" 2>/dev/null && echo "   (files changed during backup - normal on a live site)" || fail "tar failed for $dir"
    fi
done
# certificates and anything else under the web root that is not a site (e.g. FastPanel's httpd-cert)
for extra in "$WWW_ROOT"/httpd-cert "$WWW_ROOT"/.well-known; do
    [ -e "$extra" ] && low tar -czf "$OUT/sites/_$(basename "$extra").tar.gz" -C "$WWW_ROOT" "$(basename "$extra")" 2>/dev/null
done

# ---------------------------------------------------------------- 2. databases
say "Databases"
MYSQL_BIN="$(command -v mysql || command -v mariadb || true)"
DUMP_BIN="$(command -v mysqldump || command -v mariadb-dump || true)"
if [ -n "$MYSQL_BIN" ] && [ -n "$DUMP_BIN" ]; then
    # shellcheck disable=SC2086
    if dbs=$("$MYSQL_BIN" $MYSQL_OPTS -N -e 'SHOW DATABASES' 2>"$OUT/databases/mysql-error.txt"); then
        for db in $dbs; do
            case "$db" in information_schema|performance_schema|sys) continue ;; esac
            echo "-> $db"
            # shellcheck disable=SC2086
            if low "$DUMP_BIN" $MYSQL_OPTS --single-transaction --quick --routines --triggers --events --hex-blob "$db" 2>>"$OUT/databases/mysql-error.txt" | gzip > "$OUT/databases/${db}.sql.gz" \
               && [ "${PIPESTATUS[0]}" -eq 0 ] && gzip -t "$OUT/databases/${db}.sql.gz"; then :; else fail "dump failed for database $db (see databases/mysql-error.txt)"; fi
        done
        # users and their privileges, so databases can be reconnected to their users
        # shellcheck disable=SC2086
        "$MYSQL_BIN" $MYSQL_OPTS -N -e "SELECT CONCAT('SHOW GRANTS FOR ''',user,'''@''',host,''';') FROM mysql.user WHERE user NOT IN ('mariadb.sys','mysql.sys','mysql.session','mysql.infoschema','debian-sys-maint','PUBLIC','')" 2>/dev/null \
            | "$MYSQL_BIN" $MYSQL_OPTS -N 2>/dev/null | sed 's/$/;/' > "$OUT/databases/_grants.sql" || fail "could not export database users"
        chmod 600 "$OUT/databases/_grants.sql"
    else
        fail "cannot connect to MySQL: $(head -1 "$OUT/databases/mysql-error.txt"). Set MYSQL_OPTS, e.g. MYSQL_OPTS='-uroot -pPASSWORD'"
    fi
else
    fail "mysql/mariadb client not found - this server has no database, or databases are NOT backed up"
fi

# ---------------------------------------------------------------- 3. configuration, cron, certificates, mail
say "Server configuration"
for path in /etc/nginx /etc/apache2 /etc/httpd /etc/php /etc/mysql /etc/my.cnf /etc/letsencrypt /etc/cron.d /etc/cron.daily \
            /etc/crontab /var/spool/cron /etc/fastpanel2 /etc/fastpanel /etc/proftpd /etc/exim4 /etc/postfix /etc/dovecot \
            /etc/fstab /etc/hosts /etc/ssh/sshd_config /etc/passwd /etc/group /etc/os-release /var/mail /var/vmail; do
    [ -e "$path" ] || continue
    low tar -czf "$OUT/config/$(echo "$path" | tr '/' '_' | sed 's/^_//').tar.gz" "$path" 2>/dev/null || echo "   (skipped unreadable parts of $path)"
done
# cron jobs of every user, readable
for user in $(cut -d: -f1 /etc/passwd); do crontab -l -u "$user" 2>/dev/null | sed "s/^/[$user] /"; done > "$OUT/config/all-user-crontabs.txt"
{ echo "# $(date)"; uname -a; cat /etc/os-release 2>/dev/null; echo; df -h; echo; dpkg -l 2>/dev/null | grep -Ei 'php|nginx|apache|mysql|maria|fastpanel' ; } > "$OUT/config/system-info.txt"

# ---------------------------------------------------------------- checksums + summary
say "Checksums"
( cd "$OUT" && find . -type f ! -name 'SHA256SUMS' ! -name 'backup.log*' -print0 | xargs -0 sha256sum > SHA256SUMS )
rm -f "$LOG.tar"

say "Summary"
echo "Folder: $OUT   ($(du -sh "$OUT" | cut -f1))"
echo "Sites archived: $(grep -c . "$OUT/sites/INVENTORY.txt")    Databases: $(find "$OUT/databases" -name '*.sql.gz' | wc -l)"
if [ "$ERRORS" -eq 0 ]; then echo "RESULT: OK, no errors."; else echo "RESULT: $ERRORS problem(s) - read the ERROR lines above before relying on this backup."; fi
echo "NEXT: copy the folder OFF this server (scp/rsync) and verify:  cd FOLDER && sha256sum -c SHA256SUMS"
exit $((ERRORS > 0))
