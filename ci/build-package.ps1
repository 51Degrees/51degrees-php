# Called unconditionally by nightly-publish.build.ps1.
#
# A Composer library needs no build artefact - the repository is the package -
# but the publish workflow still uploads a "package" directory as an artifact,
# so the shared step writes a placeholder into it. Mirrors
# device-detection-php-onpremise.

./php/build-package-composer.ps1
