## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable, creative experience to be truly fulfilling. Laravel attempts to take the pain out of development by easing common tasks used in the majority of web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, yet powerful, providing tools needed for large, robust applications. A superb combination of simplicity, elegance, and innovation give you tools you need to build any application with which you are tasked.

## Installing Laravel
Presequisites are:
- PHP installed (e.g. with Homebrew)
- Composer installed (e.g. with Homebrew)
- Cloud Foundry CLI installed ([CLI doc](https://docs.developer.swisscom.com/cf-cli/))

Now to install Laravel open the ```terminal``` and go to a folder of your choice. Enter command below into the ```terminal``` and see the magic:
```bash
composer create-project --prefer-dist laravel/laravel <project-name>
```
This command as setup a full installation of Laravel.

You can run your application locally with this command:
```bash
php -S 127.0.0.1:8000 -t public
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
```
Be carefully the application name may already exsist or at least the route.

## PHP configuration
The Buildpack allows use to configure the PHP environment and we should definitly do this as we need some extension and have to set the composer vendor dir. Create the file
```.bp-config/options.json``` in the root of your project and enter this json:
```json
{
  "LIBDIR": "",
  "WEBDIR": "public",
  "PHP_VERSION": "{PHP_71_LATEST}",
  "PHP_EXTENSIONS": [ "bz2", "zlib", "openssl", "fpm", "tokenizer", "curl", "mcrypt", "mbstring", "pdo", "pdo_mysql"]
}
```
You can find more options about the PHP Build pack [here](https://docs.developer.swisscom.com/buildpacks/php/index.html).

## .cfignore - Push only things you should
As we already have a nice ```.gitignore``` we should have also a ```.cfignore``` as we don't like to pushing stuff which is dependent to our local setup. Add the file ```.cfignore``` to your
project root:
```
/node_modules
/public/storage
/public/hot
/storage/*.key
/vendor
/.idea
Homestead.json
Homestead.yaml
.env
```
This is acutally the same content as in ```.gitignore``` so you can also copy/rename ```.gitignore```

## Testing your setup
Now let's test our setup. Add the following route in ```routes/api.php``` to verify we have access to the Cloud Foundry environmental variables:
```php
Route::get('/environment', function (Request $request) {
    return response()->json($_ENV);
});
```

Now let push this by executing this command in the ```terminal```:
```bash
cf push
```

> Caugtion: Uncomment the above lines asap after validation, as these lines expose sentitive information (e.g. Your service credentials)

## Bind mariaDB as service
First things first. Create a mariaDB service to the application cloud like [this](https://docs.developer.swisscom.com/console/services.html).

Next we add the service to our ```manifest.yaml```. This will auto bind the service to our application. After the change the file should look like this:
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

Next we edit ```config/database.php``` to retreive the mariaDB information and pass them to Laravel.

Add these lines of codes to the very top of the file:
```php
$cfEnv = getenv('VCAP_SERVICES');
if ($cfEnv !== false) {
  try {
    $vcapServices = json_decode(getenv('VCAP_SERVICES'));
    $mariaDbConnection = head($vcapServices->mariadb)->credentials;

    $_ENV['DB_CONNECTION'] = 'mysql';
    $_ENV['DB_HOST'] = $mariaDbConnection->host;
    $_ENV['DB_PORT'] = $mariaDbConnection->port;
    $_ENV['DB_DATABASE'] = $mariaDbConnection->database;
    $_ENV['DB_USERNAME'] = $mariaDbConnection->username;
    $_ENV['DB_PASSWORD'] = $mariaDbConnection->password;
    $_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'production';
    $_ENV['APP_DEBUG'] = $_ENV['APP_ENV'] ?? 'false';
    $_ENV['APP_KEY'] = $_ENV['APP_ENV'] ?? 'CFENV!!!';
  }
  catch (Exception $e) {
    dd($e->getMessage());
  }
}
```

After this change we have the mariaDB information on the root level of our environmental variables, just the way Laravel is used to it. Doing it this way ensures the standard way of
local development with Laravel (see ```.env``` handling).

As the PHP Buildpack can't handle the helper method ```env(...)``` during staging we have to fix this in ```config/database.php```. Replace the ```mysql``` config so it looks like this:
```php
'mysql' => [
    'driver' => 'mysql',
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => $_ENV['DB_PORT'] ?? '3306',
    'database' => $_ENV['DB_DATABASE'] ?? 'forge',
    'username' => $_ENV['DB_USERNAME'] ?? 'forge',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
    'engine' => null,
],
```
Just replacing ```env(...)``` with ```$_ENV[...]``` and some PHP 7 fallback handling with ```??```.

## Migrations
Luckily for us Laravel provides some nice commands to scaffold a migration for use. Execute ```php artisan make:migration create_robots_table --create=robots```. This will produce
a migration file in ```database/migrations``` for robots with a table ```robots```. Now modify the schema in this new file to look like this:
```php
Schema::create('robots', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name');
    $table->string('year');
    $table->enum('type', [ 'droid', 'mechanical' ]);
    $table->timestamps();
});
```

New let's add a composer script to automatically run migrations after each ```cf push```. For this edit the composer script to look like this:
```json
"post-install-cmd": [
    "Illuminate\\Foundation\\ComposerScripts::postInstall",
    "php artisan optimize",
    "php artisan migrate --force"
],
```
Now we can run ```cf push``` and our database will be migrated every time we ```cf push```.

> Caution: Normally I don't do migrations automated with composer. Migrations should be handled manually or a CI will perform the task. This is just for simplicity or development.

## Seeding test data
As it's useless to have an API without any data we will setup a seeder to have nice test data. Run this command to scaffold a seeder
```php artisan make:seeder RobotsTableSeeder```. This will produce a file in ```database/seeds```. Now let add test data. Add these lines of codes to our new file in method ```run()```
```php
DB::table('robots')->insert([
  'name' => 'R2D2',
  'year' => '2016',
  'type' => 'droid',
  'created_at' => \Carbon\Carbon::now(),
  'updated_at' => \Carbon\Carbon::now()
]);

DB::table('robots')->insert([
  'name' => 'B2-RP',
  'year' => '1999',
  'type' => 'mechanical',
  'created_at' => \Carbon\Carbon::now(),
  'updated_at' => \Carbon\Carbon::now()
]);

DB::table('robots')->insert([
  'name' => 'E-XD',
  'year' => '2000',
  'type' => 'droid',
  'created_at' => \Carbon\Carbon::now(),
  'updated_at' => \Carbon\Carbon::now()
]);
```

Now we have to register the this seeder in ```database/seeds/DatabaseSeeder.php``` by adding this line to method ```run()```:
```php
$this->call(RobotsTableSeeder::class);
```

## Use SSH to perform operation tasks - e.g. seeding data
As we should not seed test data automatically we can do this manually with Cloud Foundry.

There for we are using ```cf ssh <app-name>``` to get access to our app. Once logged in via SSH we must configure PHP for CLI useage. We do this by executing
these command:
```bash
export PATH=$PATH:/home/vcap/app/php/bin
export PHPRC=/home/vcap/app/php/etc/php.ini
```

We can verify that the right ```php.ini``` is used with command ```php --ini```.

Now as we have PHP configured as it's would be when accessing over HTTP we can execute any ```artisan``` command we like. Let's seed our data base with:
```php
php artisan db:seed
```

> Tip: Execute ```php artisan``` in the ```terminal``` to see other tasks

## Exposing data over API
Now let's expose the robots data over ```/api/robots```. Therefor open ```routes/api.php``` and this as route:
```php
Route::get('/robots', function (Request $request) {
    $result = \Illuminate\Support\Facades\DB::table('robots')->get();
    return response()->json($result );
});
```
Now we can visit ```/api/robots``` and will get the robots as json.

## Task Scheduling / Cron jobs
As Laravel has a impressive task scheduling and processing engine (this engine also handles queued email), we like to have this but Cloud Foundry has no ```cron``` jobs ability.

A solution I like to implement is to trigger the entry job over API. To do this add the below code to ```routes/api.php```:
```php
Route::get('/cron', function (Request $request) {
    $result = \Illuminate\Support\Facades\Artisan::call('schedule:run');
    return response()->json((object)array("exitCode" => $result));
});
```

This will expose the ```php artisan schedule:run``` command over API. Now I could setup Jenkins to call this every minute or so.

> Tip: Protect this API endpoint.

> You can also use [this](https://github.com/18F/cg-cron) repo to run recurring jobs.

## Loggin to CF
By default, Cloud Foundry logs all http calls to the console. But what if we like to log our application logs to this console.
Nothing simpler than that. Go to file ```bootstrap/app.php``` and add these the below lines after the ```Create The Application``` block:
```php
/*
|--------------------------------------------------------------------------
| Extend monolog
|--------------------------------------------------------------------------
|
*/

$app->configureMonologUsing(function ($monolog) {
    // Logs to the CF Console
    $monolog->pushHandler(new \Monolog\Handler\ErrorLogHandler());
});
```

Now we could even add an [ELK](https://docs.developer.swisscom.com/service-offerings/elk.html) service and the good thing is that it works out of the box as every log entry in the Cloud
Foundry console also will be logged to ELK by default.

## FAQ
### How to set the ciphers key
Set a user-provided environmental variable named ```APP_KEY``` for your application ([doc](https://docs.developer.swisscom.com/devguide/deploy-apps/environment-variable.html#USER)). Laravel will automatically pick this up.
You can generate a new key with ```php artisan key:generate```.
### MariaDB String length
As we are using mariaDB, the string length is small and doesn't suite Laravel. To fix this add this line of code to the ```boot``` method in file
```app/Providers/AppServiceProviders.php```:
```php
Schema::defaultStringLength(191);
```
Remember to import/use the ```Schema``` facade.
### Use an external logging provider (Loggly)
Also [Loggly](https://www.loggly.com/) can be integrated easily. Add the below code to ```boostrap/app.php``` after the ```Create The Application``` block:
```php
/*
|--------------------------------------------------------------------------
| Extend monolog
|--------------------------------------------------------------------------
|
*/

$app->configureMonologUsing(function ($monolog) {
    $handler = new \Monolog\Handler\LogglyHandler('<your-api-key>' ,\Monolog\Logger::DEBUG);
    $handler->setTag('Webinar Laravel');
    $monolog->pushHandler($handler);
});
```