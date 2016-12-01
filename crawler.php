<?php
/**
 * Created by Pavlo Onysko.
 * Date: 11/10/16.
 */
include_once 'helpers/Config.php';
include_once 'helpers/Request.php';
include_once 'helpers/Crawler.php';

$map = $_SERVER['argv'][1];

if (!strpos($map, '.xml')) {
    die('Please provide XML sitemap');
}

$crawler = new \helpers\Crawler($map);
$crawler->start();
