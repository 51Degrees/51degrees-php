#!/usr/bin/env bash
#
# Local build-and-test helper using Docker.
#
# On Windows, run it from a WSL shell (the project asks for WSL because the
# extension is a Linux/macOS build). It compiles the FiftyOneDegreesHashEngine
# extension in a clean PHP image and runs the fork-safety reproduction for
# issue #38 against the free Lite data file, so no licensed data file or host
# toolchain is required.
#
# Usage:
#   dev/docker-build-test.sh [php-version]
# Example:
#   dev/docker-build-test.sh 8.3
#
set -euo pipefail

PHP_VERSION="${1:-8.3}"
PACKAGE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# The C sources and the Lite data file are in the cxx submodule at the root
# of the repository, two levels above this package.
REPO_ROOT="$(cd "${PACKAGE_ROOT}/../.." && pwd)"
PACKAGE="${PACKAGE_ROOT#"${REPO_ROOT}"/}"
IMAGE="php:${PHP_VERSION}-cli"
DATA_REL="cxx/data/device-detection/51Degrees-LiteV4.1.hash"
CSV_REL="cxx/data/device-detection/20000 User Agents.csv"

if [ ! -f "${REPO_ROOT}/${DATA_REL}" ]; then
    echo "Lite data file not found at ${DATA_REL}." >&2
    echo "Run these from the root of the repository to get it." >&2
    echo "  git submodule update --init --recursive" >&2
    echo "  sh cxx/data/get-data-files.sh 51Degrees-LiteV4.1.hash" >&2
    exit 1
fi

echo "Building and testing with ${IMAGE} ..."

docker run --rm \
    -v "${REPO_ROOT}:/repo:ro" \
    -e DATA="/repo/${DATA_REL}" \
    -e CSV="/repo/${CSV_REL}" \
    -e PACKAGE="${PACKAGE}" \
    "${IMAGE}" bash -euo pipefail -c '
        echo "==> Installing build tools"
        apt-get update -qq
        DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
            g++ make autoconf swig file >/dev/null
        docker-php-ext-install pcntl >/dev/null

        echo "==> Building the extension"
        cp -a "/repo/${PACKAGE}" /build
        rm -rf /build/on-premise/cxx && mkdir -p /build/on-premise/cxx
        cp -a /repo/cxx/src /build/on-premise/cxx/src
        cd /build/on-premise
        phpize >/dev/null
        ./configure >/dev/null
        make -j"$(nproc)" >/dev/null
        echo "Built: $(ls /build/on-premise/modules/*.so)"

        echo "==> Running the fork-safety reproduction (issue #38)"
        cat > /tmp/ff.ini <<INI
FiftyOneDegreesHashEngine.data_file=${DATA}
extension=/build/on-premise/modules/FiftyOneDegreesHashEngine.so
INI
        _51DEGREES_DD_PATH="${DATA}" \
        _51DEGREES_UA_CSV="${CSV}" \
            php -c /tmp/ff.ini /build/configuration-tests/testForkSafety.php
    '
