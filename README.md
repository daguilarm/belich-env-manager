# Laravel .env Manager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/daguilar/belich-env-manager.svg?style=flat-square)](https://packagist.org/packages/daguilar/belich-env-manager)
[![Total Downloads](https://img.shields.io/packagist/dt/daguilar/belich-env-manager.svg?style=flat-square)](https://packagist.org/packages/daguilar/belich-env-manager)
[![License](https://img.shields.io/packagist/l/daguilar/belich-env-manager.svg?style=flat-square)](LICENSE)

A simple and robust package to programmatically manage your Laravel `.env` files. It allows you to read, write, and update environment variables with ease, including support for comments and automatic backups.

## Features

*   **Read and Parse**: Accurately reads and parses existing `.env` files, understanding their structure.
*   **Get Variables**: Retrieve the value of any environment variable.
    *   Option to provide a default value if the key is not found.
*   **Set Variables**: Add new variables or update existing ones.
    *   Support for adding/updating inline comments.
    *   Support for adding/updating block comments above a variable.
*   **Remove Variables**: Delete environment variables from the file.
*   **Check Existence**: Verify if a specific key exists in the `.env` file.
*   **Comment Preservation**: Intelligently preserves existing inline and block comments when modifying variables.
*   **`export` Prefix Preservation**: Recognizes and maintains the `export` prefix for variables if present.
*   **Automatic Backups**: Creates a backup of the `.env` file before any changes are saved.
    *   Configurable backup path.
    *   Configurable retention policy (number of days to keep backups).
    *   Automatic pruning of old backups.
*   **Fluent API**: Chain methods for a more expressive way to manage variables (e.g., `Env::set('KEY', 'value')->save()`).
*   **Facade and Dependency Injection**: Usable via a convenient `Env` facade or by injecting the `EnvManager` service.
*   **Configuration**: Publishable configuration file for backup settings.
*   **PSR-12 Compliant**: Code follows PSR-12 coding standards.
*   **PHP 8.1+**: Leverages modern PHP features.

## Installation

You can install the package via composer:

```bash
composer require your-vendor/your-package-name
