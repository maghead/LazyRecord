#!/bin/bash
onion -d compile \
    --lib src \
    --lib vendor/pear \
    --classloader \
    --bootstrap scripts/lazy-embed.php \
    --executable \
    --output lazy.phar
mv lazy.phar lazy
chmod +x lazy
