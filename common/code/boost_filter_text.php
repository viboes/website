<?php
/*
  Copyright 2005-2008 Redshift Software, Inc.
  Distributed under the Boost Software License, Version 1.0.
  (See accompanying file LICENSE_1_0.txt or http://www.boost.org/LICENSE_1_0.txt)
*/

class BoostFilterText extends BoostFilters
{
    function __construct($params) {
        parent::__construct($params);
        // TODO: Better support for other character sets?
        $this->charset = 'UTF-8';
    }

    function echo_filtered()
    {
        $this->title = html_encode($this->params['key']);

        $this->display_template(
            $this->template_params($this->text_filter_content()));
    }

    function text_filter_content()
    {
        return
            "<h3>".html_encode($this->params['key'])."</h3>\n".
            "<pre>\n".
            $this->encoded_text('text').
            "</pre>\n";
    }

    // This takes a plain text file and outputs encoded html with marked
    // up links.

    function encoded_text($type) {
        $text = '';

        $root = dirname(preg_replace('@([^/]+/)@','../',$this->params['key']));

        // John Gruber's regular expression for finding urls
        // http://daringfireball.net/2009/11/liberal_regex_for_matching_urls
        
        foreach(preg_split(
            '@\b((?:[\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|[^[:punct:]\s]|/))@',
            $this->params['content'], -1, PREG_SPLIT_DELIM_CAPTURE)
            as $index => $part)
        {
            if($index % 2 == 0) {
                $html = $this->html_encode_with_fallback($part);

                if($type == 'cpp') {
                    $html = preg_replace(
                        '@(#[ ]*include[ ]+&lt;)(boost[^&]+)@Ssm',
                        '${1}<a href="'.$root.'/${2}">${2}</a>',
                        $html );
                    $html = preg_replace(
                        '@(#[ ]*include[ ]+&quot;)(boost[^&]+)@Ssm',
                        '${1}<a href="'.$root.'/${2}">${2}</a>',
                        $html );
                }

                $text .= $html;
            }
            else {
                $url = $this->process_absolute_url($part, $root);
                if($url) {
                    $text .= '<a href="'.html_encode($url).'">'.
                        $this->html_encode_with_fallback($part).'</a>';
                }
                else {
                    $text .= $this->html_encode_with_fallback($part);
                }
            }
        }

        return $text;
    }

    function html_encode_with_fallback($text) {
        // Could probably handle this better with php 5.4 or multibyte
        // extensions.
        $encoded_text = html_encode($text);

        if ($text && !$encoded_text) {
            $encoded_text = html_encode(
                preg_replace('/[\x80-\xFF]/', "\xef\xbf\xbd", $text));
        }

        return $encoded_text;
    }

    function process_absolute_url($url, $root = null) {
        // Simplified version of the 'loose' regular expression from
        // http://blog.stevenlevithan.com/archives/parseuri
        //
        // (c) Steven Levithan <stevenlevithan.com>
        // MIT License

        if(!preg_match(
            '~^'.
            // Protocol(1): (Could also remove the userinfo detection stuff?)
            '(?:(?![^:@]+:[^:@\/]*@)([^:\/?#.]+):)?'.
            '(?:\/\/)?'.
            // Authority(2)
            '('.
                // User info
                '(?:[^:@]*:?[^:@]*@)?'.
                // Host(3)
                '([^:\/?#]*)'.
                // Port
                '(?::\d*)?'.
            ')'.
            // Relative(4)
            '(\/.*)'.
            '~',
            $url, $matches))
        {
            return;
        }

        $protocol = $matches[1];
        $authority = $matches[2];
        $host = $matches[3];
        $relative = $matches[4];

        if(!$authority) return;

        if($root &&
            ($host == 'boost.org' || $host == 'www.boost.org') &&
            strpos($relative, '/lib') === 0 &&
            strpos($relative, '.') === 0)
        {
            $url = $root.$relative;
        }
        else
        {
            $url = ($protocol ? $protocol : 'http').'://'.$authority.$relative;
        }

        return $url;
    }
}
