## About Phalcon
Follow this [link](https://phalconphp.com/de/) for more information about Phalcon.

## Installing Phalcon
Presequisites are:
- PHP installed (e.g. with Homebrew)
- Phalcon PHP extension (e.g. with Homebrew)
- Cloud Foundry CLI installed ([CLI doc](https://docs.developer.swisscom.com/cf-cli/))

As this branch contains the minimal setup to run a micro service based Phalcon app, there is noting more to do.

You can run your application locally with this command:
```bash
php -S 127.0.0.1:8000 -t ./ .htrouter.php
```

## Manifest - Make it ready for Cloud Foundry
Next we setup the ```manifest.yaml``` file, which will describe our app. You can read more about manifests [here](https://docs.developer.swisscom.com/devguide/deploy-apps/manifest.html).

Create a file named ```manifest.yaml``` in the root of you project with these content:
```yaml
---
applications:
- name: <application-name>
  memory: 1G
  instances: 1
  buildpack: https://github.com/cloudfoundry/php-buildpack.git

  services:
  - <service-name>
```
Be carefully the application name may already exsist or at least the route.

## PHP configuration
The Buildpack allows use to configure the PHP environment and we should definitly do this as we need the Phalcon extension. Create the file
```.bp-config/options.json``` in the root of your project and enter this json:
```json
{
	"WEBDIR": "",
  "PHP_VERSION": "{PHP_70_LATEST}",
  "PHP_EXTENSIONS": ["phalcon", "pdo", "pdo_mysql"]
}
```
You can find more options about the PHP Build pack [here](https://docs.developer.swisscom.com/buildpacks/php/index.html).