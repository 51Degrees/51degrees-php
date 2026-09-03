# Called by nightly-publish.package.ps1, on main / alpha / version-* branches.
#
# Packagist is fed by a GitHub webhook, so publishing a Composer package is just
# a matter of pushing the tag that nightly-publish.package.ps1 creates in the
# step after this one - there is nothing to upload here. Mirrors
# device-detection-php-onpremise.
#
# NOTE: this repository is not on Packagist yet, and cannot be until
# 51degrees/fiftyone.pipeline.translation is published - see issue #5. Until
# then a tag will be pushed but nothing will appear on Packagist.

Write-Output "Packagist will pick up the new version automatically, no need to upload anything"
