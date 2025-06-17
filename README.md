# Laravel .env Manager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/daguilar/belich-env-manager.svg?style=flat-square)](https://packagist.org/packages/daguilar/belich-env-manager)
[![Total Downloads](https://img.shields.io/packagist/dt/daguilar/belich-env-manager.svg?style=flat-square)](https://packagist.org/packages/daguilar/belich-env-manager)
[![License](https://img.shields.io/packagist/l/daguilar/belich-env-manager.svg?style=flat-square)](LICENSE)

A simple and versatil package to programmatically manage your Laravel `.env` files. 

It allows you to read, write, and update environment variables with ease, including support for comments and automatic backups.

> **Warning:** This package is currently in a **beta stage**. The `main` branch contains the latest beta version. While it aims to be stable, please use it with caution in production environments and ensure you have backups. We appreciate any feedback or bug reports during this phase.

## Features

*   **Read and Parse**: 
    - Accurately reads and parses existing `.env` files directly (in .env format), understanding their structure.
    - You can also use the package using Laravel Collections to manage the .env files.
*   **Get Variables**: 
    - Retrieve the value of any environment variable.
    - Option to provide a default value if the key is not found.
*   **Set Variables**: Add new variables or update existing ones.
    - Support for adding/updating inline comments.
    - Support for adding/updating block comments above a variable.
*   **Remove Variables**: 
    - Delete environment variables from the file.
*   **Check Existence**: 
    - Verify if a specific key exists in the `.env` file.
*   **Comment Preservation**: 
    - Intelligently preserves existing inline and block comments when modifying variables.
*   **`export` Prefix Preservation**: 
    - Recognizes and maintains the `export` prefix for variables if present.
*   **Automatic Backups**: 
    - Creates a backup of the `.env` file before any changes are saved.
    - Configurable backup path.
    - Configurable retention policy (number of days to keep backups).
    *   Automatic pruning of old backups.
*   **Fluent API**: 
    - Chain methods for a more expressive way to manage variables (e.g., `Env::set('KEY', 'value')->save()`).
*   **Facade and Dependency Injection**: 
    - Usable via a convenient `Env` facade or by injecting the `EnvManager` service.
*   **Configuration**: 
    - Publishable configuration file for backup settings.
*   **PSR-12 Compliant**: 
    - Code follows PSR-12 coding standards.
*   **PHP 8.3+**: 
    - Leverages modern PHP features.

## Installation

You can install the package via composer:

```bash
composer require daguilar/belich-env-manager:dev-master
```

Or in your composer file:

```bash
"daguilar/belich-env-manager": "dev-master"
```

The package will automatically register its service provider. Optionally, you can publish the configuration file using:

```bash 
php artisan vendor:publish --provider="Daguilar\BelichEnvManager\BelichEnvManagerServiceProvider" --tag="belich-env-manager-config"
```

This will create a `config/belich-env-manager.php` file in your project where you can customize the backup settings.

## Configuration

After publishing the configuration file, you can find it at `config/belich-env-manager.php`. Available options:

- `backup.enabled`: (bool) Enable or disable automatic backups. Defaults to true.
- `backup.path`: (string) The directory path where backups will be stored. Defaults to `storage_path('app/belich/env_backups')`.
- `backup.retention_days`: (int) The number of days to retain backups. Backups older than this will be pruned. Set to 0 or null to retain forever. 
    - Defaults to 7.

## Usage 

You can use the Env facade or inject the `Daguilar\BelichEnvManager\Services\EnvManager` class.

Please, remember this is a **beta version**, use it with the utmost caution.

## Using the .env format (you will directly modify the .env file)

You can manipulate the .env file in its own format, using the `.env format`:

```php 

use Daguilar\BelichEnvManager\Facades\Env;

// Get all the data as .env 
$allTheDataAsEnv = Env::getEnvContent();

// Get all the data as Collection:
$allTheDataAsCollection = Env::getEnvContentAsCollection();

// Get all the data as array:
$allTheDataAsArray = Env::getEnvContentAsArray();

// Other operations with the ,env file data:

// Get a value
$appName = Env::get('APP_NAME');

// Get a value with a default
$dbHost = Env::get('DB_HOST', '127.0.0.1');

// Check if a key exists
if (Env::has('APP_DEBUG')) {
    // ...
}

// Set a value
Env::set('NEW_VARIABLE', 'its_value')->save(); 

// Set a value with an inline comment
Env::set('API_KEY', 'your_api_key_here')->commentLine('This is an important API key')->save();

// Set a value with block comments above
Env::set('MAIL_HOST', 'smtp.example.com')->commentsAbove([
    '# Mail Configuration',
    '# Ensure these are correct for your provider'
])->save();

// Remove a key
Env::remove('OLD_VARIABLE');

// Save changes to the .env file
// This will also trigger a backup if enabled
Env::save();

// Get the entire .env content as a string
$envContent = Env::getEnvContent();
```

### Using multipleSet() for Batch Updates (.env format).

For setting multiple variables  at once with their respective comments, you can use the multipleSet() method for a fluent API:

```php 
use Daguilar\BelichEnvManager\Facades\Env; 

Env::multipleSet()
    ->setItem('BATCH_KEY_1', 'value1')
    ->commentLine('Inline comment for BATCH_KEY_1')
    ->setItem('BATCH_KEY_2', 'value2')
    ->commentsAbove(['# Block comment for BATCH_KEY_2'])
    ->save(); 
```

### Using Dependency Injection (.env format)

```php 
use Daguilar\BelichEnvManager\Services\EnvManager;

class YourService
{
    protected EnvManager $envManager;

    public function __construct(EnvManager $envManager)
    {
        $this->envManager = $envManager;
    }

    public function updateEnv()
    {
        $this->envManager
            ->set('MY_SETTING', 'new_value')
            ->commentLine('Updated via DI')
            ->save();
        
        $currentAppName = $this->envManager->get('APP_NAME');
        // Do something with $currentAppName
    }
}
```

## Using Laravel Collection Format

```php 
$manager = app(EnvCollectionManager::class);

// Obtener toda la colección
$envCollection = $manager->asCollection();

// Modificar un valor existente
$manager->set('APP_DEBUG', 'false')
    ->commentLine('Production mode');

// Añadir nuevo valor
$manager->set('NEW_KEY', 'value')
    ->commentsAbove('This is a new setting');

// Eliminar un valor
$manager->remove('OLD_KEY');

// Actualizar desde colección externa
$newCollection = collect([...]);
$manager->updateFileFromCollection($newCollection);

// Guardar cambios
$manager->save();

// Using fluent interface
$manager->setByKey('APP_NAME', 'Laravel')
    ->comment(false)
    ->commentsAbove('Application Name')
    ->commentLine('Do not change')
    ->save();

// And of course, you can use the Facade (EnvCollect)
EnvCollect::get('APP_NAME')
    ->comment(false)
    ->commentLine('Do not change')
    ->save();
```

## Backup Management

Backups are created automatically when Env::save() or EnvManager::save() is called, provided backups are enabled in the configuration. Old backups are pruned according to the `retention_days` setting.

## Testing 

```bash 
composer test
```

## Contributing

Contributions are welcome! Please feel free to submit a pull request or create an issue for any bugs or feature requests.

- Fork the Project.
- Create your Feature Branch (`git checkout -b feature`).
- Commit your Changes (`git commit -m 'Add some AmazingFeature'`).
- Push to the Branch (`git push origin feature`/AmazingFeature`).
- Open a Pull Request.

## License

The Laravel **`ENV Manager`** is open-sourced software licensed under the MIT license.