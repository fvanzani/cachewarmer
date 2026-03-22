# Simple PHP Cache Warmer

A simple PHP command line cache warmer that crawls a website to trigger cache generation.

## Features

- Recursive crawling with configurable depth.
- Exclude specific URL patterns.
- Configurable delay between requests to avoid overloading the server.
- Reports response times for each URL.
- No dependencies, single-file script.

## Prerequisites

- PHP >= 8.1

## Install 

Just download the file `cacheWarmer.php`.

## Usage

Show syntax and available options: 

```bash
php cacheWarmer.php -h
```

### Options

| Short | Long | Description | Default |
|-------|------|-------------|---------|
| `-u` | `--url` | The starting URL (required). | - |
| `-l` | `--level` | Maximum recursion level for crawling. | `2` |
| `-e` | `--excludes` | Comma-separated list of strings to exclude from URLs. | - |
| `-s` | `--sleep` | Seconds to wait between each request. | `0` |
| `-h` | `--help` | Show this help message. | - |

### Examples

**Basic usage:**
```bash
php cacheWarmer.php -u https://www.mymagentoshop.com/
```

**Advanced usage:**
Recursive crawl up to level 3, waiting 1 second between requests, and excluding common dynamic pages:
```bash
php cacheWarmer.php -u https://www.mymagentoshop.com/ -s 1 -l 3 -e 'uenc,customer,checkout,wishlist'
```

## How it works

The script starts at the provided URL, extracts all links, and recursively visits them up to the specified level. It only follows links that start with the same base URL to avoid leaving the site. Each visit triggers the server to process the request and generate the cache.