# Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features

[![CI Workflow](https://github.com/derafu/http/actions/workflows/ci.yml/badge.svg?branch=main&event=push)](https://github.com/derafu/http/actions/workflows/ci.yml?query=branch%3Amain)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://opensource.org/licenses/MIT)

A PSR and RFC compliant HTTP library that provides elegant request/response handling, content negotiation and problem details for PHP applications.

## Why Derafu\Http?

### 🎯 **Simple, but not limited, HTTP Handling**

Most HTTP libraries either do too much or too little. Derafu\Http provides:

- **Extended Request/Response**: Smart content type negotiation and safe data access.
- **Content Negotiation**: Intelligent format detection and response transformation.
- **Problem Details**: RFC 7807 implementation for structured error handling.
- **PSR Compliance**: Built on PSR-7 and PSR-15 standards.

### ✨ **Key Features**

- **Smart Request Handling**: Safe access to query, post, and JSON data.
- **Intelligent Responses**: Automatic content negotiation and format transformation.
- **Structured Errors**: Complete RFC 7807 Problem Details implementation.
- **Modular Design**: Core HTTP functionality with optional middleware support.
- **Type Safety**: Use of enums for HTTP status codes and content types.

## Installation

```bash
composer require derafu/http
```

## Basic Usage

### public/index.php

```php
use Derafu\Http\Kernel;
use Derafu\Kernel\Environment;

// In real project change this to:
// require_once dirname(__DIR__) . '/vendor/derafu/http/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/bootstrap.php';

return fn (array $context): Kernel => new Kernel(new Environment(
    $context['APP_ENV'],
    (bool) $context['APP_DEBUG'],
    $context
));
```

Test the application with:

```bash
php -S localhost:9000 -t public
```

### Working with Requests

```php
// Safe access to request data.
$id = $request->query('id', 0);
$data = $request->json();
$file = $request->file('document');

// Content negotiation.
$format = $request->getPreferredFormat();
```

### Creating Responses

```php
// Automatic content negotiation.
// Returns JSON for API requests in `/api` paths.
return ['status' => 'ok'];

// Explicit responses.
return (new Response())->asJson($data)->withHttpStatus(HttpStatus::CREATED);

// Redirect.
return (new Response())->redirect('https://www.example.com');
```

### Error Handling with Problem Details

Any exception will be treated as "Problem Detail" (RFC 7807). To have control over all fields, exceptions must implement `HttpExceptionInterface`.

```json
{
    "type": "about:blank",
    "title": "Not Found",
    "status": 404,
    "detail": "No route found for \"/api/mustFail\".",
    "instance": "/api/mustFail",
    "extensions": {
        "timestamp": "2025-02-20T22:04:25+00:00",
        "environment": "dev",
        "debug": true,
        "context": [],
        "throwable": null
    }
}
```

## Integration with Other Packages

Derafu\Http is designed to work with other Derafu packages:

### Required

- **derafu/kernel**: The core of the application, with dependency injection.
- **derafu/renderer**: For template rendering, with dependency on **derafu/twig**.
- **derafu/routing**: For URL routing, with autodiscovery of templates.
- **derafu/translation**: For I18n, with a simple translator that supports ICU.

### Optional

- **derafu/markdown**: For renderig templates in markdown format.

### Suggested

- **derafu/data-processor**: For data processing: format, cast, sanitize and validate.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This library is licensed under the MIT License. See the `LICENSE` file for more details.
