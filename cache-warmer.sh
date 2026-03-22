#!/bin/bash

website=${1}

sitemaps1=$(curl -s ${website}/robots.txt | grep xml | grep -ioP '(?<=Sitemap:).*' | sed 's/ //g' )
sitemaps2=$(echo "$sitemaps1" | xargs curl -s | grep -oP '(?<=<loc>).*(?=</loc>)' | grep -P 'xml$' | sed 's/ //g'  )
sitemaps=$(printf '%s\n' $sitemaps1 $sitemaps2)
urls=$(echo "$sitemaps" | xargs curl -s  | grep -oP '(?<=<loc>).*(?=</loc>)' | grep -vP 'xml$' | sed 's/ //g' )
echo "$urls" | xargs -I{} echo "sleep 1; curl -s {} >/dev/null " 2>&1 | bash