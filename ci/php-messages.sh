# Gives a PHP that a CI step starts outside PHPUnit the settings that
# tests/bootstrap.php gives each PHP the tests start, as ci/php-messages.ps1
# does for PowerShell. Source this file, call php_messages_start before the
# PHP runs and php_messages_check after. A PHP started with -n reads no ini
# file, so it is given the settings with php_messages_options.

php_messages_start() {
    PHP_MESSAGES=$(mktemp -d)
    printf 'error_reporting = -1\nlog_errors = On\nerror_log = "%s"\n' \
        "$PHP_MESSAGES/php.log" > "$PHP_MESSAGES/php-messages.ini"
    PHP_MESSAGES_SCAN=${PHP_INI_SCAN_DIR-}
    # An empty entry makes PHP scan its own folder too, which it does when
    # PHP_INI_SCAN_DIR is not set.
    export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR-}:$PHP_MESSAGES"
}

php_messages_options() {
    printf '%s\n' -d error_reporting=-1 -d log_errors=On \
        -d "error_log=$PHP_MESSAGES/php.log"
}

# Prints what PHP logged, puts PHP_INI_SCAN_DIR back, and returns 1 when the
# log holds a warning, notice, deprecation or fatal error.
php_messages_check() {
    local log="$PHP_MESSAGES/php.log" status=0
    if [ -s "$log" ]; then
        echo 'PHP logged:'
        cat "$log"
        if grep -Eq '^\[[^]]*\] PHP (Warning|Notice|Deprecated|Strict Standards|Fatal error|Parse error|Recoverable fatal error):' "$log"; then
            echo 'PHP logged a warning, notice, deprecation or fatal error, which is an error.' >&2
            status=1
        fi
    fi
    rm -rf "$PHP_MESSAGES"
    if [ -n "$PHP_MESSAGES_SCAN" ]; then
        export PHP_INI_SCAN_DIR="$PHP_MESSAGES_SCAN"
    else
        unset PHP_INI_SCAN_DIR
    fi
    return "$status"
}
