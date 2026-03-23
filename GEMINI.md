# GEMINI.md - Project Context for Cache Warmer

This project provides tools designed to recursively crawl a website to trigger server-side cache generation (e.g., for Magento, WordPress, or custom applications).

## Project Overview
- **Purpose**: A command-line tool for cache warming.
- **Main Technologies**: PHP (Pure script) and Bash.
- **Core Logic**:
    - `cache-warmer.php`: Handles recursive crawling, link extraction via regex, and filtering.
    - `cache-warmer.sh`: An alternative Bash-based implementation that uses sitemaps to find URLs.

## Technical Architecture
- **PHP Implementation**: The logic is contained within `cache-warmer.php`. It uses `file_get_contents` for HTTP requests and `preg_match_all` for link extraction.
- **Bash Implementation**: `cache-warmer.sh` fetches sitemaps from `robots.txt`, extracts URLs, and warms them using `curl`.
- **Recursion (PHP)**: Employs a level-based recursion to prevent infinite loops and limit depth.
- **Filtering**: Both tools provide ways to exclude specific URL patterns.

## Development Conventions & Standards
- **PHP Version**: Requires PHP >= 8.1.
- **Coding Style (PHP)**:
    - Uses typed properties (e.g., `protected string $startUrl`).
    - Methods use camelCase (e.g., `checkArguments`, `visitUrl`).
    - Minimalist approach: No composer, no external libraries.
- **Validation**: When modifying, ensure compatibility with PHP 8.1+.

## Building and Running
- **PHP Execution**: `php cache-warmer.php -u <url> [options]`
- **Bash Execution**: `./cache-warmer.sh <website_url> [-e <exclude_pattern>]`
- **Linting**: `php -l cache-warmer.php`

## Key Files
- `cache-warmer.php`: The main PHP executable script.
- `cache-warmer.sh`: A Bash script for sitemap-based warming.
- `readme.md`: Usage documentation and options reference.
- `.gitignore`: Ignores `.idea/` and `var/` directories.

## Known Constraints
- **PHP Argument Parsing**: Uses `getopt()`. Recent updates have improved handling of duplicate flags.
- **PHP Performance**: Crawling is sequential and synchronous.
- **Bash Dependencies**: Requires `curl`, `grep`, `sed`, `xargs`, and `wc`.
