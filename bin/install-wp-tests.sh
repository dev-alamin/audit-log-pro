#!/usr/bin/env bash
set -e

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-127.0.0.1}
WP_VERSION=${5-latest}

WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress}
WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}

download() {
    curl -s "$1" > "$2"
}

# Download WP core
if [ ! -d "$WP_CORE_DIR" ]; then
    mkdir -p "$WP_CORE_DIR"
    download "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" /tmp/wordpress.tar.gz
    tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
fi

# Download test suite (the actual includes/ used by wp-phpunit) if not already present
if [ ! -d "$WP_TESTS_DIR" ]; then
    mkdir -p "$WP_TESTS_DIR"
    svn export --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/includes/ "$WP_TESTS_DIR/includes"
    svn export --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/data/ "$WP_TESTS_DIR/data"
fi

# Generate wp-tests-config.php
if [ ! -f wp-tests-config.php ]; then
    download https://develop.svn.wordpress.org/trunk/wp-tests-config-sample.php wp-tests-config.php
    sed -i "s/youremptytestdbnamehere/${DB_NAME}/" wp-tests-config.php
    sed -i "s/yourusernamehere/${DB_USER}/" wp-tests-config.php
    sed -i "s/yourpasswordhere/${DB_PASS}/" wp-tests-config.php
    sed -i "s|localhost|${DB_HOST}|" wp-tests-config.php
    sed -i "s|dirname( __FILE__ ) . '/src/'|'${WP_CORE_DIR}/'|" wp-tests-config.php
fi