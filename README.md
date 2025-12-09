# Tonics Template System

A fast, extensible tokenizer-based template engine for PHP. Unlike traditional regex-based template engines, Tonics uses a state machine tokenizer that parses templates character by character, providing better performance and flexibility.

## Table of Contents

### 📖 Quick Navigation

| Section | Description | Jump Link |
|---------|-------------|-----------|
| **Getting Started** | Installation and basic setup | [→](#installation) |
| **Core Concepts** | Template syntax and features | [→](#template-syntax) |
| **Real-World Examples** | Complete usage scenarios | [→](#complete-usage-scenarios) |
| **Advanced Topics** | Hooks, caching, and custom handlers | [→](#advanced-topics) |
| **Troubleshooting** | Common issues and solutions | [→](#troubleshooting) |

### 🚀 Quick Jump to Scenarios

Skip the basics and dive straight into real-world examples:

| Scenario | Description | Jump Link |
|----------|-------------|-----------|
| **🎨 Theme System** | Plugin-ready theme with dynamic content injection | [Jump →](#scenario-1-building-a-theme-system-with-hooks) |
| **🔍 SEO Management** | Dynamic meta tags, Open Graph, Schema.org | [Jump →](#scenario-2-dynamic-seo-management) |
| **🛒 E-commerce** | Product listings with loops and conditionals | [Jump →](#scenario-3-e-commerce-product-listing-with-loops) |
| **🌍 Multi-Language** | Internationalization and locale switching | [Jump →](#scenario-4-multi-language-template-system) |
| **📊 Dashboard** | Complex nested data with functions | [Jump →](#scenario-5-dashboard-with-nested-loops-and-functions) |

### 📚 Full Documentation Index

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
  - [With Extension Bundle](#with-extension-bundle-recommended)
  - [Core Only](#core-only)
- [Documentation](#documentation)
  - [Basic Setup](#basic-setup)
  - [Basic Rendering](#basic-rendering)
  - [Template Loaders](#template-loaders)
  - [Template Namespaces](#template-namespaces)
  - [Template Syntax](#template-syntax)
    - [Variables](#variables)
    - [Blocks](#blocks)
    - [Template Inheritance](#template-inheritance)
    - [Imports](#imports)
    - [Functions](#functions)
    - [Raw Content](#raw-content)
    - [Comments](#comments)
  - [Extension Bundle Features](#extension-bundle-features)
  - [Caching](#caching)
  - [Custom Mode Handlers](#custom-mode-handlers)
  - [Render Modes](#render-modes)
  - [Best Practices](#best-practices)
  - [Example: Complete Application Setup](#example-complete-application-setup)
- [Helper Functions](#helper-functions)
- [Advanced Topics](#advanced-topics)
- [Complete Usage Scenarios](#complete-usage-scenarios)
  - [Scenario 1: Building a Theme System with Hooks](#scenario-1-building-a-theme-system-with-hooks)
  - [Scenario 2: Dynamic SEO Management](#scenario-2-dynamic-seo-management)
  - [Scenario 3: E-commerce Product Listing with Loops](#scenario-3-e-commerce-product-listing-with-loops)
  - [Scenario 4: Multi-Language Template System](#scenario-4-multi-language-template-system)
  - [Scenario 5: Dashboard with Nested Loops and Functions](#scenario-5-dashboard-with-nested-loops-and-functions)
- [Hook System Deep Dive](#hook-system-deep-dive)
- [Troubleshooting](#troubleshooting)
- [Performance Tips](#performance-tips)
- [License](#license)
- [Contributing](#contributing)

---

## Features

* **Tokenizer-Based Parsing** - Uses a state machine instead of regex for better performance
* **Square Bracket Syntax** - Clean `[[tag]]` syntax for template markup
* **Template Inheritance** - Support for template blocks, imports, and inheritance
* **Extensible Architecture** - Easy to create custom tokenizers and renderers
* **Built-in Caching** - Optional caching support (Memcached)
* **Context-Free Modes** - Flexible mode system for different template behaviors
* **Extension Bundle Available** - Loops, conditionals, hooks, and string functions

## Requirements

* PHP 8.0 and above
* PHP mbstring extension enabled

## Installation

### With Extension Bundle (Recommended)

To get the full power of the template system including loops, conditionals, and hooks:

```sh
composer require devsrealm/tonics-template-system-extension-bundle
```

### Core Only

If you only need the core template system:

```sh
composer require devsrealm/tonics-template-system
```

## Documentation

### Basic Setup

Before you start, wire up the TonicsView dependencies. Here's the complete setup based on production usage:

```php
use Devsrealm\TonicsTemplateSystem\TonicsView;
use Devsrealm\TonicsTemplateSystem\Loader\TonicsTemplateFileLoader;
use Devsrealm\TonicsTemplateSystem\Tokenizer\State\DefaultTokenizerState;
use Devsrealm\TonicsTemplateSystem\Content;
use Devsrealm\TonicsTemplateSystem\Caching\TonicsTemplateApcuCache;
use Devsrealm\TonicsTemplateSystem\Caching\TonicsTemplateFileCache;
// Note: TonicsTemplateMemcached is NOT included - create your own adapter

// Step 1: Configure the template loader with multiple directories
$templateLoader = new TonicsTemplateFileLoader('html');

// Load templates from multiple paths (optionally with namespaces)
$templatePaths = [
    '/path/to/core/templates',           // Without namespace
    'theme' => '/path/to/theme/templates', // With 'theme' namespace
    'admin' => '/path/to/admin/templates', // With 'admin' namespace
];

foreach ($templatePaths as $namespace => $path) {
    $namespace = is_string($namespace) ? $namespace : null;
    if (is_dir($path)) {
        $templateLoader->resolveTemplateFiles($path, $namespace);
    }
}

// Step 2: Create TonicsView with settings
$settings = [
    'templateLoader' => $templateLoader,
    'tokenizerState' => new DefaultTokenizerState(),
    'content' => new Content(),
];

// Optional: Add caching support (recommended for production)
if (extension_loaded('apcu') && apcu_enabled()) {
    // APCu cache - fastest for single server
    $settings['templateCache'] = new TonicsTemplateApcuCache();
} elseif (extension_loaded('memcached')) {
    // Memcached - best for multiple servers
    $settings['templateCache'] = new TonicsTemplateMemcached();
}

$view = new TonicsView($settings);

// Step 3: Register mode handlers (if using extension bundle)
// Conditionals
$view->addModeHandler('if', IfCondition::class, false);

// Loops
$view->addModeHandler('each', EachLoop::class, false);
$view->addModeHandler('foreach', EachLoop::class, false); // Alias

// String Functions
$view->addModeHandler('string_trim', StringFunctions::class);
$view->addModeHandler('string_ucfirst', StringFunctions::class);
$view->addModeHandler('string_strtoupper', StringFunctions::class);
$view->addModeHandler('string_strtolower', StringFunctions::class);
$view->addModeHandler('string_htmlentities', StringFunctions::class);
$view->addModeHandler('string_substr', StringFunctions::class);
// ... add more string functions as needed

// Hooks
$view->addModeHandler('add_hook', Hook::class);
$view->addModeHandler('hook_into', Hook::class);
$view->addModeHandler('on_hook_into_event', OnHookIntoEvent::class);
$view->addModeHandler('reset_hook', Hook::class);
$view->addModeHandler('reset_all_hooks', Hook::class);

// Now you're ready to use the view
$view->setCachePrefix('MyApp_Templates_');
```

### Basic Rendering

**Simple Variable Output:**

Template file (`welcome.html`):
```html
<h1>Welcome, [[v("name")]]!</h1>
<p>Email: [[v("email")]]</p>
```

PHP code:
```php
$output = $view
    ->setVariableData(['name' => 'John Doe', 'email' => 'john@example.com'])
    ->render('welcome', TonicsView::RENDER_CONCATENATE);
    
echo $output;
```

### Template Loaders

#### File Loader

Load templates from the filesystem:

```php
$loader = new TonicsTemplateFileLoader('html');

// Load templates from a directory
$loader->resolveTemplateFiles('/path/to/templates');

// Load from multiple directories with namespaces
$loader->resolveTemplateFiles('/path/to/theme', 'theme');
$loader->resolveTemplateFiles('/path/to/core', 'core');

// Exclude specific directories
$loader = new TonicsTemplateFileLoader('html', ['.git', 'node_modules']);
```

#### Array Loader

Load templates from arrays (useful for testing):

```php
use Devsrealm\TonicsTemplateSystem\Loader\TonicsTemplateArrayLoader;

$loader = new TonicsTemplateArrayLoader();
$loader->setTemplates([
    'home.html' => '<h1>[[v("title")]]</h1>',
    'about.html' => '<p>About us</p>',
]);
```

### Template Namespaces

Organize templates into logical namespaces for better structure:

**Setup with Namespaces:**

```php
$templateLoader = new TonicsTemplateFileLoader('html');

// Register templates with namespaces
$templatePaths = [
    '/app/templates/core',              // Default namespace (no prefix)
    'theme' => '/app/templates/themes/default',
    'admin' => '/app/templates/admin',
    'email' => '/app/templates/emails',
    'components' => '/app/templates/components',
];

foreach ($templatePaths as $namespace => $path) {
    $namespace = is_string($namespace) ? $namespace : null;
    if (is_dir($path)) {
        $templateLoader->resolveTemplateFiles($path, $namespace);
    }
}
```

**Using Namespaced Templates:**

```php
// Default namespace (no prefix needed)
view('home', $data);           // Loads: /app/templates/core/home.html

// Theme namespace
view('theme::layout', $data);  // Loads: /app/templates/themes/default/layout.html
view('theme::header', $data);  // Loads: /app/templates/themes/default/header.html

// Admin namespace
view('admin::dashboard', $data); // Loads: /app/templates/admin/dashboard.html

// Email namespace
view('email::welcome', $data);   // Loads: /app/templates/emails/welcome.html

// Components namespace
view('components::button', $data); // Loads: /app/templates/components/button.html
```

**In Templates:**

```html
[[-- Use inheritance across namespaces --]]
[[inherit("theme::layout")]]

[[-- Import components --]]
[[import("components::header")]]

<main>
    [[-- Your content --]]
</main>

[[import("components::footer")]]
```

**Benefits:**
- Organize templates by module or feature
- Avoid naming conflicts between different parts of your app
- Easier template management in large applications
- Plugin/theme templates stay separated from core templates

### Template Syntax

#### Variables

Output variables using the `v` or `var` mode:

```html
[[v("username")]]
[[var("user.email")]]
```

**Escaped vs Raw Output:**

```html
[[-- Escaped output (safe for user content) --]]
[[v("user.bio")]]          

[[-- Raw output (use for trusted HTML/JS) --]]
[[_v("html_content")]]     

[[-- Examples: --]]
[[v("user.name")]]          <!-- Output: John &lt;script&gt; (escaped) -->
[[_v("user.name")]]         <!-- Output: John <script> (raw) -->

[[-- Use cases: --]]
[[v("user.comment")]]       <!-- Escape user input -->
[[_v("admin.wysiwyg")]]     <!-- Trusted HTML from admin -->
[[_v("schema.json")]]       <!-- JSON-LD schema -->
```

**Important:** Use `[[v()]]` for user-generated content and `[[_v()]]` only for trusted content you control.

Access nested data with dot notation:

```php
$data = [
    'user' => [
        'name' => 'Jane',
        'profile' => [
            'bio' => 'Developer'
        ]
    ]
];

// In template:
[[v("user.name")]]
[[v("user.profile.bio")]]
```

#### Blocks

Define reusable content blocks:

```html
[[block("header")
    <header>
        <h1>My Site</h1>
    </header>
]]

[[block("footer")
    <footer>&copy; 2025</footer>
]]
```

Reference blocks:

```html
[[usec("header")]]
<main>Content here</main>
[[usec("footer")]]
```

#### Template Inheritance

**Base template (`layout.html`):**

```html
<!DOCTYPE html>
<html>
<head>
    <title>[[block("title")Default Title]]</title>
</head>
<body>
    [[block("content")
        Default content
    ]]
</body>
</html>
```

**Child template (`page.html`):**

```html
[[inherit("layout")
    [[block("title")My Page Title]]
    
    [[block("content")
        <h1>Welcome to my page</h1>
        <p>This overrides the default content.</p>
    ]]
]]
```

#### Imports

Include other templates:

```html
[[import("header")]]
<main>
    Page content
</main>
[[import("footer")]]
```

#### Functions

Define and call template functions:

```html
[[func("greet", "name")
    <p>Hello, [[v("name")]]!</p>
]]

[[greet("John")]]
[[greet("Jane")]]
```

#### Raw Content

Output raw content without parsing. Raw content uses three square brackets `[[[` by default.

**Basic raw content:**

```html
[[[
    This content won't be parsed: [[v("test")]]
    All template tags are treated as literal text
]]]
```

**Including brackets in raw content:**

To include literal square brackets in your raw content, use MORE brackets on the outside than what you want to display inside.

- To display `[[[` use 4 brackets: `[[[[content]]]]`
- To display `[[[[` use 5 brackets: `[[[[[content]]]]]`
- To display `[[[[[` use 6 brackets: `[[[[[[content]]]]]]`

**Examples:**

```html
[[[[
    You can now include [[[ in your content
]]]]
```

```html
[[[[[
    This raw block can contain [[ to [[[[ brackets literally
]]]]]
```

```html
[[[[[[
    Maximum nesting: [ to [[[[[ all valid inside
]]]]]]
```

The rule: **outer brackets = max inner brackets + 1**

#### Comments

Template comments (not rendered):

```html
[[-- This is a comment and won't appear in output --]]
```

### Extension Bundle Features

When using the extension bundle, you get additional powerful features:

#### Conditionals

```html
[[if("v[user.isAdmin]")
    <a href="/admin">Admin Panel</a>
]]

[[if("v[posts]")
    <h2>Posts</h2>
]]
```

#### Loops

Iterate over arrays:

```html
[[each("post in posts")
    <article>
        <h3>[[v("post.title")]]</h3>
        <p>[[v("post.excerpt")]]</p>
        <small>Index: [[v("_loop.index")]]</small>
    </article>
]]
```

The `_loop` variable provides:
- `_loop.index` - Current iteration index (0-based)
- `_loop.iteration` - Current iteration (1-based)
- `_loop.first` - Boolean, true on first iteration
- `_loop.last` - Boolean, true on last iteration

#### String Functions

Built-in string manipulation:

```html
[[string_ucfirst("v[username]")]]
[[string_strtoupper("v[title]")]]
[[string_htmlentities("v[content]")]]
[[string_trim("v[input]")]]
```

**Complete list of available string functions:**

| Function | Description | Example |
|----------|-------------|---------|
| `string_addslashes` | Add slashes to string | `[[string_addslashes("v[text]")]]` |
| `string_chop` | Alias of rtrim | `[[string_chop("v[text]")]]` |
| `string_trim` | Remove whitespace | `[[string_trim("v[text]")]]` |
| `string_ltrim` | Remove left whitespace | `[[string_ltrim("v[text]")]]` |
| `string_rtrim` | Remove right whitespace | `[[string_rtrim("v[text]")]]` |
| `string_lcfirst` | Lowercase first character | `[[string_lcfirst("v[text]")]]` |
| `string_ucfirst` | Uppercase first character | `[[string_ucfirst("v[text]")]]` |
| `string_ucwords` | Uppercase each word | `[[string_ucwords("v[text]")]]` |
| `string_strtolower` | Convert to lowercase | `[[string_strtolower("v[text]")]]` |
| `string_strtoupper` | Convert to uppercase | `[[string_strtoupper("v[text]")]]` |
| `string_htmlentities` | Convert to HTML entities | `[[string_htmlentities("v[text]")]]` |
| `string_html_entity_decode` | Decode HTML entities | `[[string_html_entity_decode("v[text]")]]` |
| `string_htmlspecialchars` | Convert special chars | `[[string_htmlspecialchars("v[text]")]]` |
| `string_htmlspecialchars_decode` | Decode special chars | `[[string_htmlspecialchars_decode("v[text]")]]` |
| `string_nl2br` | Convert newlines to `<br>` | `[[string_nl2br("v[text]")]]` |
| `string_strip_tags` | Strip HTML tags | `[[string_strip_tags("v[html]")]]` |
| `string_stripcslashes` | Un-quote string | `[[string_stripcslashes("v[text]")]]` |
| `string_substr` | Extract substring | `[[string_substr("v[text]", "0", "10")]]` |
| `string_str_replace` | Replace substring | `[[string_str_replace("search", "replace", "v[text]")]]` |
| `string_str_ireplace` | Case-insensitive replace | `[[string_str_ireplace("search", "replace", "v[text]")]]` |
| `string_str_pad` | Pad string to length | `[[string_str_pad("v[text]", "10")]]` |
| `string_str_repeat` | Repeat string | `[[string_str_repeat("v[text]", "3")]]` |
| `string_str_shuffle` | Randomly shuffle string | `[[string_str_shuffle("v[text]")]]` |
| `string_strrev` | Reverse string | `[[string_strrev("v[text]")]]` |
| `string_number_format` | Format number | `[[string_number_format("v[price]", "2")]]` |
| `string_sprintf` | Format string | `[[string_sprintf("%s %s", "v[first]", "v[last]")]]` |
| `string_implode` | Join array elements | `[[string_implode(", ", "v[array]")]]` |
| `string_join` | Alias of implode | `[[string_join(", ", "v[array]")]]` |
| `string_wordwrap` | Wrap text to width | `[[string_wordwrap("v[text]", "80")]]` |

### Caching

Enable template caching for better performance. The template system supports multiple caching backends:

#### APCu Cache (Recommended for Single Server)

APCu is fast, built into PHP, and perfect for single-server deployments:

```php
use Devsrealm\TonicsTemplateSystem\Caching\TonicsTemplateApcuCache;

// Check if APCu is available
if (extension_loaded('apcu') && apcu_enabled()) {
    $settings['templateCache'] = new TonicsTemplateApcuCache();
}

$view = new TonicsView($settings);
$view->setCachePrefix('MyApp_Templates_');
```

**Benefits:**
- Very fast (in-memory)
- No external dependencies
- Simple setup
- Built into PHP
- Perfect for single server setups

**Requirements:**
- PHP APCu extension installed and enabled
- `apcu.enabled=1` in php.ini

#### Memcached Cache (Recommended for Multi-Server)

Memcached is ideal for distributed systems and multiple servers.

**Note:** `TonicsTemplateMemcached` is **NOT included** in the template system package. You need to create your own adapter by implementing the `TonicsTemplateCacheInterface`.

**Create Your Custom Memcached Adapter:**

```php
namespace App\Cache;

use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsTemplateCacheInterface;
use Memcached;

class TonicsTemplateMemcached implements TonicsTemplateCacheInterface
{
    private Memcached $memcached;
    
    public function __construct()
    {
        $this->memcached = new Memcached();
        $this->memcached->addServer('localhost', 11211);
        // Or use your existing Memcached instance
    }

    public function add(string $key, mixed $value): bool
    {
        return $this->memcached->set($key, $value);
    }

    public function get(string $key): mixed
    {
        return $this->memcached->get($key);
    }

    public function delete(string $key): mixed
    {
        return $this->memcached->delete($key);
    }

    public function exists(string $key): bool
    {
        $this->memcached->get($key);
        return $this->memcached->getResultCode() !== Memcached::RES_NOTFOUND;
    }

    public function clear(): mixed
    {
        return $this->memcached->flush();
    }
}
```

**Use Your Custom Cache:**

```php
use App\Cache\TonicsTemplateMemcached;

if (extension_loaded('memcached')) {
    $settings['templateCache'] = new TonicsTemplateMemcached();
}

$view = new TonicsView($settings);
$view->setCachePrefix('MyApp_Templates_');
```

**Example Using Existing Cache System:**

If you already have a cache system in your application, wrap it:

```php
namespace App\Cache;

use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsTemplateCacheInterface;
use App\Core\Cache; // Your existing cache

class TonicsTemplateMemcached implements TonicsTemplateCacheInterface
{
    public function add(string $key, mixed $value): bool
    {
        return Cache::set($key, $value);
    }

    public function get(string $key): mixed
    {
        return Cache::get($key);
    }

    public function delete(string $key): mixed
    {
        return Cache::delete($key);
    }

    public function exists(string $key): bool
    {
        return Cache::exists($key);
    }

    public function clear(): mixed
    {
        return Cache::flush();
    }
}
```

**Benefits:**
- Distributed caching across multiple servers
- Large cache size
- Persistent across PHP restarts
- Shared cache between processes

**Requirements:**
- Memcached server running
- PHP Memcached extension
- Custom adapter implementation

#### File Cache (Fallback Option)

File-based caching for when memory caches aren't available:

```php
use Devsrealm\TonicsTemplateSystem\Caching\TonicsTemplateFileCache;

// File cache with different serialization methods
$cacheDir = __DIR__ . '/cache/templates';

// Make sure directory exists
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Choose serialization method: 'JSON', 'SERIALIZE', or 'VAR_EXPORT'
$settings['templateCache'] = new TonicsTemplateFileCache($cacheDir, 'SERIALIZE');

$view = new TonicsView($settings);
$view->setCachePrefix('MyApp_Templates_');
```

**Serialization Methods:**
- `JSON` - Human readable, slower
- `SERIALIZE` - Faster, binary format
- `VAR_EXPORT` - Creates PHP files (uses OPcache automatically)

**Benefits:**
- Works everywhere
- No external dependencies
- Good for development
- VAR_EXPORT method benefits from OPcache

**Drawbacks:**
- Slower than memory caches
- Can fill disk space
- No automatic expiration

#### Creating Custom Cache Adapters

You can create cache adapters for any backend by implementing the `TonicsTemplateCacheInterface`:

```php
namespace Devsrealm\TonicsTemplateSystem\Interfaces;

interface TonicsTemplateCacheInterface
{
    /**
     * Add/Update a cache entry
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @return bool Success status
     */
    public function add(string $key, mixed $value): bool;

    /**
     * Get a cached value
     * @param string $key Cache key
     * @return mixed Cached value or null/false if not found
     */
    public function get(string $key): mixed;

    /**
     * Delete a cache entry
     * @param string $key Cache key
     * @return mixed Success status
     */
    public function delete(string $key): mixed;

    /**
     * Check if cache entry exists
     * @param string $key Cache key
     * @return bool True if exists
     */
    public function exists(string $key): bool;

    /**
     * Clear all cache entries
     * @return mixed Success status
     */
    public function clear(): mixed;
}
```

**Example: Redis Cache Adapter**

```php
namespace App\Cache;

use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsTemplateCacheInterface;
use Redis;

class TonicsTemplateRedisCache implements TonicsTemplateCacheInterface
{
    private Redis $redis;
    private int $ttl = 3600; // 1 hour default

    public function __construct(string $host = 'localhost', int $port = 6379, int $ttl = 3600)
    {
        $this->redis = new Redis();
        $this->redis->connect($host, $port);
        $this->ttl = $ttl;
    }

    public function add(string $key, mixed $value): bool
    {
        return $this->redis->setex($key, $this->ttl, serialize($value));
    }

    public function get(string $key): mixed
    {
        $value = $this->redis->get($key);
        return $value !== false ? unserialize($value) : null;
    }

    public function delete(string $key): mixed
    {
        return $this->redis->del($key);
    }

    public function exists(string $key): bool
    {
        return $this->redis->exists($key) > 0;
    }

    public function clear(): mixed
    {
        return $this->redis->flushDB();
    }
}
```

**Example: Database Cache Adapter**

```php
namespace App\Cache;

use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsTemplateCacheInterface;
use PDO;

class TonicsTemplateDatabaseCache implements TonicsTemplateCacheInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS template_cache (
                cache_key VARCHAR(255) PRIMARY KEY,
                cache_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function add(string $key, mixed $value): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO template_cache (cache_key, cache_value) 
            VALUES (?, ?) 
            ON CONFLICT(cache_key) DO UPDATE SET cache_value = ?, created_at = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$key, serialize($value), serialize($value)]);
    }

    public function get(string $key): mixed
    {
        $stmt = $this->pdo->prepare("SELECT cache_value FROM template_cache WHERE cache_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? unserialize($result) : null;
    }

    public function delete(string $key): mixed
    {
        $stmt = $this->pdo->prepare("DELETE FROM template_cache WHERE cache_key = ?");
        return $stmt->execute([$key]);
    }

    public function exists(string $key): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM template_cache WHERE cache_key = ?");
        $stmt->execute([$key]);
        return $stmt->fetchColumn() > 0;
    }

    public function clear(): mixed
    {
        return $this->pdo->exec("DELETE FROM template_cache");
    }
}
```

**Example: Array Cache (Development Only)**

```php
namespace App\Cache;

use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsTemplateCacheInterface;

class TonicsTemplateArrayCache implements TonicsTemplateCacheInterface
{
    private array $cache = [];

    public function add(string $key, mixed $value): bool
    {
        $this->cache[$key] = $value;
        return true;
    }

    public function get(string $key): mixed
    {
        return $this->cache[$key] ?? null;
    }

    public function delete(string $key): mixed
    {
        unset($this->cache[$key]);
        return true;
    }

    public function exists(string $key): bool
    {
        return isset($this->cache[$key]);
    }

    public function clear(): mixed
    {
        $this->cache = [];
        return true;
    }
}
```

**Usage:**

```php
// Redis
$settings['templateCache'] = new TonicsTemplateRedisCache('localhost', 6379);

// Database
$settings['templateCache'] = new TonicsTemplateDatabaseCache($pdo);

// Array (dev only - lost between requests)
$settings['templateCache'] = new TonicsTemplateArrayCache();
```

#### Choosing the Right Cache

```php
// Smart cache selection based on environment
if (extension_loaded('apcu') && apcu_enabled()) {
    // Best for single server
    $settings['templateCache'] = new TonicsTemplateApcuCache();
} elseif (extension_loaded('memcached')) {
    // Best for multiple servers
    $settings['templateCache'] = new TonicsTemplateMemcached();
} else {
    // Fallback to file cache
    $cacheDir = __DIR__ . '/cache/templates';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    $settings['templateCache'] = new TonicsTemplateFileCache($cacheDir, 'VAR_EXPORT');
}

$view = new TonicsView($settings);
$view->setCachePrefix('MyApp_v1_'); // Version your cache
```

#### Cache Management

```php
// Clear cache when needed
$view->getTemplateCache()->clear();

// Check if specific template is cached
$cacheKey = $view->getCachePrefix() . 'template_name';
if ($view->getTemplateCache()->exists($cacheKey)) {
    // Template is cached
}

// Manually delete specific cache
$view->getTemplateCache()->delete($cacheKey);
```

**Cache Key Format:**
```
{cachePrefix}{templateName}_{hash}
```

**Best Practices:**
- Always set a cache prefix to avoid conflicts
- Version your cache prefix when deploying: `MyApp_v1.2.3_`
- Clear cache after template changes in production
- Use APCu for single servers (fastest)
- Use Memcached for distributed systems
- Use File cache only as fallback

### Custom Mode Handlers

Extend the template system with custom modes:

```php
use Devsrealm\TonicsTemplateSystem\AbstractClasses\TonicsTemplateViewAbstract;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsModeInterface;
use Devsrealm\TonicsTemplateSystem\Interfaces\TonicsModeRendererInterface;

class CustomMode extends TonicsTemplateViewAbstract 
    implements TonicsModeInterface, TonicsModeRendererInterface
{
    public function validate(OnTagToken $tagToken): bool
    {
        // Validate tag arguments
        return true;
    }

    public function stickToContent(OnTagToken $tagToken)
    {
        // Process and add to content
        $this->getTonicsView()
            ->getContent()
            ->addToContent('custom', $tagToken->getContent(), ['data' => $tagToken]);
    }

    public function render(string $content, array $args, array $nodes = []): string
    {
        // Custom rendering logic
        return "<div class='custom'>{$content}</div>";
    }

    public function error(): string
    {
        return 'Custom mode error';
    }
}

// Register the custom mode
$view->addModeHandler('custom', CustomMode::class);

// Use in templates
// [[custom()Your content here]]
```

### Render Modes

The template system supports three rendering modes via constants:

```php
const RENDER_CONCATENATE_AND_OUTPUT = 1;  // Default
const RENDER_CONCATENATE = 2;
const RENDER_TOKENIZE_ONLY = 3;
```

#### 1. RENDER_CONCATENATE_AND_OUTPUT (Default)

Renders the template and **outputs directly to the browser** (uses `echo`).

```php
// Outputs HTML directly to browser
$view->render('template', TonicsView::RENDER_CONCATENATE_AND_OUTPUT);
// or simply (default)
$view->render('template');
```

**Use cases:**
- ✅ Traditional PHP-FPM scripts
- ✅ Simple applications
- ✅ Legacy codebases
- ❌ **NOT for frameworks** (use RENDER_CONCATENATE instead)
- ❌ **NOT for RoadRunner/Swoole** (breaks worker output)

#### 2. RENDER_CONCATENATE (Recommended)

Renders the template and **returns the HTML as a string** without outputting.

```php
// Returns HTML string
$html = $view->render('template', TonicsView::RENDER_CONCATENATE);

// Use the string however you need
echo $html;                    // Output later
file_put_contents('page.html', $html);  // Save to file
$response->write($html);       // Send via PSR-7 response
```

**Use cases:**
- ✅ **Recommended for all modern applications**
- ✅ Framework integration (Laravel, Symfony, etc.)
- ✅ PSR-7 applications
- ✅ RoadRunner/Swoole workers
- ✅ API responses (when returning HTML)
- ✅ Email generation
- ✅ PDF generation
- ✅ Testing (capture output)

**Example with PSR-7:**
```php
// In RoadRunner worker or PSR-7 application
$html = $view->render('home', TonicsView::RENDER_CONCATENATE);

$response = new Response();
$response->getBody()->write($html);
return $response->withHeader('Content-Type', 'text/html');
```

#### 3. RENDER_TOKENIZE_ONLY (Debug/Advanced)

Tokenizes the template and **returns the TonicsView object** with parsed content, without rendering.

```php
// Returns TonicsView object with tokenized content
$viewObject = $view->render('template', TonicsView::RENDER_TOKENIZE_ONLY);

// Access internal data
$contents = $viewObject->getContent()->getContents();
$blocks = $viewObject->getContent()->getBlocks();
$modeStorage = $viewObject->getModeStorage('add_hook');
```

**Use cases:**
- ✅ Debugging template parsing
- ✅ Template analysis
- ✅ Custom post-processing
- ✅ Testing tokenization
- ✅ Advanced template manipulation
- ❌ **NOT for production rendering**

**Debug Example:**
```php
// Debug template structure
$view->setDebug(true);
$viewObject = $view->render('template', TonicsView::RENDER_TOKENIZE_ONLY);

// Inspect parsed content
print_r($viewObject->getContent()->getContents());
print_r($viewObject->getContent()->getBlocks());
```

#### Choosing the Right Mode

```php
// Smart mode selection based on context
function view(string $template, array $data = []): mixed
{
    $view = App::view()->setVariableData($data);
    
    // Detect environment
    if (defined('ROADRUNNER_MODE') || isset($_ENV['RR_MODE'])) {
        // RoadRunner worker - always return string
        return $view->render($template, TonicsView::RENDER_CONCATENATE);
    }
    
    if (PHP_SAPI === 'cli') {
        // CLI - return string for flexibility
        return $view->render($template, TonicsView::RENDER_CONCATENATE);
    }
    
    // Traditional web request - output directly
    $view->render($template, TonicsView::RENDER_CONCATENATE_AND_OUTPUT);
    return null;
}
```

#### Performance Comparison

| Mode | Speed | Output | Use Case |
|------|-------|--------|----------|
| RENDER_CONCATENATE_AND_OUTPUT | ⭐⭐⭐ | Direct | Simple scripts |
| RENDER_CONCATENATE | ⭐⭐⭐ | String | Modern apps ⭐ |
| RENDER_TOKENIZE_ONLY | ⭐⭐ | Object | Debug only |

**Note:** Performance difference between modes 1 and 2 is negligible. Always prefer `RENDER_CONCATENATE` for better control and compatibility.

### Best Practices

1. **Organize Templates**: Use namespaces for different template directories
2. **Cache in Production**: Enable caching for production environments
3. **Reuse Blocks**: Define common blocks in base templates
4. **Use Inheritance**: Leverage template inheritance for consistent layouts
5. **Escape Output**: Use `string_htmlentities` for user-generated content
6. **Separate Logic**: Keep business logic in PHP, use templates for presentation only

### Example: Complete Application Setup

Here's a production-ready setup pattern based on real-world usage:

```php
use Devsrealm\TonicsTemplateSystem\TonicsView;
use Devsrealm\TonicsTemplateSystem\Loader\TonicsTemplateFileLoader;
use Devsrealm\TonicsTemplateSystem\Tokenizer\State\DefaultTokenizerState;
use Devsrealm\TonicsTemplateSystem\Content;
use Devsrealm\TonicsTemplateSystem\Caching\TonicsTemplateApcuCache;
use Devsrealm\TonicsTemplateSystem\Caching\TonicsTemplateMemcached;
use Devsrealm\TonicsTemplateSystem\Caching\TonicsTemplateFileCache;
use Devsrealm\TonicsEventSystem\EventDispatcher;
use Devsrealm\TonicsEventSystem\EventQueue;

class App
{
    private static ?TonicsView $view = null;
    private static ?EventDispatcher $eventDispatcher = null;
    
    /**
     * Initialize the application
     */
    public static function init(): void
    {
        // 1. Setup Events
        $events = self::wireEvents();
        
        // 2. Setup Templates
        $view = self::wireTemplates();
        
        // Store globally
        self::$view = $view;
        self::$eventDispatcher = $events;
    }
    
    /**
     * Wire event system
     */
    private static function wireEvents(): EventDispatcher
    {
        $events = [
            OnHookIntoTemplate::class => [
                AnalyticsHandler::class,
                SeoHandler::class,
                ThemeCustomizer::class,
            ],
            // Add more events...
        ];
        
        $eventQueue = new EventQueue();
        return new EventDispatcher(
            $eventQueue->addMultipleEventsAndHandlers($events)
        );
    }
    
    /**
     * Wire template system
     */
    private static function wireTemplates(): TonicsView
    {
        $templateLoader = new TonicsTemplateFileLoader('html');
        
        // Register template directories
        $templatePaths = [
            __DIR__ . '/templates/core',
            'theme' => __DIR__ . '/templates/theme',
            'admin' => __DIR__ . '/templates/admin',
        ];
        
        foreach ($templatePaths as $namespace => $path) {
            $namespace = is_string($namespace) ? $namespace : null;
            if (is_dir($path)) {
                $templateLoader->resolveTemplateFiles($path, $namespace);
            }
        }
        
        // Configure TonicsView
        $settings = [
            'templateLoader' => $templateLoader,
            'tokenizerState' => new DefaultTokenizerState(),
            'content' => new Content(),
        ];
        
        // Smart cache selection
        if (extension_loaded('apcu') && apcu_enabled()) {
            $settings['templateCache'] = new TonicsTemplateApcuCache();
        } elseif (extension_loaded('memcached')) {
            $settings['templateCache'] = new TonicsTemplateMemcached();
        }
        
        $view = new TonicsView($settings);
        
        // Register mode handlers
        $view->addModeHandler('if', IfCondition::class, false);
        $view->addModeHandler('each', EachLoop::class, false);
        $view->addModeHandler('foreach', EachLoop::class, false);
        
        // String functions
        $stringFunctions = [
            'trim', 'ucfirst', 'ucwords', 'strtolower', 'strtoupper',
            'htmlentities', 'htmlspecialchars', 'strip_tags',
            'substr', 'str_replace', 'number_format'
        ];
        
        foreach ($stringFunctions as $func) {
            $view->addModeHandler("string_{$func}", StringFunctions::class);
        }
        
        // Hooks
        $view->addModeHandler('add_hook', Hook::class);
        $view->addModeHandler('hook_into', Hook::class);
        $view->addModeHandler('on_hook_into_event', OnHookIntoEvent::class);
        $view->addModeHandler('reset_hook', Hook::class);
        $view->addModeHandler('reset_all_hooks', Hook::class);
        
        return $view;
    }
    
    /**
     * Get TonicsView instance
     */
    public static function view(): TonicsView
    {
        return self::$view;
    }
    
    /**
     * Get EventDispatcher instance
     */
    public static function event(): EventDispatcher
    {
        return self::$eventDispatcher;
    }
}

// Initialize application
App::init();

// Helper functions for global access
function view(string $template, array $data = []): string
{
    return App::view()
        ->setVariableData($data)
        ->setCachePrefix('MyApp_')
        ->render($template, TonicsView::RENDER_CONCATENATE);
}

function event(): EventDispatcher
{
    return App::event();
}

// Usage example
$data = [
    'page' => [
        'title' => 'Welcome to My Site',
        'description' => 'A modern web application'
    ],
    'user' => [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'is_premium' => true
    ],
    'posts' => [
        ['title' => 'First Post', 'excerpt' => 'This is the first post'],
        ['title' => 'Second Post', 'excerpt' => 'This is the second post'],
    ]
];

// Render template
echo view('theme::home', $data);

// Or render from core namespace
echo view('core::dashboard', $data);
```

## Helper Functions

For convenient access, create global helper functions in your application:

### Core Helpers

```php
/**
 * Render a view template
 * 
 * @param string $viewName Template name (supports namespace::template syntax)
 * @param array|stdClass $data Data to pass to template
 * @param int $condition Rendering mode (CONCATENATE, CONCATENATE_AND_OUTPUT, TOKENIZE_ONLY)
 * @return mixed
 */
function view(string $viewName, array|stdClass $data = [], int $condition = TonicsView::RENDER_CONCATENATE): mixed
{
    return App::view()
        ->setVariableData($data)
        ->setCachePrefix('MyApp_')
        ->render($viewName, $condition);
}

/**
 * Get the event dispatcher instance
 * 
 * @return EventDispatcher
 */
function event(): EventDispatcher
{
    return App::event();
}

/**
 * Get the TonicsView instance directly
 * 
 * @return TonicsView
 */
function tonicsView(): TonicsView
{
    return App::view();
}
```

### Usage Examples

**Rendering templates:**

```php
// Simple render
echo view('home', ['title' => 'Home Page']);

// With namespace
echo view('theme::layout', $data);

// Just concatenate, don't output
$html = view('email/welcome', ['user' => $user], TonicsView::RENDER_CONCATENATE);

// Get tokenized content (for debugging)
$tokens = view('debug', $data, TonicsView::RENDER_TOKENIZE_ONLY);
```

**Working with events:**

```php
// Dispatch an event
event()->dispatch(new OnHookIntoTemplate(tonicsView()));

// Listen to an event
event()->listen(SomeEvent::class, function($event) {
    // Handle event
});
```

**Render mode constants explained:**

```php
// Mode 1: Output directly to browser (echoes HTML)
TonicsView::RENDER_CONCATENATE_AND_OUTPUT = 1
// Use: Simple scripts, legacy apps
// Returns: null (outputs directly)

// Mode 2: Return as string (recommended for modern apps)
TonicsView::RENDER_CONCATENATE = 2
// Use: Frameworks, RoadRunner, PSR-7, Email generation
// Returns: string (HTML content)

// Mode 3: Return tokenized object (debugging/advanced)
TonicsView::RENDER_TOKENIZE_ONLY = 3
// Use: Template debugging, testing, analysis
// Returns: TonicsView object (with parsed content)
```

**Quick Reference:**

| Constant | Value | Returns | Output | Best For |
|----------|-------|---------|--------|----------|
| `RENDER_CONCATENATE_AND_OUTPUT` | 1 | `null` | Yes (echo) | Simple scripts |
| `RENDER_CONCATENATE` | 2 | `string` | No | Modern apps ⭐ |
| `RENDER_TOKENIZE_ONLY` | 3 | `TonicsView` | No | Debugging |

### Context-Aware Rendering

For applications that run in different contexts (traditional PHP-FPM vs long-running workers like RoadRunner):

```php
function view(string $viewName, array|stdClass $data = [], int $condition = TonicsView::RENDER_CONCATENATE_AND_OUTPUT): mixed
{
    // Detect RoadRunner worker context
    if (isset($GLOBALS['__ROADRUNNER_CORE_INSTANCE__'])) {
        // In RoadRunner, always concatenate (never output directly)
        $condition = TonicsView::RENDER_CONCATENATE;
    }
    
    return App::view()
        ->setVariableData($data)
        ->setCachePrefix('MyApp_')
        ->render($viewName, $condition);
}
```

This ensures your templates work correctly in both traditional and modern PHP environments.

## Advanced Topics

### Custom Tokenizer States

Create custom tokenizer states for specialized parsing:

```php
use Devsrealm\TonicsTemplateSystem\AbstractClasses\TonicsTemplateTokenizerStateAbstract;

class CustomTokenizerState extends TonicsTemplateTokenizerStateAbstract
{
    public static function CustomStateHandler(TonicsView $tv): void
    {
        // Custom tokenization logic
    }
}
```

### Variable Data Types

The template system accepts both arrays and objects:

```php
// Using array
$view->setVariableData(['name' => 'John']);

// Using stdClass
$data = new stdClass();
$data->name = 'John';
$view->setVariableData($data);

// Using custom objects
class User {
    public $name = 'John';
    public function getEmail() { return 'john@example.com'; }
}

$view->setVariableData(['user' => new User()]);
// In template: [[v("user.name")]]
```

## Complete Usage Scenarios

### Scenario 1: Building a Theme System with Hooks

Hooks provide a powerful placeholder system for injecting content into templates dynamically. This is ideal for theme systems where plugins need to inject CSS, JavaScript, or HTML.

**Base Theme Template (`theme.html`):**

```html
<!DOCTYPE html>
[[on_hook_into_event()]]

[[add_hook('Theme::Bootstrap')]]
<html lang="[[add_hook('in_html_lang')en]]">
<head>
    <meta charset="UTF-8">
    [[add_hook('before_meta_tags')]]
    [[add_hook('in_head')]]
    
    <style>
        [[add_hook('in_head_inline_styles')]]
    </style>
    
    [[add_hook('in_head_stylesheet')]]
    [[add_hook('before_closing_head')]]
</head>
<body class="[[add_hook('in_body_class_attribute')]]">
    [[add_hook('in_body')]]
    
    [[add_hook('before_closing_body')]]
</body>
</html>
```

**Page Template (`page.html`):**

```html
[[inherit("theme")]]

[[hook_into('in_head')
    <title>[[v("page.title")]]</title>
    <meta name="description" content="[[v("page.description")]]">
    <link rel="stylesheet" href="/assets/page.css">
]]

[[hook_into('in_body_class_attribute')page-wrapper theme-dark]]

[[hook_into('in_body')
    <header>
        <h1>[[v("page.title")]]</h1>
    </header>
    
    <main>
        [[v("page.content")]]
    </main>
    
    <footer>
        <p>&copy; 2025 My Site</p>
    </footer>
]]

[[hook_into('before_closing_body')
    <script src="/assets/main.js"></script>
]]
```

**PHP Setup with Event-Driven Hooks:**

```php
use Core\events\OnHookIntoTemplate;
use Devsrealm\TonicsTemplateSystem\TonicsView;

// Setup view with hook extension
$view = new TonicsView($settings);
$view->addModeHandler('add_hook', Hook::class);
$view->addModeHandler('hook_into', Hook::class);
$view->addModeHandler('on_hook_into_event', OnHookIntoEvent::class);

// Register hook via event system
event()->listen(OnHookIntoTemplate::class, function(OnHookIntoTemplate $event) {
    $event->hookInto('in_head_stylesheet', function(TonicsView $view) {
        return '<link rel="stylesheet" href="/plugins/gallery/style.css">';
    });
    
    $event->hookInto('in_body', function(TonicsView $view) {
        return '<div class="notification-bar">Welcome!</div>';
    });
});

// Render
$data = [
    'page' => [
        'title' => 'Welcome Page',
        'description' => 'This is the welcome page',
        'content' => '<p>Hello World!</p>'
    ]
];

$output = $view->setVariableData($data)->render('page', TonicsView::RENDER_CONCATENATE);
```

### Scenario 2: Dynamic SEO Management

Build an SEO-friendly template system with conditional meta tags:

**SEO Template (`seo-page.html`):**

```html
[[inherit("theme")]]

[[hook_into('in_head')
    <link rel="canonical" href="[[v('seo.canonical')]]">
    <meta name="robots" content="[[v('seo.index')]], [[v('seo.follow')]]">

    [[if("v[seo.title]")
        <title>[[v('seo.title')]]</title>
    ]]
    
    [[if("v[seo.description]")
        <meta name="description" content="[[v('seo.description')]]"/>
    ]]
    
    [[if("v[seo.og_image]")
        <meta property="og:image" content="[[v('seo.og_image')]]"/>
        <meta property="og:image:width" content="1200"/>
        <meta property="og:image:height" content="630"/>
    ]]
    
    [[if("v[seo.twitter_card]")
        <meta name="twitter:card" content="summary_large_image"/>
        <meta name="twitter:title" content="[[v('seo.title')]]"/>
        <meta name="twitter:description" content="[[v('seo.description')]]"/>
        <meta name="twitter:image" content="[[v('seo.twitter_card')]]"/>
    ]]
    
    [[if("v[seo.schema]")
        <script type="application/ld+json">[[_v('seo.schema')]]</script>
    ]]
]]
```

**PHP Controller:**

```php
$seoData = [
    'seo' => [
        'canonical' => 'https://example.com/blog/my-post',
        'index' => 'index',
        'follow' => 'follow',
        'title' => 'My Awesome Blog Post | My Site',
        'description' => 'Learn about building amazing templates',
        'og_image' => 'https://example.com/images/post-image.jpg',
        'twitter_card' => 'https://example.com/images/post-image.jpg',
        'schema' => json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => 'My Awesome Blog Post',
            'author' => [
                '@type' => 'Person',
                'name' => 'John Doe'
            ]
        ])
    ]
];

$output = $view->setVariableData($seoData)->render('seo-page');
```

### Scenario 3: E-commerce Product Listing with Loops

**Product List Template (`products.html`):**

```html
[[inherit("theme")]]

[[hook_into('in_head')
    <title>[[v("store.name")]] - Products</title>
]]

[[hook_into('in_body')
    <div class="product-grid">
        [[if("v[products]")
            [[each("product in products")
                <div class="product-card">
                    <img src="[[v('product.image')]]" 
                         alt="[[string_htmlentities('v[product.name]')]]">
                    
                    <h3>[[v("product.name")]]</h3>
                    
                    <div class="price">
                        [[if("v[product.on_sale]")
                            <span class="original">$[[v("product.regular_price")]]</span>
                            <span class="sale">$[[v("product.sale_price")]]</span>
                        ]]
                        
                        [[if("!v[product.on_sale]")
                            <span>$[[v("product.regular_price")]]</span>
                        ]]
                    </div>
                    
                    <div class="meta">
                        <span class="sku">SKU: [[v("product.sku")]]</span>
                        
                        [[if("v[product.in_stock]")
                            <span class="stock in-stock">In Stock</span>
                        ]]
                        
                        [[if("!v[product.in_stock]")
                            <span class="stock out-of-stock">Out of Stock</span>
                        ]]
                    </div>
                    
                    [[if("v[product.tags]")
                        <div class="tags">
                            [[each("tag in product.tags")
                                <span class="tag">[[string_ucfirst('v[tag]')]]</span>
                            ]]
                        </div>
                    ]]
                    
                    <button data-product-id="[[v('product.id')]]">
                        Add to Cart
                    </button>
                    
                    [[if("v[_loop.first]")
                        <span class="badge">New Arrival</span>
                    ]]
                </div>
            ]]
        ]]
        
        [[if("!v[products]")
            <div class="empty-state">
                <p>No products found.</p>
            </div>
        ]]
    </div>
]]
```

**PHP Controller:**

```php
$data = [
    'store' => ['name' => 'My Store'],
    'products' => [
        [
            'id' => 1,
            'name' => 'Premium Headphones',
            'image' => '/images/headphones.jpg',
            'sku' => 'HP-001',
            'regular_price' => 299.99,
            'sale_price' => 249.99,
            'on_sale' => true,
            'in_stock' => true,
            'tags' => ['electronics', 'audio', 'premium']
        ],
        [
            'id' => 2,
            'name' => 'Wireless Mouse',
            'image' => '/images/mouse.jpg',
            'sku' => 'MS-042',
            'regular_price' => 49.99,
            'on_sale' => false,
            'in_stock' => false,
            'tags' => ['electronics', 'accessories']
        ],
    ]
];

$output = $view->setVariableData($data)->render('products');
```

### Scenario 4: Multi-Language Template System

**Base Internationalized Template (`i18n-page.html`):**

```html
[[inherit("theme")]]

[[hook_into('in_html_lang')[[v("locale.code")]]]]

[[hook_into('in_head')
    <title>[[v("i18n.page_title")]]</title>
    
    [[if("v[locale.alternates]")
        [[each("lang in locale.alternates")
            <link rel="alternate" hreflang="[[v('lang.code')]]" 
                  href="[[v('lang.url')]]">
        ]]
    ]]
]]

[[hook_into('in_body')
    <nav>
        [[each("item in navigation")
            <a href="[[v('item.url')]]">[[v("item.label")]]</a>
        ]]
    </nav>
    
    <main>
        <h1>[[v("i18n.welcome_message")]]</h1>
        
        [[if("v[user.name]")
            <p>[[v("i18n.greeting")]], [[v("user.name")]]!</p>
        ]]
        
        <p>[[v("i18n.content")]]</p>
        
        <div class="language-selector">
            <label>[[v("i18n.select_language")]]:</label>
            <select>
                [[each("lang in locale.available")
                    <option value="[[v('lang.code')]]" 
                            [[if("v[lang.code] === v[locale.code]")]]selected[[]]>
                        [[v("lang.name")]]
                    </option>
                ]]
            </select>
        </div>
    </main>
]]
```

**PHP Translation System:**

```php
class TranslationService
{
    private array $translations = [];
    
    public function loadTranslations(string $locale): array
    {
        // Load from database or file
        return [
            'en' => [
                'page_title' => 'Welcome to Our Site',
                'welcome_message' => 'Welcome',
                'greeting' => 'Hello',
                'content' => 'This is the main content',
                'select_language' => 'Select Language'
            ],
            'es' => [
                'page_title' => 'Bienvenido a Nuestro Sitio',
                'welcome_message' => 'Bienvenido',
                'greeting' => 'Hola',
                'content' => 'Este es el contenido principal',
                'select_language' => 'Seleccionar Idioma'
            ]
        ][$locale];
    }
}

$locale = 'en';
$translations = new TranslationService();

$data = [
    'locale' => [
        'code' => $locale,
        'alternates' => [
            ['code' => 'es', 'url' => 'https://example.com/es/page'],
            ['code' => 'fr', 'url' => 'https://example.com/fr/page'],
        ],
        'available' => [
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'es', 'name' => 'Español'],
            ['code' => 'fr', 'name' => 'Français'],
        ]
    ],
    'i18n' => $translations->loadTranslations($locale),
    'navigation' => [
        ['url' => '/', 'label' => 'Home'],
        ['url' => '/about', 'label' => 'About'],
        ['url' => '/contact', 'label' => 'Contact'],
    ],
    'user' => ['name' => 'John']
];

$output = $view->setVariableData($data)->render('i18n-page');
```

### Scenario 5: Dashboard with Nested Loops and Functions

**Dashboard Template (`dashboard.html`):**

```html
[[inherit("theme")]]

[[func("formatDate", "timestamp")
    [[string_substr("v[timestamp]", "0", "10")]]
]]

[[func("statusBadge", "status")
    [[if("v[status] === string[active]")
        <span class="badge badge-success">Active</span>
    ]]
    [[if("v[status] === string[pending]")
        <span class="badge badge-warning">Pending</span>
    ]]
    [[if("v[status] === string[inactive]")
        <span class="badge badge-danger">Inactive</span>
    ]]
]]

[[hook_into('in_body')
    <div class="dashboard">
        <h1>Dashboard - [[string_ucwords("v[user.name]")]]</h1>
        
        <div class="stats-grid">
            [[each("stat in stats")
                <div class="stat-card">
                    <h3>[[v("stat.label")]]</h3>
                    <div class="value">[[string_number_format("v[stat.value]", "0")]]</div>
                    
                    [[if("v[stat.change]")
                        <span class="change [[if('v[stat.change] > 0')]]positive[[]]">
                            [[v("stat.change")]]%
                        </span>
                    ]]
                </div>
            ]]
        </div>
        
        <div class="recent-orders">
            <h2>Recent Orders</h2>
            
            [[if("v[orders]")
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        [[each("order in orders")
                            <tr>
                                <td>[[v("order.id")]]</td>
                                <td>[[string_ucwords("v[order.customer]")]]</td>
                                <td>
                                    [[each("item in order.items")
                                        <div>
                                            [[v("item.name")]] 
                                            (x[[v("item.quantity")]])
                                            [[if("!v[_loop.last]")]], [[]]
                                        </div>
                                    ]]
                                </td>
                                <td>$[[string_number_format("v[order.total]", "2")]]</td>
                                <td>[[statusBadge("order.status")]]</td>
                                <td>[[formatDate("order.created_at")]]</td>
                            </tr>
                        ]]
                    </tbody>
                </table>
            ]]
        </div>
    </div>
]]
```

**PHP Controller:**

```php
$data = [
    'user' => ['name' => 'john doe'],
    'stats' => [
        ['label' => 'Total Sales', 'value' => 45230, 'change' => 12.5],
        ['label' => 'Orders', 'value' => 892, 'change' => -3.2],
        ['label' => 'Customers', 'value' => 1547, 'change' => 8.1],
    ],
    'orders' => [
        [
            'id' => 1001,
            'customer' => 'jane smith',
            'items' => [
                ['name' => 'Product A', 'quantity' => 2],
                ['name' => 'Product B', 'quantity' => 1],
            ],
            'total' => 149.98,
            'status' => 'active',
            'created_at' => '2025-12-09 10:30:00'
        ],
        [
            'id' => 1002,
            'customer' => 'bob johnson',
            'items' => [
                ['name' => 'Product C', 'quantity' => 5],
            ],
            'total' => 299.95,
            'status' => 'pending',
            'created_at' => '2025-12-09 09:15:00'
        ],
    ]
];

$output = $view->setVariableData($data)->render('dashboard');
```

## Hook System Deep Dive

### Hook Types

1. **`add_hook(name)`** - Creates a placeholder for future content injection
2. **`hook_into(name)`** - Injects content into a previously defined hook
3. **`reset_hook(name)`** - Clears all content from a specific hook
4. **`reset_all_hooks()`** - Clears content from all hooks
5. **`on_hook_into_event()`** - Triggers event-based hook system

### Hook Positioning

```html
[[add_hook('header')Default Content]]

[[-- Multiple hook_into calls append content --]]
[[hook_into('header')
    <link rel="stylesheet" href="/style.css">
]]

[[hook_into('header')
    <script src="/app.js"></script>
]]

[[-- Result: Default Content + CSS + JS --]]
```

### Event-Driven Hooks (PHP)

The template system integrates with the Tonics Event System to allow dynamic hook registration. Here's the complete pattern:

**Step 1: Create the Event Class**

```php
namespace App\Events;

use Devsrealm\TonicsEventSystem\Interfaces\EventInterface;
use Devsrealm\TonicsTemplateSystem\TonicsView;

class OnHookIntoTemplate implements EventInterface
{
    private array $hookInto = [];
    private TonicsView $tonicsView;

    public function __construct(TonicsView $tonicsView)
    {
        $this->tonicsView = $tonicsView;
    }

    public function event(): static
    {
        return $this;
    }

    /**
     * Hook into a template placeholder
     * 
     * @param string $name Hook name to inject content into
     * @param callable $handler Function that returns content to inject
     * @param bool $fireInstantly If true, executes immediately if hook exists
     */
    public function hookInto(string $name, callable $handler, bool $fireInstantly = false): static
    {
        if ($fireInstantly) {
            // Check if hook exists and fire immediately
            $storage = $this->getTonicsView()->getModeStorage('add_hook');
            if (isset($storage[$name])) {
                $handler($this->getTonicsView());
            }
        } else {
            // Register for later execution
            $this->hookInto[] = [
                'hook_into' => $name,
                'handler' => function() use ($handler) {
                    return $handler($this->getTonicsView());
                },
            ];
        }
        return $this;
    }

    public function getHookInto(): array
    {
        return $this->hookInto;
    }

    public function getTonicsView(): TonicsView
    {
        return $this->tonicsView;
    }
}
```

**Step 2: Register Event Handlers**

```php
namespace App;

class Events
{
    public static function AppEvents(): array
    {
        return [
            OnHookIntoTemplate::class => [
                AnalyticsHandler::class,
                SeoHandler::class,
                ThemeCustomizer::class,
            ],
        ];
    }
}
```

**Step 3: Create Event Handlers**

```php
namespace App\Handlers;

use App\Events\OnHookIntoTemplate;

class AnalyticsHandler
{
    public function handle(OnHookIntoTemplate $event): void
    {
        // Add analytics script before closing body
        $event->hookInto('before_closing_body', function($view) {
            $trackingId = env('ANALYTICS_ID');
            return <<<HTML
                <script>
                    // Google Analytics or similar
                    window.ga = window.ga || function() {
                        (ga.q = ga.q || []).push(arguments)
                    };
                    ga('create', '{$trackingId}', 'auto');
                    ga('send', 'pageview');
                </script>
                HTML;
        });
    }
}

class SeoHandler
{
    public function handle(OnHookIntoTemplate $event): void
    {
        // Add SEO meta tags
        $event->hookInto('in_head', function($view) {
            $data = $view->getVariableData();
            $siteName = $data['site_name'] ?? 'My Site';
            
            return <<<HTML
                <meta property="og:site_name" content="{$siteName}">
                <meta name="generator" content="MyApp v1.0">
                HTML;
        });
    }
}

class ThemeCustomizer
{
    public function handle(OnHookIntoTemplate $event): void
    {
        // Add custom CSS based on user preferences
        $event->hookInto('in_head_inline_styles', function($view) {
            $data = $view->getVariableData();
            $primaryColor = $data['theme']['primary_color'] ?? '#007bff';
            
            return <<<CSS
                :root {
                    --primary-color: {$primaryColor};
                }
                .btn-primary {
                    background-color: var(--primary-color);
                }
                CSS;
        });
        
        // Add conditional premium badge
        $event->hookInto('in_body', function($view) {
            $data = $view->getVariableData();
            if (isset($data['user']) && $data['user']['is_premium']) {
                return '<div class="premium-badge">Premium User</div>';
            }
            return '';
        });
    }
}
```

**Step 4: Wire Events with Template System**

```php
use Devsrealm\TonicsEventSystem\EventDispatcher;
use Devsrealm\TonicsEventSystem\EventQueue;

// Collect all events from your application
$events = [
    OnHookIntoTemplate::class => [
        AnalyticsHandler::class,
        SeoHandler::class,
        ThemeCustomizer::class,
    ],
    // ... other events
];

// Wire the event dispatcher
$eventQueue = new EventQueue();
$eventDispatcher = new EventDispatcher(
    $eventQueue->addMultipleEventsAndHandlers($events)
);

// Make it globally accessible
$GLOBALS['__EVENT_DISPATCHER__'] = $eventDispatcher;

// Helper function
function event(): EventDispatcher {
    return $GLOBALS['__EVENT_DISPATCHER__'];
}
```

**Step 5: Use in Templates**

In your base template, trigger the event system:

```html
<!DOCTYPE html>
[[on_hook_into_event()]]

<html>
<head>
    [[add_hook('in_head')]]
    <style>
        [[add_hook('in_head_inline_styles')]]
    </style>
</head>
<body>
    [[add_hook('in_body')]]
    
    [[add_hook('before_closing_body')]]
</body>
</html>
```

The `[[on_hook_into_event()]]` triggers all registered hook handlers, which then inject content into the appropriate `add_hook` placeholders.

## Troubleshooting

### Common Issues

**Problem: Template not found**
```
TonicsTemplateLoaderError: `template.html` Does Not Exist
```
Solution: Ensure the template directory is registered and the file exists
```php
// Check if directory exists
if (is_dir($templatePath)) {
    $templateLoader->resolveTemplateFiles($templatePath, 'namespace');
}

// Debug: Check loaded templates
print_r($templateLoader->getTemplates());
```

**Problem: Hook content not appearing**
```html
[[add_hook('my_hook')]]  <!-- Nothing shows up -->
```
Solution: Make sure `[[on_hook_into_event()]]` is called in your base template before hooks are used:
```html
[[on_hook_into_event()]]  <!-- Add this at the top -->
[[add_hook('my_hook')]]
```

**Problem: Variables not rendering**
```html
[[v("user.name")]]  <!-- Shows nothing -->
```
Solution: Check that data is passed correctly
```php
// Make sure data structure matches template
$data = [
    'user' => [
        'name' => 'John'  // This should work with [[v("user.name")]]
    ]
];
view('template', $data);
```

**Problem: String functions not working**
```
Error: Mode handler 'string_trim' not registered
```
Solution: Register string function mode handlers
```php
$view->addModeHandler('string_trim', StringFunctions::class);
// Or register all at once - see Basic Setup section
```

**Problem: Templates work in development but fail in production**

Solution: Clear template cache
```php
// If using APCu caching
if (extension_loaded('apcu')) {
    apcu_clear_cache();
    // Or through the interface
    $cache = new TonicsTemplateApcuCache();
    $cache->clear();
}

// If using Memcached caching
if (extension_loaded('memcached')) {
    $cache = new TonicsTemplateMemcached();
    $cache->clear();
}

// If using File cache
$cache = new TonicsTemplateFileCache('/path/to/cache', 'SERIALIZE');
$cache->clear();

// Or restart cache service
// For APCu: sudo systemctl restart php-fpm (or apache2)
// For Memcached: sudo systemctl restart memcached
```

**Problem: Performance issues with many templates**

Solution: Enable caching and use cache prefix
```php
if (extension_loaded('memcached')) {
    $settings['templateCache'] = new TonicsTemplateMemcached();
}
$view = new TonicsView($settings);
$view->setCachePrefix('MyApp_v1_'); // Version your cache
```

### Debugging Templates

**Enable debug mode:**
```php
$view->setDebug(true);
```

**Get tokenized content:**
```php
$tokens = $view->render('template', TonicsView::RENDER_TOKENIZE_ONLY);
var_dump($tokens);
```

**Check mode storage (for hooks):**
```php
$hookStorage = $view->getModeStorage('add_hook');
print_r($hookStorage);
```

**Validate template loader:**
```php
$templates = $view->getTemplateLoader()->getTemplates();
foreach ($templates as $name => $path) {
    echo "$name => $path\n";
}
```

## Performance Tips

1. **Use Caching in Production**
   ```php
   // APCu is fastest for single server (recommended)
   if (env('APP_ENV') === 'production' && extension_loaded('apcu') && apcu_enabled()) {
       $settings['templateCache'] = new TonicsTemplateApcuCache();
   }
   // Or Memcached for distributed systems
   elseif (env('APP_ENV') === 'production' && extension_loaded('memcached')) {
       $settings['templateCache'] = new TonicsTemplateMemcached();
   }
   ```

2. **Cache the Entire Application Graph**
   ```php
   // Cache router, events, and templates together
   $cache->set('app_graph', [
       'router' => $router,
       'eventDispatcher' => $eventDispatcher,
       'tonicsView' => $tonicsView,
   ]);
   ```

3. **Use Appropriate Render Mode**
    - Use `RENDER_CONCATENATE` for framework integration
    - Use `RENDER_CONCATENATE_AND_OUTPUT` only for simple scripts
    - Avoid `RENDER_TOKENIZE_ONLY` in production

4. **Minimize Hook Handlers**
    - Only register hooks that are actually needed
    - Use `$fireInstantly` parameter when appropriate
    - Avoid heavy computation in hook handlers

5. **Template Organization**
    - Use namespaces to separate concerns
    - Keep templates focused and small
    - Leverage inheritance for common layouts

6. **String Functions**
    - Only register functions you actually use
    - Use raw output `[[_v()]]` when appropriate to skip escaping

## License

This package follows the same license as the TonicsCMS framework.

## Contributing

Contributions are welcome! Please ensure your code follows PSR standards and includes appropriate tests.

