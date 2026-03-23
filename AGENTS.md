# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A lightweight cache warming toolset with two implementations:
- **PHP**: Recursive HTML crawler that follows links up to a configurable depth
- **Bash**: Sitemap-based warmer that fetches URLs from robots.txt

## Commands

### PHP Script

**Check syntax:**
```bash
php -l cache-warmer.php
```

**Run with basic options:**
```bash
php cache-warmer.php -u https://example.com/
```

**Run with full options:**
```bash
php cache-warmer.php -u https://example.com/ -l 3 -s 1 -e 'customer,checkout,wishlist'
```

**Display help:**
```bash
php cache-warmer.php -h
```

### Bash Script

**Make executable:**
```bash
chmod +x cache-warmer.sh
```

**Run with exclusions:**
```bash
./cache-warmer.sh https://example.com/ -e '/customer' -e '/checkout'
```

## Architecture

### PHP Implementation (cache-warmer.php)

- **Single-class design**: All logic in `cacheWarmer` class
- **Core flow**:
  1. Argument parsing via `getopt()` in `checkArguments()`
  2. Recursive traversal starting from `execute()` → `visitUrl()`
  3. Link extraction with regex in `linksExtractor()`
  4. URL filtering/normalization in `filterLinks()` and `relativeToAbsolute()`
- **State management**: Visited URLs tracked in `$links` array to prevent re-crawling
- **Synchronous execution**: Sequential HTTP requests using `file_get_contents()`
- **No external dependencies**: Pure PHP with no Composer packages

### Bash Implementation (cache-warmer.sh)

- **Pipeline-based**: Uses chained bash commands (curl → grep → sed → xargs)
- **Two-stage sitemap discovery**:
  1. Fetch sitemaps from robots.txt
  2. Parse nested sitemap indexes to find child sitemaps
- **URL extraction**: Regex patterns extract `<loc>` tags from XML
- **Exclusion support**: Perl-compatible regex patterns via `grep -vP`
- **User agent**: Custom UA string identifying the tool and repo

## Key Technical Details

### PHP Specifics

- **Requirements**: PHP >= 8.1 (uses typed properties)
- **URL normalization**: Strips query strings and fragments in `removeQueryFragment()`
- **Recursion control**: Level counter prevents infinite loops
- **Argument handling**: Recent improvements handle duplicate flags; only last value is used
- **Performance consideration**: Synchronous nature means single-threaded execution

### Bash Specifics

- **Dependencies**: curl, grep (with Perl regex), sed, xargs, wc
- **Sitemap parsing**: Handles CDATA sections and XML namespaces
- **URL deduplication**: Uses `sort | uniq` to remove duplicates
- **Request delay**: Fixed 1-second sleep between requests
- **Error handling**: Redirects stderr to stdout in xargs pipeline

## File Structure

```
cache-warmer.php    # PHP recursive crawler
cache-warmer.sh     # Bash sitemap-based warmer
readme.md           # User-facing documentation
GEMINI.md           # Gemini-specific project context
var/                # Working directory (gitignored)
```

## Development Notes

- Both tools are standalone scripts (no build process)
- PHP coding style: camelCase methods, typed properties
- When modifying PHP: Ensure PHP 8.1+ compatibility
- When modifying Bash: Test with different sitemap structures (nested indexes, CDATA)
- The tools are intentionally minimal with no framework dependencies
