<?php

namespace x {
    function feed($content) {
        \extract($GLOBALS, \EXTR_SKIP);
        return \strtr($content ?? "", ['</head>' => '<link href="' . $url->current(false, false) . '/feed.xml" rel="alternate" title="' . \i('RSS') . ' | ' . \w($state->title) . '" type="application/rss+xml"></head>']);
    }
    // Insert some HTML `<link>` that maps to the feed resource
    if (!\has(['feed.json', 'feed.xml'], \basename($url->path ?? ""))) {
        // Make sure to run the hook before `x\link\content`
        \Hook::set('content', __NAMESPACE__ . "\\feed", -1);
    }
}

// <https://validator.w3.org/feed/docs/rss2.html>
namespace x\feed\route {
    function json($content, $path) {
        if (null !== $content) {
            return $content;
        }
        \extract($GLOBALS, \EXTR_SKIP);
        $chunk = $_GET['chunk'] ?? 25;
        $deep = $_GET['deep'] ?? 0;
        $fire = $_GET['fire'] ?? null;
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
        // Validate function name
        if ($fire && !\preg_match('/^[a-z_$][\w$]*(\.[a-z_$][\w$]*)*$/i', $fire)) {
            \status(403);
            return "";
        }
        $x_image = isset($state->x->image);
        $x_tag = isset($state->x->tag);
        $status = 200;
        $lot = [
            0 => [
                'current' => \Hook::fire('link', [$url->current(false, false) . $url->query([
                    'part' => $part,
                    'sort' => $sort
                ])]),
                'description' => ($page->description ?? $state->description) ?: null,
                'generator' => 'Mecha ' . \VERSION,
                'language' => $state->language ?? 'en',
                'time' => (string) $page->time,
                'title' => ($page_exist ? $page->title : \i('Error')) . ' | ' . $state->title,
                'url' => \Hook::fire('link', [$url->current(false, false)])
            ],
            1 => null
        ];
        if ($x_tag) {
            $lot[0]['tags'] = [];
            foreach (\g(\LOT . \D . 'tag', 'page') as $k => $v) {
                $tag = new \Tag($k);
                $lot[0]['tags'][$tag->name] = [
                    'description' => $tag->description ?: null,
                    'id' => $tag->id,
                    'time' => (string) $tag->time,
                    'title' => $tag->title
                ];
                \ksort($lot[0]['tags']);
            }
        }
        $pages = [];
        foreach ($query ? \k($folder, 'page', $deep, \preg_split('/\s+/', $query), true) : \g($folder, 'page', $deep) as $k => $v) {
            $p = new \Page($k);
            $pages[$k] = [$sort[1] => (string) ($p->{$sort[1]} ?? 0)];
        }
        $lot[0]['count'] = \count($pages);
        $pages = (new \Anemone($pages))->sort($sort, true)->chunk($chunk, -1, true)->get();
        if ($part > 1) {
            $lot[0]['prev'] = \Hook::fire('link', [$url->current(false, false) . $url->query([
                'part' => $part - 1,
                'sort' => $sort
            ])]);
        }
        if (!empty($pages[$part])) {
            $lot[0]['next'] = \Hook::fire('link', [$url->current(false, false) . $url->query([
                'part' => $part + 1,
                'sort' => $sort
            ])]);
        }
        if (!empty($pages[$part - 1])) {
            $lot[1] = [];
            foreach (\array_keys($pages[$part - 1]) as $k => $v) {
                $page = new \Page($v);
                $lot[1][$k] = [
                    'description' => $page->description ?: null,
                    'id' => $page->id,
                    'image' => \Hook::fire('link', [$x_image ? $page->image(72, 72) : null]),
                    'link' => \Hook::fire('link', [$page->link]),
                    'time' => (string) $page->time,
                    'title' => $page->title,
                    'url' => \Hook::fire('link', [$page->url])
                ];
                if ($x_tag) {
                    $lot[1][$k]['kind'] = (array) $page->kind;
                }
            }
        } else if ($page_exist) {
            $lot[1] = [];
            $lot[1][0] = [
                'description' => $page->description ?: null,
                'id' => $page->id,
                'image' => \Hook::fire('link', [$x_image ? $page->image(72, 72) : null]),
                'link' => \Hook::fire('link', [$page->link]),
                'time' => (string) $page->time,
                'title' => $page->title,
                'url' => \Hook::fire('link', [$page->url])
            ];
            if ($x_tag) {
                $lot[1][0]['kind'] = (array) $page->kind;
            }
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
        \type('application/' . ($fire ? 'javascript' : 'json'));
        return ($fire ? $fire . '(' : "") . \json_encode($lot, \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_HEX_TAG | \JSON_UNESCAPED_UNICODE) . ($fire ? ');' : "");
    }
    function xml($content, $path) {
        if (null !== $content) {
            return $content;
        }
        \extract($GLOBALS, \EXTR_SKIP);
        $chunk = $_GET['chunk'] ?? 25;
        $deep = $_GET['deep'] ?? 0;
        $fire = $_GET['fire'] ?? null;
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
        // Validate function name
        if ($fire && !\preg_match('/^[a-z_$][\w$]*(\.[a-z_$][\w$]*)*$/i', $fire)) {
            \status(403);
            return "";
        }
        $x_image = isset($state->x->image);
        $x_tag = isset($state->x->tag);
        $status = 200;
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
                if ($x_image && $image = $page->image(72, 72)) {
                    $content .= '<image>';
                    $content .= '<height>72</height>';
                    $content .= '<link>' . \Hook::fire('link', [$page->url]) . '</link>';
                    $content .= '<title><![CDATA[' . $page->title . ']]></title>';
                    $content .= '<url>' . \Hook::fire('link', [$image]) . '</url>';
                    $content .= '<width>72</width>';
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
            if ($x_image && $image = $page->image(72, 72)) {
                $content .= '<image>';
                $content .= '<title>' . \basename($link = $page->image) . '</title>';
                $content .= '<url>' . \Hook::fire('link', [$image]) . '</url>';
                $content .= '<link>' . \Hook::fire('link', [$link]) . '</link>';
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
    if ('feed.json' === \basename($url->path ?? "")) {
        \Hook::set('route', __NAMESPACE__ . "\\json", 10);
    } else if ('feed.xml' === \basename($url->path ?? "")) {
        \Hook::set('route', __NAMESPACE__ . "\\xml", 10);
    }
}