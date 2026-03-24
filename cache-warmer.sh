#!/bin/bash
website=${1}
shift
excludes=()
list_only=false
dry_run=false

while [[ $# -gt 0 ]]; do
  case "$1" in
    --exclude|-e)
      excludes+=("$2")
      shift 2
      ;;
    --list)
      list_only=true
      shift
      ;;
    --dryrun)
      dry_run=true
      shift
      ;;
    *)
      shift
      ;;
  esac
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
c_sitemaps=$(echo "$sitemaps" | grep . | wc -l)
echo "Found $c_sitemaps sitemaps" >&2

urls=$(echo "$sitemaps" | xargs curl -s  | sed 's|</loc>|</loc>\n|g' | sed 's/<!\[CDATA\[//g; s/\]\]>//g' | grep -oP '(?<=<loc>).*(?=</loc>)' | sed 's/ //g' | grep -vP 'xml$'  | sort | uniq  )


# Apply exclusions
if [[ ${#excludes[@]} -gt 0 ]]; then
  urls=$(exclude_filter "$urls")
fi

c_urls=$(echo "$urls" | grep . | wc -l)
echo "Found $c_urls urls" >&2

if [[ "$list_only" == true ]]; then
  echo "$urls"
  exit 0
fi

if [[ "$dry_run" == true ]]; then
  exit 0
fi

echo "$urls" | xargs -I{} echo "curl -s -A '${USER_AGENT}' {} >/dev/null ; echo {} ; sleep 1" 2>&1 | bash