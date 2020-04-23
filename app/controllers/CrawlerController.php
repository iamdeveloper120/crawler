<?php
declare(strict_types=1);

class CrawlerController extends \Phalcon\Mvc\Controller
{
    use ResponseController;
    public $linksForCrawl = array();

    public $howManyPages = 2;
    public function indexAction()
    {
        $url = $_REQUEST['url'] ?? null;
        if(!$url || $url == null){
            $payload = json_decode(file_get_contents('php://input'));
            $url = $payload->url ?? null;
        }
        $isExist = $this->urlExists($url) ?? null;
        if(!$url || $url == '' || $url == null || !$isExist || $isExist == ''){
            $this->success = false;
            $this->message = 'Invalid URL or please provide a url to crawl page(s)';
            $this->status = 404;
            return $this->apiResponse();
        }

        $result = $this->crawlPage($url);
        $crawl = array();
        $crawl['crawl'][] = $result;
        if (count($this->linksForCrawl) > 0) {
            foreach ($this->linksForCrawl as $link) {
                $crawl['crawl'][] = $this->crawlPage($link);
            }
        }
        $data = array();
        $wordCount = 0;
        $titleLength = 0;
        $pageLoad = 0;
        $totalImages = 0;
        $totalInternalLinks = 0;
        $totalExternalLinks = 0;
        foreach ($crawl['crawl'] as $key => $val) {
            $pageLoad += $val['page_load'];
            $wordCount += $val['word_count'];
            $titleLength += $val['title_length'];
            $totalImages += count($val['images']) ?? 0;
            $totalInternalLinks += count($val['internal_links']) ?? 0;
            $totalExternalLinks += count($val['external_links']) ?? 0;
            $data['page_load'] = $pageLoad;
            $data['word_count'] = $wordCount;
            $data['title_length'] = $titleLength;
            $data['total_images'] = $totalImages;
            $data['total_internal_link'] = $totalInternalLinks;
            $data['total_external_link'] = $totalExternalLinks;
        }
        $crawl['pages_crawled'] = count($crawl['crawl']);
        $crawl['avg_page_load'] = ($data['page_load']/count($crawl));
        $crawl['avg_word_count'] = ceil($data['word_count']/count($crawl));
        $crawl['avg_title_length'] = ceil($data['title_length']/count($crawl));
        $crawl['total_images'] = $totalImages;
        $crawl['totlal_internal_link'] = $totalInternalLinks;
        $crawl['total_external_link'] = $totalExternalLinks;

        $this->data = $crawl;
        return $this->apiResponse();
    }

    function crawlPage($url, $depth=null)
    {
        $dom = new DOMDocument();
        @$dom->loadHTMLFile($url);
        $anchors = $dom->getElementsByTagName('a');
        $internalLinks = array();
        $externalLinks = array();
        $arrayLinks = array();
        $imagesArray = array();
        foreach ($anchors as $element) {
            $href = $element->getAttribute('href');
            if (0 !== strpos($href, 'http')) {
                $path = str_replace([' '], '', '/' . ltrim($href, '/'));
                if(!in_array(trim($path), $arrayLinks)){
                    $internalLinks[] = $this->absolutePath($url, $path);
                }
            } else {
                $path = str_replace([' '], '', '/' . ltrim($href, '/'));
                if(!in_array(trim($path), $arrayLinks)){
                    $externalLinksLinks[] = $this->absolutePath($url, $path);
                }
            }
            $arrayLinks[] = $path;
        }

        $images = $dom->getElementsByTagName('img');
        foreach ($images as $image) {
            $img = $image->getAttribute('src');
            if($img) {
                $imagesArray[] = $this->absolutePath($url, $img);
            }
        }
        $pageTitle = '';
        $titleTag = $dom->getElementsByTagName("title");
        if ($titleTag->length > 0) {
            $pageTitle = $titleTag->item(0)->textContent;
        }
        $pageLoadTime = $this->pageLoadTime($url);
        $pageWordCount = $this->pageWordCount($dom);
        $pageTitleCount = strlen($pageTitle) ?? 0;

        $this->linksForCrawl = array_slice($internalLinks, 1, $this->howManyPages-1);

        $data = [] || array();
        $data['url'] = $url;
        $data['internal_links'] = $internalLinks;
        $data['external_links'] = $externalLinks;
        $data['images'] = $imagesArray;
        $data['page_load'] = $pageLoadTime;
        $data['word_count'] = $pageWordCount;
        $data['title_length'] = $pageTitleCount;
        return $data;
    }

    public function pageLoadTime($url) {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_exec($curl);
        return curl_getinfo($curl, CURLINFO_TOTAL_TIME);

    }

    public function pageWordCount($dom) {
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//text()');

        $textNodeContent = '';
        foreach($nodes as $node) {
            $textNodeContent .= " $node->nodeValue";
        }
        return str_word_count($textNodeContent) ?? 0;
    }

    public function absolutePath($url, $path) {
        $parts = parse_url($url);
        $schema = $parts['scheme'] . '://';
        return $schema.$parts['host'].$path;
    }

    function secondsToTime($seconds) {
        $dtF = new \DateTime('@0');
        $dtT = new \DateTime("@$seconds");
        return $dtF->diff($dtT)->format('%s seconds');
    }

    function urlExists($url) {
        $result = false;
        $url = filter_var($url, FILTER_VALIDATE_URL);
        if(!$url || $url == false ){
            return $result;
        }

        /* Open curl connection */
        $handle = curl_init($url);

        /* Set curl parameter */
        curl_setopt_array($handle, array(
            CURLOPT_FOLLOWLOCATION => TRUE,     // we need the last redirected url
            CURLOPT_NOBODY => TRUE,             // we don't need body
            CURLOPT_HEADER => FALSE,            // we don't need headers
            CURLOPT_RETURNTRANSFER => FALSE,    // we don't need return transfer
            CURLOPT_SSL_VERIFYHOST => FALSE,    // we don't need verify host
            CURLOPT_SSL_VERIFYPEER => FALSE     // we don't need verify peer
        ));

        /* Get the HTML or whatever is linked in $url. */
        $response = curl_exec($handle);

        $httpCode = curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);  // Try to get the last url
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);      // Get http status from last url

        /* Check for 200 (file is found). */
        if($httpCode == 200) {
            $result = true;
        }

        return $result;

        /* Close curl connection */
        curl_close($handle);
    }

}

