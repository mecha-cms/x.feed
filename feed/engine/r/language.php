<?php

Language::set([
    'feed' => ['Feed', 'Feed', 'Feeds'],
    'feed-count' => function(int $i) {
        return $i . ' Feed' . ($i === 1 ? "" : 's');
    },
    'rss' => ['RSS', 'RSS', 'RSSs'],
    'rss-title' => 'RSS | %s',
    'json' => ['JSON', 'JSON', 'JSONs'],
    'sitemap' => ['Sitemap', 'Sitemap', 'Stemaps'],
    'sitemap-title' => 'Sitemap | %s',
    'site-map' => ['Site Maps', 'Site Map', 'Site Maps'],
    'site-map-title' => 'Site Map | %s'
]);