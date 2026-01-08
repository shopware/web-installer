# Web Installer

The web installer is a simple Symfony application packaged as a Phar file, that allows running automated Composer commands to install or update Shopware.
The term installer means 

## Create a Phar file

To create a Phar file, first install the dependencies with Composer:

    composer install

Then run the following command:

    composer run build-phar

## Running Unit Tests

To run the unit tests, use the following command:

    composer run test

## Running the Web Installer

Copy the created `shopware-installer.phar.php` file to the root directory of your Shopware installation or into an empty directory.

Request that page in your browser with /shopware-installer.phar.php and the Installer will decide if you need to install or update Shopware.

## Running the Web Installer for development

Change the files of this repository as needed, then compile and copy afterwards:
`composer run build-phar && cp -f shopware-installer.phar.php your/directory/`

## Running update against an unreleased Shopware version

To run an update against an unreleased Shopware version,
copy the `shopware-installer.phar.php` file to the root directory of your Shopware installation.

Clone Shopware into `platform` directory and checkout the branch you want to test.

Then edit the `composer.json` of the Shopware installation and add the following line:

```diff
"repositories": [
    {
        "type": "path",
        "url": "custom/plugins/*",
        "options": {
            "symlink": true
        }
    },
    {
        "type": "path",
        "url": "custom/plugins/*/packages/*",
        "options": {
            "symlink": true
        }
    },
    {
        "type": "path",
        "url": "custom/static-plugins/*",
        "options": {
            "symlink": true
        }
-   }
+   },
+   {
+       "type": "path",
+       "url": "platform/src/*",
+       "options": {
+           "symlink": true
+       }
+   }
],
```

and 
create a `.env.installer` file with the following content:

```
SW_RECOVERY_NEXT_VERSION=6.5.1.0
SW_RECOVERY_NEXT_BRANCH=trunk
```

Replace the version and branch with the version and branch you want to test. 
If in the `composer.json` of the branch is a version set (like in release branches), 
you have to use that version for the next version variable.

Then run the updater regularly with `php shopware-installer.phar.php`,
it will use the forced version and don't try to determine a version anymore.

## Releasing

To create a new release, push a tag to the repository:

```bash
git tag 1.0.0
git push origin 1.0.0
```

The GitHub Actions workflow will automatically build the PHAR file, generate a build provenance attestation, and upload it to the release.

You can verify the attestation of a downloaded release using the GitHub CLI:

```bash
gh attestation verify shopware-installer.phar.php --repo shopware/web-installer
```

### Configurable Installer Timeout

The installer timeout can be configured using the `SHOPWARE_INSTALLER_TIMEOUT` environment variable (in seconds). 
Default is 900 seconds (15 minutes). Invalid values fall back to default.

Example:
```bash
export SHOPWARE_INSTALLER_TIMEOUT=1200  # 20 minutes
```

