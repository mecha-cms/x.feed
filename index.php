<?php namespace x\feed;

// Insert some HTML `<link>` that maps to the feed resource
function content($content) {
    \extract(\lot(), \EXTR_SKIP);
    $json = '<link href="' . $url->current(false, false) . '/feed.json" rel="alternate" title="' . \i('RSS') . ' | ' . \w($state->title) . '" type="application/feed+json">';
    $xml = '<link href="' . $url->current(false, false) . '/feed.xml" rel="alternate" title="' . \i('RSS') . ' | ' . \w($state->title) . '" type="application/rss+xml">';
    return \strtr($content ?? "", ['</head>' => $json . $xml . '</head>']);
}

function route($content, $path) {
    if (null !== $content) {
        return $content;
    }
    \extract(\lot(), \EXTR_SKIP);
    $x_image = isset($state->x->image);
    $x_tag = isset($state->x->tag);
    $x_user = isset($state->x->user);
    $chunk = $_GET['chunk'] ?? 25;
    $deep = $_GET['deep'] ?? 0;
    $fire = $_GET['fire'] ?? null;
    $part = $_GET['part'] ?? 1;
    $query = $_GET['query'] ?? null;
    $sort = \array_replace([-1, 'time'], (array) ($_GET['sort'] ?? []));
    // Validate function name
    if ($fire && !\preg_match('/^[a-z_$][\w$]*(\.[a-z_$][\w$]*)*$/i', $fire)) {
        \status(403);
        return "";
    }
    $n = \basename($path ?? "");
    $path = \trim(\dirname($path ?? ""), '/');
    $route = \trim($state->route ?? "", '/');
    $folder = \LOT . \D . 'page' . \D . ($path ?: $route);
    $page = new \Page($exist = \exist([
        $folder . '.archive',
        $folder . '.page'
    ], 1) ?: null);
    $status = 200;
    // <https://www.jsonfeed.org>
    if ('feed.json' === $n) {
        $lot = [
            'description' => ($page->description ?? $state->description) ?: null,
            'feed_url' => \Hook::fire('link', [$url->current([
                'part' => $part,
                'sort' => $sort
            ], false)]),
            'home_page_url' => \Hook::fire('link', [(string) $url]),
            'items' => [],
            'title' => ($exist ? $page->title : \i('Error')) . ' | ' . $state->title,
            'version' => 'https://jsonfeed.org/version/1.1'
        ];
        if ($page_author = $page->author) {
            if ($x_user && $page_author instanceof \User) {
                $page_author_avatar = $page_author->avatar(512, 512);
                $page_author_link = $page_author->link;
                $page_author_url = $page_author->url;
                $author = ['name' => (string) $page_author];
                if ($page_author_avatar) {
                    $author['avatar'] = \Hook::fire('link', [$page_author_avatar]);
                }
                if ($page_author_link || $page_author_url) {
                    $author['url'] = \Hook::fire('link', [$page_author_link ?? $page_author_url]);
                }
                \ksort($author);
                $lot['author'] = $lot['authors'][0] = $author;
            } else if (\is_string($page_author)) {
                $lot['author'] = $lot['authors'][0] = ['name' => $page_author];
            }
        }
        if (\is_file(\PATH . \D . 'favicon.ico')) {
            $lot['favicon'] = \Hook::fire('link', [$url . '/favicon.ico']);
        }
        if ($image = $page->image(512, 512) ?? $page->avatar(512, 512)) {
            $lot['icon'] = \Hook::fire('link', [$image]);
        }
        if ($language = $state->language) {
            $lot['language'] = $language;
        }
        $pages = [];
        foreach ($query ? \k($folder, 'page', $deep, \preg_split('/\s+/', $query), true) : \g($folder, 'page', $deep) as $k => $v) {
            $p = new \Page($k);
            $pages[$k] = [$sort[1] => (string) ($p->{$sort[1]} ?? 0)];
        }
        $pages = (new \Anemone($pages))->sort($sort, true)->chunk($chunk, -1, true)->get();
        if (!empty($pages[$part])) {
            $lot['next_url'] = \Hook::fire('link', [$url->current([
                'part' => $part + 1,
                'sort' => $sort
            ], false)]);
        }
        if (!empty($pages[$part - 1])) {
            foreach (\array_keys($pages[$part - 1]) as $k => $v) {
                $page = new \Page($v);
                $item = [];
                // if ($page_content = $page->content) {
                //     $item['content_html'] = $page_content;
                //     $item['content_text'] = \trim(\strip_tags($page_content));
                // }
                if ($page_author = $page->author) {
                    if ($x_user && $page_author instanceof \User) {
                        $page_author_avatar = $page_author->avatar(512, 512);
                        $page_author_link = $page_author->link;
                        $page_author_url = $page_author->url;
                        $author = ['name' => (string) $page_author];
                        if ($page_author_avatar) {
                            $author['avatar'] = \Hook::fire('link', [$page_author_avatar]);
                        }
                        if ($page_author_link || $page_author_url) {
                            $author['url'] = \Hook::fire('link', [$page_author_link ?? $page_author_url]);
                        }
                        \ksort($author);
                    } else if (\is_string($page_author)) {
                        $author = ['name' => $page_author];
                    }
                    $item['authors'][0] = $author;
                }
                if (\is_file($page_path = $page->path)) {
                    $item['date_modified'] = \date(\DATE_RFC3339, \filemtime($page_path));
                }
                if ($page_time = $page->time(\DATE_RFC3339)) {
                    $item['date_published'] = $page_time;
                }
                if ($page_link = $page->link) {
                    $item['external_url'] = \Hook::fire('link', [$page_link]);
                }
                $item['id'] = (string) $page->id;
                if ($page_image = $page->image) {
                    $item['image'] = $page_image;
                }
                if ($page_description = $page->description) {
                    if ("" !== ($page_description = \trim(\strip_tags($page_description)))) {
                        $item['summary'] = $page_description;
                    }
                }
                if ($page_language = $page->language) {
                    $item['language'] = $page_language;
                }
                if ($x_tag && $page_tags = $page->tags) {
                    if ($page_tags->count) {
                        $item['tags'] = [];
                        foreach ($page_tags as $page_tag) {
                            $page_tag_title = $page_tag->title;
                            if ("" !== ($page_tag_title = \trim(\strip_tags($page_tag_title)))) {
                                $item['tags'][] = $page_tag_title;
                            }
                        }
                    }
                }
                if ($page_title = $page->title) {
                    if ("" !== ($page_title = \trim(\strip_tags($page_title)))) {
                        $item['title'] = $page_title;
                    }
                }
                if ($page_url = $page->url) {
                    $item['url'] = \Hook::fire('link', [$page_url]);
                }
                \ksort($item);
                $lot['items'][] = $item;
            }
        } else if ($exist) {
            $item = [];
            // if ($page_content = $page->content) {
            //     $item['content_html'] = $page_content;
            //     $item['content_text'] = \trim(\strip_tags($page_content));
            // }
            if ($page_author = $page->author) {
                if ($x_user && $page_author instanceof \User) {
                    $page_author_avatar = $page_author->avatar(512, 512);
                    $page_author_link = $page_author->link;
                    $page_author_url = $page_author->url;
                    $author = ['name' => (string) $page_author];
                    if ($page_author_avatar) {
                        $author['avatar'] = \Hook::fire('link', [$page_author_avatar]);
                    }
                    if ($page_author_link || $page_author_url) {
                        $author['url'] = \Hook::fire('link', [$page_author_link ?? $page_author_url]);
                    }
                    \ksort($author);
                } else if (\is_string($page_author)) {
                    $author = ['name' => $page_author];
                }
                $item['authors'][0] = $author;
            }
            if (\is_file($page_path = $page->path)) {
                $item['date_modified'] = \date(\DATE_RFC3339, \filemtime($page_path));
            }
            if ($page_time = $page->time(\DATE_RFC3339)) {
                $item['date_published'] = $page_time;
            }
            if ($page_link = $page->link) {
                $item['external_url'] = \Hook::fire('link', [$page_link]);
            }
            $item['id'] = (string) $page->id;
            if ($page_image = $page->image) {
                $item['image'] = $page_image;
            }
            if ($page_description = $page->description) {
                if ("" !== ($page_description = \trim(\strip_tags($page_description)))) {
                    $item['summary'] = $page_description;
                }
            }
            if ($page_language = $page->language) {
                $item['language'] = $page_language;
            }
            if ($x_tag && $page_tags = $page->tags) {
                if ($page_tags->count) {
                    $item['tags'] = [];
                    foreach ($page_tags as $page_tag) {
                        $page_tag_title = $page_tag->title;
                        if ("" !== ($page_tag_title = \trim(\strip_tags($page_tag_title)))) {
                            $item['tags'][] = $page_tag_title;
                        }
                    }
                }
            }
            if ($page_title = $page->title) {
                if ("" !== ($page_title = \trim(\strip_tags($page_title)))) {
                    $item['title'] = $page_title;
                }
            }
            if ($page_url = $page->url) {
                $item['url'] = \Hook::fire('link', [$page_url]);
            }
            \ksort($item);
            $lot['items'][] = $item;
        } else {
            $status = 404;
        }
        $age = 60 * 60 * 24; // Cache for a day
        $content = \To::JSON(\Hook::fire('y.feed', [$lot], $page));
        \status($status, $exist ? [
            'cache-control' => 'max-age=' . $age . ', private',
            'expires' => \gmdate('D, d M Y H:i:s', $age + $_SERVER['REQUEST_TIME']) . ' GMT',
            'pragma' => 'private'
        ] : [
            'cache-control' => 'max-age=0, must-revalidate, no-cache, no-store',
            'expires' => '0',
            'pragma' => 'no-cache'
        ]);
        \type('application/' . ($fire ? 'javascript' : 'feed+json'));
        return ($fire ? $fire . '(' : "") . $content . ($fire ? ');' : "");
    }
    // <https://validator.w3.org/feed/docs/rss2.html>
    if ('feed.xml' === $n) {
        $lot = [
            0 => 'rss',
            1 => [
                ['channel', [
                    ['atom:link', false, [
                        'href' => \Hook::fire('link', [$url->current([
                            'part' => $part,
                            'sort' => $sort
                        ], false)]),
                        'rel' => 'self'
                    ]],
                    ['description', '<![CDATA[' . ($page->description ?? $state->description) . ']]>', []],
                    ['generator', '<![CDATA[Mecha ' . \VERSION . ']]>', []],
                    ['language', $state->language ?? 'en', []],
                    ['lastBuildDate', \date('r', $_SERVER['REQUEST_TIME']), []],
                    ['link', \htmlspecialchars(\Hook::fire('link', [$url->current([
                        'part' => $part,
                        'sort' => $sort
                    ], false)])), []],
                    ['title', '<![CDATA[' . ($exist ? $page->title : \i('Error')) . ' | ' . $state->title . ']]>', []]
                ], []]
            ],
            2 => [
                'version' => '2.0',
                'xmlns:atom' => 'http://www.w3.org/2005/Atom'
            ]
        ];
        $pages = [];
        foreach ($query ? \k($folder, 'page', $deep, \preg_split('/\s+/', $query), true) : \g($folder, 'page', $deep) as $k => $v) {
            $p = new \Page($k);
            $pages[$k] = [$sort[1] => (string) ($p->{$sort[1]} ?? 0)];
        }
        $pages = (new \Anemone($pages))->sort($sort, true)->chunk($chunk, -1, true)->get();
        if ($part > 1) {
            $lot[1][] = ['atom:link', false, [
                'href' => \Hook::fire('link', [$url->current([
                    'part' => $part - 1,
                    'sort' => $sort
                ], false)]),
                'rel' => 'prev'
            ]];
        }
        if (!empty($pages[$part])) {
            $lot[1][] = ['atom:link', false, [
                'href' => \Hook::fire('link', [$url->current([
                    'part' => $part + 1,
                    'sort' => $sort
                ], false)]),
                'rel' => 'next'
            ]];
        }
        if (!empty($pages[$part - 1])) {
            foreach (\array_keys($pages[$part - 1]) as $k => $v) {
                $page = new \Page($v);
                $item = ['item', [], []];
                $item[1][] = ['description', '<![CDATA[' . $page->description . ']]>', []];
                $item[1][] = ['guid', $guid = \htmlspecialchars(\Hook::fire('link', [$page->url])), []];
                $item[1][] = ['link', $guid, []];
                $item[1][] = ['pubDate', $page->time->format('r'), []];
                $item[1][] = ['title', '<![CDATA[' . $page->title . ']]>', []];
                if ($x_image && $page_image = $page->image(512, 512)) {
                    $image = ['image', [], []];
                    $image[1][] = ['height', '512', []];
                    $image[1][] = ['link', \htmlspecialchars(\Hook::fire('link', [$page->image])), []];
                    $image[1][] = ['title', '<![CDATA[' . \basename($page->image) . ']]>', []];
                    $image[1][] = ['url', \htmlspecialchars(\Hook::fire('link', [$page_image])), []];
                    $image[1][] = ['width', '512', []];
                    $item[1][] = $image;
                }
                if ($x_tag && $page_tags = $page->tags) {
                    foreach ($page_tags as $page_tag) {
                        $item[1][] = ['category', '<![CDATA[' . $page_tag->title . ']]>', [
                            'domain' => \Hook::fire('link', [$page_tag->link])
                        ]];
                    }
                }
                $lot[1][$guid] = $item;
            }
        } else if ($exist) {
            $item = ['item', [], []];
            $item[1][] = ['description', '<![CDATA[' . $page->description . ']]>', []];
            $item[1][] = ['guid', $guid = \htmlspecialchars(\Hook::fire('link', [$page->url])), []];
            $item[1][] = ['link', $guid, []];
            $item[1][] = ['pubDate', $page->time->format('r'), []];
            $item[1][] = ['title', '<![CDATA[' . $page->title . ']]>', []];
            if ($x_image && $page_image = $page->image(512, 512)) {
                $image = ['image', [], []];
                $image[1][] = ['height', '512', []];
                $image[1][] = ['link', \htmlspecialchars(\Hook::fire('link', [$page->image])), []];
                $image[1][] = ['title', '<![CDATA[' . \basename($page->image) . ']]>', []];
                $image[1][] = ['url', \htmlspecialchars(\Hook::fire('link', [$page_image])), []];
                $image[1][] = ['width', '512', []];
                $item[1][] = $image;
            }
            if ($x_tag && $page_tags = $page->tags) {
                foreach ($page_tags as $page_tag) {
                    $item[1][] = ['category', '<![CDATA[' . $page_tag->title . ']]>', [
                        'domain' => \Hook::fire('link', [$page_tag->link])
                    ]];
                }
            }
            $lot[1][$guid] = $item;
        } else {
            $status = 404;
        }
        $age = 60 * 60 * 24; // Cache for a day
        $content = '<?xml version="1.0" encoding="utf-8"?>' . (new \XML(\Hook::fire('y.feed', [$lot], $page), true));
        \status($status, $exist ? [
            'cache-control' => 'max-age=' . $age . ', private',
            'expires' => \gmdate('D, d M Y H:i:s', $age + $_SERVER['REQUEST_TIME']) . ' GMT',
            'pragma' => 'private'
        ] : [
            'cache-control' => 'max-age=0, must-revalidate, no-cache, no-store',
            'expires' => '0',
            'pragma' => 'no-cache'
        ]);
        \type('application/' . ($fire ? 'javascript' : 'rss+xml'));
        return $fire ? $fire . '(' . \To::JSON($content) . ');' : $content;
    }
    return $content;
}

if (!\in_array(\basename($url->path ?? ""), ['feed.json', 'feed.xml'])) {
    \Hook::set('content', __NAMESPACE__ . "\\content", -1);
} else {
    \Hook::set('route', __NAMESPACE__ . "\\route", 10);
}