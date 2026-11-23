# API Specific CI/CD Approach
This API complies with the `common-ci` approach.

The workflows pass the organization secrets to `common-ci` with `secrets: inherit`, and `common-ci` puts each key, from an organization secret or an organization variable, into the environment under its `_51DEGREES_` name before these scripts run. No script takes a key as a parameter.

The following secrets are required:
* `ACCESS_TOKEN` - GitHub [access token](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens#about-personal-access-tokens) for cloning repos, creating PRs, etc.
    * Example: `github_pat_l0ng_r4nd0m_s7r1ng`

The following organization variable is required:
* `_51DEGREES_RESOURCE_KEY_BESPOKE` - [resource key](https://51degrees.com/documentation/_services__cloud__resource_keys.html?utm_source=github&utm_medium=readme&utm_campaign=51degrees-php&utm_content=ci-readme.md&utm_term=api-specific-ci-cd-approach) for integration tests, carrying every property the tests ask for
    * Example: `R4nd0m-S7r1ng`
    * Without it, the integration tests are skipped, with a warning naming it.
