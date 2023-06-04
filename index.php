<?php namespace x\feed;

function content($content) {
    \extract($GLOBALS, \EXTR_SKIP);
    $json = '<link href="' . $url->current(false, false) . '/feed.json" rel="alternate" title="' . \i('RSS') . ' | ' . \w($state->title) . '" type="application/feed+json">';
    $xml = '<link href="' . $url->current(false, false) . '/feed.xml" rel="alternate" title="' . \i('RSS') . ' | ' . \w($state->title) . '" type="application/rss+xml">';
    return \strtr($content ?? "", ['</head>' => $json . $xml . '</head>']);
}

function route($content, $path) {
    if (null !== $content) {
        return $content;
    }
    \extract($GLOBALS, \EXTR_SKIP);
    $x_image = isset($state->x->image);
    $x_tag = isset($state->x->tag);
    $x_user = isset($state->x->user);
    $chunk = $_GET['chunk'] ?? 25;
    $deep = $_GET['deep'] ?? 0;
    $fire = $_GET['fire'] ?? null;
    // Validate function name
    if ($fire && !\preg_match('/^[a-z_$][\w$]*(\.[a-z_$][\w$]*)*$/i', $fire)) {
        \status(403);
        return "";
    }
    $part = $_GET['part'] ?? 1;
    $query = $_GET['query'] ?? null;
    $sort = \array_replace([-1, 'time'], (array) ($_GET['sort'] ?? []));
    $path = \trim(\dirname($path ?? ""), '/');
    $route = \trim($state->route ?? "", '/');
    $folder = \LOT . \D . 'page' . \D . ($path ?: $route);
    $page = new \Page(\exist([
        $folder . '.archive',
        $folder . '.page'
    ], 1) ?: null);
    $page_exist = $page->exist();
    $name =\basename($path ?? "");
    $status = 200;
    // <https://www.jsonfeed.org>
    if ('feed.json' === $name) {
        $lot = [
            'description' => ($page->description ?? $state->description) ?: null,
            'feed_url' => \Hook::fire('link', [$url->current(false, false) . $url->query([
                'part' => $part,
                'sort' => $sort
            ])]),
            'home_page_url' => \Hook::fire('link', [(string) $url]),
            'items' => [],
            'title' => ($page_exist ? $page->title : \i('Error')) . ' | ' . $state->title,
            'version' => 'https://jsonfeed.org/version/1.1'
        ];
        if ($author = $page->author) {
            if ($x_user && $author instanceof \User) {
                $author_avatar = $author->avatar(512, 512);
                $author_link = $author->link;
                $author_url = $author->url;
                if ($author_avatar) {
                    $author['avatar'] = \Hook::fire('link', [$author_avatar]);
                }
                $author = ['name' => (string) $author];
                if ($author_link || $author_url) {
                    $author['url'] = \Hook::fire('link', [$author_link ?? $author_url]);
                }
                \ksort($author);
                $lot['author'] = $lot['authors'][0] = $author;
            } else if (\is_string($author)) {
                $lot['author'] = $lot['authors'][0] = ['name' => $author];
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
            $lot['next_url'] = \Hook::fire('link', [$url->current(false, false) . $url->query([
                'part' => $part + 1,
                'sort' => $sort
            ])]);
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
                        $author = ['name' => (string) $page_author];
                        $page_author_avatar = $page_author->avatar(512, 512);
                        $page_author_link = $page_author->link;
                        $page_author_url = $page_author->url;
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
        } else if ($page_exist) {
            $item = [];
            // if ($page_content = $page->content) {
            //     $item['content_html'] = $page_content;
            //     $item['content_text'] = \trim(\strip_tags($page_content));
            // }
            if ($page_author = $page->author) {
                if ($x_user && $page_author instanceof \User) {
                    $author = ['name' => (string) $page_author];
                    $page_author_avatar = $page_author->avatar(512, 512);
                    $page_author_link = $page_author->link;
                    $page_author_url = $page_author->url;
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
        $age = 60 * 60 * 24; // Cache output for a day
        \status($status, $page_exist ? [
            'cache-control' => 'max-age=' . $age . ', private',
            'expires' => \gmdate('D, d M Y H:i:s', $age + $_SERVER['REQUEST_TIME']) . ' GMT',
            'pragma' => 'private'
        ] : [
            'cache-control' => 'max-age=0, must-revalidate, no-cache, no-store',
            'expires' => '0',
            'pragma' => 'no-cache'
        ]);
        \type('application/' . ($fire ? 'javascript' : 'feed+json'));
        return ($fire ? $fire . '(' : "") . \json_encode($lot, \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_HEX_TAG | \JSON_UNESCAPED_UNICODE) . ($fire ? ');' : "");
    }
    // <https://validator.w3.org/feed/docs/rss2.html>
    if ('feed.xml' === $name) {
        $content = "";
        $content .= '<?xml version="1.0" encoding="utf-8"?>';
        $content .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">';
        $content .= '<channel>';
        $content .= '<generator>Mecha ' . \VERSION . '</generator>';
        $content .= '<title><![CDATA[' . ($page_exist ? $page->title : \i('Error')) . ' | ' . $state->title . ']]></title>';
        $content .= '<link>' . \Hook::fire('link', [$url->clean(false, false)]) . '</link>';
        $content .= '<description><![CDATA[' . ($page->description ?? $state->description) . ']]></description>';
        $content .= '<lastBuildDate>' . \date('r', $_SERVER['REQUEST_TIME']) . '</lastBuildDate>';
        $content .= '<language>' . ($state->language ?? 'en') . '</language>';
        $content .= '<atom:link href="' . \Hook::fire('link', [$url->current(false, false) . \htmlspecialchars($url->query([
            'part' => $part,
            'sort' => $sort
        ]))]) . '" rel="self"/>';
        $pages = [];
        foreach ($query ? \k($folder, 'page', $deep, \preg_split('/\s+/', $query), true) : \g($folder, 'page', $deep) as $k => $v) {
            $p = new \Page($k);
            $pages[$k] = [$sort[1] => (string) ($p->{$sort[1]} ?? 0)];
        }
        $pages = (new \Anemone($pages))->sort($sort, true)->chunk($chunk, -1, true)->get();
        if ($part > 1) {
            $content .= '<atom:link href="' . \Hook::fire('link', [$url->current(false, false) . \htmlspecialchars($url->query([
                'part' => $part - 1,
                'sort' => $sort
            ]))]) . '" rel="prev"/>';
        }
        if (!empty($pages[$part])) {
            $content .= '<atom:link href="' . \Hook::fire('link', [$url->current(false, false) . \htmlspecialchars($url->query([
                'part' => $part + 1,
                'sort' => $sort
            ]))]) . '" rel="next"/>';
        }
        if (!empty($pages[$part - 1])) {
            foreach (\array_keys($pages[$part - 1]) as $k => $v) {
                $page = new \Page($v);
                $content .= '<item>';
                $content .= '<description><![CDATA[' . $page->description . ']]></description>';
                $content .= '<guid>' . \Hook::fire('link', [$page->url]) . '</guid>';
                $content .= '<link>' . \Hook::fire('link', [$page->url]) . '</link>';
                $content .= '<pubDate>' . $page->time->format('r') . '</pubDate>';
                $content .= '<title><![CDATA[' . $page->title . ']]></title>';
                if ($x_image && $image = $page->image(512, 512)) {
                    $content .= '<image>';
                    $content .= '<height>512</height>';
                    $content .= '<link>' . \Hook::fire('link', [$page->url]) . '</link>';
                    $content .= '<title><![CDATA[' . $page->title . ']]></title>';
                    $content .= '<url>' . \Hook::fire('link', [$image]) . '</url>';
                    $content .= '<width>512</width>';
                    $content .= '</image>';
                }
                if ($x_tag) {
                    foreach ($page->tags as $tag) {
                        $content .= '<category domain="' . \Hook::fire('link', [$tag->link]) . '"><![CDATA[' . $tag->title . ']]></category>';
                    }
                }
                $content .= '</item>';
            }
        } else if ($page_exist) {
            $content .= '<item>';
            $content .= '<title><![CDATA[' . $page->title . ']]></title>';
            $content .= '<link>' . \Hook::fire('link', [$page->url]) . '</link>';
            $content .= '<description><![CDATA[' . $page->description . ']]></description>';
            $content .= '<pubDate>' . $page->time->format('r') . '</pubDate>';
            $content .= '<guid>' . \Hook::fire('link', [$page->url]) . '</guid>';
            if ($x_image && $image = $page->image(512, 512)) {
                $content .= '<image>';
                $content .= '<height>512</height>';
                $content .= '<link>' . \Hook::fire('link', [$link]) . '</link>';
                $content .= '<title>' . \basename($link = $page->image) . '</title>';
                $content .= '<url>' . \Hook::fire('link', [$image]) . '</url>';
                $content .= '<width>512</width>';
                $content .= '</image>';
            }
            if ($x_tag) {
                foreach ($page->tags as $tag) {
                    $content .= '<category domain="' . \Hook::fire('link', [$tag->link]) . '"><![CDATA[' . $tag->title . ']]></category>';
                }
            }
            $content .= '</item>';
        } else {
            $status = 404;
        }
        $content .= '</channel>';
        $content .= '</rss>';
        $age = 60 * 60 * 24; // Cache output for a day
        \status($status, $page_exist ? [
            'cache-control' => 'max-age=' . $age . ', private',
            'expires' => \gmdate('D, d M Y H:i:s', $age + $_SERVER['REQUEST_TIME']) . ' GMT',
            'pragma' => 'private'
        ] : [
            'cache-control' => 'max-age=0, must-revalidate, no-cache, no-store',
            'expires' => '0',
            'pragma' => 'no-cache'
        ]);
        \type('application/' . ($fire ? 'javascript' : 'rss+xml'));
        return $fire ? $fire . '(' . \json_encode($content, \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_HEX_TAG | \JSON_UNESCAPED_UNICODE) . ');' : $content;
    }
    return $content;
}

// Insert some HTML `<link>` that maps to the feed resource
if (!\in_array(\basename($url->path ?? ""), ['feed.json', 'feed.xml'])) {
    \Hook::set('content', __NAMESPACE__ . "\\content", -1);
    \Hook::set('route', __NAMESPACE__ . "\\route", 10);
}