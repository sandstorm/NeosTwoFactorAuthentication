#!/bin/bash
set -eou pipefail

# ping db until it is ready
echo "Waiting for database..."
until mariadb -h"${DB_NEOS_HOST}" -P"${DB_NEOS_PORT}" -u"${DB_NEOS_USER}" -p"${DB_NEOS_PASSWORD}" -D"${DB_NEOS_DATABASE}" --disable-ssl --silent -e "SELECT 1;" 2>/dev/null; do
    sleep 2
done

./flow flow:cache:flush

# this does the warmup as well
./flow doctrine:migrate

yes y | ./flow resource:clean || true

./flow cr:setup
./flow cr:status

./flow site:importall --package-key Neos.Demo

./flow resource:publish --collection static

# 2. We now can start caretakerd, which will run the remaining steps in parallel and restart the container if they fail.
/usr/bin/caretakerd run
