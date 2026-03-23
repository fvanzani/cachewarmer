# Simple PHP & Bash Cache Warmer

A simple set of tools to crawl a website or its sitemaps to trigger cache generation.

## Features

- **PHP Implementation**: Recursive crawling with configurable depth.
- **Bash Implementation**: Sitemap-based warming using `robots.txt`.
- Exclude specific URL patterns.
- Configurable delay between requests.
- Reports response times (PHP).

## Prerequisites

- PHP >= 8.1
- Bash (for `cache-warmer.sh`) with `curl`, `grep`, `sed`, `xargs`.

## Install 

Just download the files `cache-warmer.php` and/or `cache-warmer.sh`.

## Usage

### PHP Script

Show syntax and available options: 

```bash
php cache-warmer.php -h
```

**Basic usage:**
```bash
php cache-warmer.php -u https://www.mymagentoshop.com/
```

**Advanced usage:**
Recursive crawl up to level 3, waiting 1 second between requests, and excluding common dynamic pages:
```bash
php cache-warmer.php -u https://www.mymagentoshop.com/ -s 1 -l 3 -e 'uenc,customer,checkout,wishlist'
```

### Bash Script

The Bash script fetches URLs from sitemaps and warms them.

```bash
chmod +x cache-warmer.sh
./cache-warmer.sh https://www.mymagentoshop.com/ -e '/customer' -e '/checkout'
```


## How it works

The script starts at the provided URL, extracts all links, and recursively visits them up to the specified level. It only follows links that start with the same base URL to avoid leaving the site. Each visit triggers the server to process the request and generate the cache.