<?php namespace _;

function feed($content) {
    global $config, $language, $url;
    $state = \Extend::state('feed');
    return \str_replace('</head>', '<link href="' . $url . '/' . $state['path']['sitemap'] . '" rel="sitemap" type="application/xml" title="' . $language->sitemapTitle(\To::text($config->title), true) . '"><link href="' . $url->clean . '/' . $state['path']['rss'] . '" rel="alternate" type="application/rss+xml" title="' . $language->rssTitle(\To::text($config->title), true) . '"></head>', $content);
}

// Insert some HTML `<link>` that maps to the feed resource
if (!\has(\array_values(\Extend::state('feed', 'path') ?? []), \Path::B($url->path . "") . "")) {
    // Make sure to run the hook before `_\minify`
    \Hook::set('content', __NAMESPACE__ . "\\feed", 1.9);
}