#!/bin/sh
#
# Generates the IP Intelligence PHP wrapper (src/php8/ipi_wrap.cxx and
# src/php8/php_FiftyOneDegreesIpIntelligenceEngine.h) from ipi-php.i with
# SWIG, the same version that generates the Device Detection wrapper. Both
# extensions join one SWIG type table when they are loaded together, so they
# must come from the same SWIG.
#
#   generate-wrapper.sh           rewrite the committed wrapper
#   generate-wrapper.sh --check   fail if the committed wrapper differs
#
# The C++ sources come from the cxx submodule at the root of the repository.
# The wrapper is never edited by hand, so a change to ipi-php.i or to cxx is
# followed by running this script and committing the result.

set -eu

SWIG_VERSION=4.5.1
MODULE=FiftyOneDegreesIpIntelligenceEngine

here=$(cd "$(dirname "$0")" && pwd)
root=$(cd "$here/../.." && pwd)
mode=write
if [ "${1:-}" = "--check" ]; then
    mode=check
elif [ $# -gt 0 ]; then
    echo "usage: $0 [--check]" >&2
    exit 2
fi

found=$(swig -version 2>/dev/null | sed -n 's/^SWIG Version //p')
if [ "$found" != "$SWIG_VERSION" ]; then
    echo "SWIG $SWIG_VERSION is required, found '${found:-none}'." >&2
    exit 1
fi
if [ ! -d "$root/cxx/src/ipi" ]; then
    echo "The cxx submodule is missing, run: git submodule update --init cxx" >&2
    exit 1
fi

# Every class name starts with the prefix ipi-php.i defines, which is also
# where the earlier names are kept.
prefix=$(sed -n 's/^ *#define IPI_CLASS_PREFIX "\([A-Za-z]*\)"$/\1/p' \
    "$here/ipi-php.i")
if [ -z "$prefix" ]; then
    echo "ipi-php.i does not define IPI_CLASS_PREFIX." >&2
    exit 1
fi

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

# ipi-php.i includes cxx/src/... relative to the folder SWIG runs in.
mkdir -p "$work/cxx" "$work/raw"
cp -R "$root/cxx/src" "$work/cxx/src"
cp "$here/ipi-php.i" "$work/"
(
    cd "$work"
    swig -c++ -php -module "$MODULE" -prefix "$prefix" -outdir raw \
        -o raw/ipi_wrap.cxx ipi-php.i
)

# SWIG writes the path of its install folder into comments. Keep only the part
# that is the same on every machine, so the output does not depend on where
# SWIG is installed.
mkdir -p "$work/out"
sed -E 's#/\*@SWIG:[^,]*/share/swig/#/*@SWIG:share/swig/#' \
    "$work/raw/ipi_wrap.cxx" > "$work/out/ipi_wrap.cxx"
cp "$work/raw/php_$MODULE.h" "$work/out/"

status=0
for name in ipi_wrap.cxx "php_$MODULE.h"; do
    if [ "$mode" = write ]; then
        cp "$work/out/$name" "$here/src/php8/$name"
    elif ! diff -u "$here/src/php8/$name" "$work/out/$name" > "$work/diff"
    then
        echo "src/php8/$name differs from what ipi-php.i generates:" >&2
        head -n 40 "$work/diff" >&2
        status=1
    fi
done

if [ "$mode" = check ] && [ "$status" -ne 0 ]; then
    echo "Run on-premise/generate-wrapper.sh and commit the result." >&2
fi
exit "$status"
