# API Specific CI/CD Approach
This API complies with the `common-ci` approach.

The workflows pass the organization secrets to `common-ci` with `secrets: inherit`, and `common-ci` puts each key into the environment under its `_51DEGREES_` name before these scripts run. No script takes a key as a parameter.

The following secrets are required:
* `_51DEGREES_LICENSE_KEY` - [license key](https://51degrees.com/pricing?utm_source=github&utm_medium=readme&utm_campaign=51degrees-php&utm_content=ci-readme.md&utm_term=api-specific-ci-cd-approach) for downloading the TAC hashes file from the Distributor
    * Example: `V3RYL0NGR4ND0M57R1NG`
    * Without it, the TAC hashes file is not fetched and only the unit tests that need no TAC data file run.
