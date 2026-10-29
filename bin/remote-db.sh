#!/usr/bin/env bash
# Runs on the server: the Makefile feeds it through
#   ssh kb bash -s -- export|import <site dir> <dump file>
# with both paths relative to $HOME.
#
# Hostinger's PHP disables exec(), so `wp db export` and `wp db import` cannot
# start mysqldump. This calls the MySQL tools directly, with the credentials
# read from the site's own config through `wp eval`.
set -euo pipefail

cmd=$1 site=$2 file=$HOME/$3

# `wp` on PHP 8.3 is a function in ~/.bashrc, which `bash -s` does not read.
source ~/.bashrc

cd "$HOME/$site"

cnf=$(mktemp)
trap 'rm -f "$cnf"' EXIT
wp eval '
  [$host, $port] = array_pad(explode(":", DB_HOST, 2), 2, "");
  $quote = fn ($v) => "\"" . addcslashes($v, "\\\"") . "\"";
  echo "[client]\nuser=", $quote(DB_USER), "\npassword=", $quote(DB_PASSWORD), "\nhost=", $quote($host), "\n";
  if ($port !== "") echo "port=$port\n";
' > "$cnf"
db=$(wp eval 'echo DB_NAME;')

case $cmd in
  export)
    mkdir -p "$(dirname "$file")"
    # Only the site's own tables: the prod database holds other tables too.
    wp db tables --all-tables-with-prefix --format=csv | tr , '\n' \
      | xargs mysqldump --defaults-extra-file="$cnf" --single-transaction --quick --no-tablespaces "$db" \
      | gzip > "$file"
    ;;
  import)
    gunzip -c "$file" | mysql --defaults-extra-file="$cnf" "$db"
    ;;
  *)
    echo "usage: $0 export|import <site dir> <dump file>" >&2
    exit 1
    ;;
esac
