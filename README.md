# ElvisAPI

A PHP wrapper library for integrating with the WoodWing Elvis DAM API.

This library provides a simple and structured way to interact with
WoodWing Elvis Digital Asset Management (DAM) systems, supporting both
version 5 and 6 APIs. It helps developers authenticate, send requests,
and process responses with minimal effort.

------------------------------------------------------------------------

## ✨ Features

-   Support for WoodWing Elvis API v5 and v6
-   Simple PHP integration
-   Authentication handling
-   Request abstraction
-   Lightweight and dependency‑free
-   Easy to extend for custom endpoints

------------------------------------------------------------------------

## 📦 Installation

Clone the repository:

``` bash
git clone https://github.com/syahrul84/ElvisAPI.git
```

Or copy the source files into your project.

------------------------------------------------------------------------

## 🚀 Usage

``` php
require_once 'ElvisAPI.php';

$elvis = new ElvisAPI([
    'base_url' => 'https://your-elvis-server',
    'username' => 'your-username',
    'password' => 'your-password',
]);

$response = $elvis->search([
    'q' => 'assetName:example'
]);

print_r($response);
```

------------------------------------------------------------------------

## 🧠 Use Cases

-   Integrating PHP applications with WoodWing Elvis DAM
-   Automating asset management workflows
-   Media publishing pipelines
-   CMS integrations
-   Bulk asset operations

------------------------------------------------------------------------

## ⚙️ Requirements

-   PHP 5.6+ (recommended PHP 7+ or newer)
-   Access to a WoodWing Elvis DAM server

------------------------------------------------------------------------

## 📁 Project Structure

    ElvisAPI.php     # Main API wrapper
    README.md        # Documentation

------------------------------------------------------------------------

## 🔒 Limitations

-   Basic wrapper (not a full SDK)
-   Depends on Elvis server configuration
-   Limited error handling (can be extended)

------------------------------------------------------------------------

## 🛠 Future Improvements

-   Composer package support
-   Namespaced class version
-   Unit tests
-   Improved exception handling
-   More endpoint helpers

------------------------------------------------------------------------

## 👨‍💻 About WoodWing Elvis

WoodWing Elvis is a Digital Asset Management (DAM) system used for
storing, searching, and managing media assets across organizations.

This library provides a convenient PHP interface for interacting with
Elvis APIs. citeturn0search0

------------------------------------------------------------------------

## 👨‍💻 Author

Syahrul Farhan

------------------------------------------------------------------------

## 📄 License

MIT License
