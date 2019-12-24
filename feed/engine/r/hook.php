<?php namespace _\lot\x;

function feed($content) {
    global $state, $url;
    return \str_replace('</head>', '<link href="' . $url->clean . '/feed.xml" rel="alternate" type="application/rss+xml" title="' . \i('RSS') . ' | ' . \w($state->title) . '"></head>', $content);
}

// Insert some HTML `<link>` that maps to the feed resource
if (!\has(['feed.json', 'feed.xml'], \basename($url->path))) {
    // Make sure to run the hook before `_\lot\x\minify`
    \Hook::set('content', __NAMESPACE__ . "\\feed", 1.9);
}
