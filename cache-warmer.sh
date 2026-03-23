#!/bin/bash
website=${1}
shift
excludes=()
while [[ "$1" == "--exclude" || "$1" == "-e" ]]; do
  excludes+=("$2")
  shift 2
done

USER_AGENT="Mozilla/5.0 (compatible; CacheWarmer/1.0; +https://github.com/fvanzani/cachewarmer)"

# Build exclude grep filter
exclude_filter() {
  local input="$1"
  for pattern in "${excludes[@]}"; do
    input=$(echo "$input" | grep -vP "$pattern")
  done
  echo "$input"
}

sitemaps1=$(curl -s ${website}/robots.txt | grep xml | grep -ioP '(?<=^Sitemap:).*' | sed 's/ //g' )
sitemaps2=$(echo "$sitemaps1" | xargs curl -s | sed 's|</loc>|</loc>\n|g' | sed 's/<!\[CDATA\[//g; s/\]\]>//g' | grep -oP '(?<=<loc>).*(?=</loc>)' | sed 's/ //g' | grep -P 'xml$'   )
sitemaps=$(printf '%s\n' "$sitemaps1" "$sitemaps2" )
c_sitemaps=$(echo "$sitemaps" | wc -l)
echo "Found $c_sitemaps sitemaps"

urls=$(echo "$sitemaps" | xargs curl -s  | sed 's|</loc>|</loc>\n|g' | sed 's/<!\[CDATA\[//g; s/\]\]>//g' | grep -oP '(?<=<loc>).*(?=</loc>)' | sed 's/ //g' | grep -vP 'xml$'  | sort | uniq  )


# Apply exclusions
if [[ ${#excludes[@]} -gt 0 ]]; then
  urls=$(exclude_filter "$urls")
fi

c_urls=$(echo "$urls" | wc -l)
echo "Found $c_urls urls"

echo "$urls" | xargs -I{} echo "curl -s -A '${USER_AGENT}' {} >/dev/null ; echo {} ; sleep 1" 2>&1 | bash