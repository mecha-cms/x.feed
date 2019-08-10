<?php namespace _\lot\x;

function feed($content) {
    global $config, $language, $url;
    $state = \state('feed');
    return \str_replace('</head>', '<link href="' . $url . '/' . $state['/']['sitemap'] . '" rel="sitemap" type="application/xml" title="' . $language->sitemapTitle(\w($config->title), true) . '"><link href="' . $url->clean . '/' . $state['/']['rss'] . '" rel="alternate" type="application/rss+xml" title="' . $language->rssTitle(\w($config->title), true) . '"></head>', $content);
}

// Insert some HTML `<link>` that maps to the feed resource
if (!\has(\array_values(\state('feed')['/'] ?? []), (string) \Path::B(\trim((string) $url->path, '/')))) {
    // Make sure to run the hook before `_\minify`
    \Hook::set('content', __NAMESPACE__ . "\\feed", 1.9);
}