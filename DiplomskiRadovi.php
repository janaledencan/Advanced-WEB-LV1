<?php

require_once('simple_html_dom.php'); 

interface iRadovi {
    public function create($data);
    public function read();
    public function save();
}

class DiplomskiRadovi implements iRadovi {
    
    private $naziv_rada;
    private $tekst_rada;
    private $link_rada;
    private $oib_tvrtke;

    function __construct($data){
        $this->naziv_rada = $data['naziv_rada'];
        $this->tekst_rada = $data['tekst_rada'];
        $this->link_rada = $data['link_rada'];
        $this->oib_tvrtke = $data['oib_tvrtke'];
    }

    public function create($data)
    {
        return new self($data);
    }

    function read(){}
    function save(){}

    public function display() {
        echo "Title: " . $this->naziv_rada . "<br>";
        echo "Description: " . $this->tekst_rada . "<br>";
        echo "Link: <a href='" . $this->link_rada . "'>" . $this->link_rada . "</a><br>";
        echo "Company ID: " . $this->oib_tvrtke . "<br><br>";
    }

}



function fetch_html($url) {
    // Parse the URL
    $url_pieces = parse_url($url);
    $host = $url_pieces['host'];
    $path = isset($url_pieces['path']) ? $url_pieces['path'] : '/';
    $port = isset($url_pieces['port']) ? $url_pieces['port'] : 80;
    $scheme = isset($url_pieces['scheme']) ? $url_pieces['scheme'] : 'http';

    // Use SSL for HTTPS
    if ($scheme == 'https') {
        $port = 443;
        $host = 'ssl://' . $host;
    }

    // Open socket connection
    $fp = fsockopen($host, $port, $errno, $errstr, 30);
    if (!$fp) {
        echo "Error: $errstr ($errno)<br />\n";
        return false;
    }

    // Send HTTP GET request
    $out = "GET $path HTTP/1.1\r\n";
    $out .= "Host: {$url_pieces['host']}\r\n";
    $out .= "Connection: Close\r\n\r\n";
    fwrite($fp, $out);

    // Read the response
    $response = '';
    $is_header = true;
    while (!feof($fp)) {
        $line = fgets($fp, 4096);
        if ($is_header) {
            // Detect end of headers
            if (trim($line) == '') {
                $is_header = false;
            }
        } else {
            $response .= $line;
        }
    }
    fclose($fp);

    return $response;
}

// URL to fetch
$url = 'https://stup.ferit.hr/zavrsni-radovi/page/7';

// Fetch HTML
$html_content = fetch_html($url);
// $html_content = file_get_contents($url);

// Debug: Check if HTML was fetched
if (!$html_content) {
    die("Failed to fetch HTML. Check your URL and connection.");
}

// Remove non-HTML characters before <!DOCTYPE>
$html_content = preg_replace('/^[^\<]+/', '', $html_content);

// Convert encoding (in case of special characters)
$html_content = mb_convert_encoding($html_content, 'HTML-ENTITIES', 'UTF-8');

// Check the cleaned HTML
//echo "<pre>" . htmlspecialchars(substr($html_content, 1000)) . "</pre>"; 
//exit;

// Check if content is fetched
if ($html_content) {
    
    // Parse HTML
    $html = new simple_html_dom();
    $html->load_file($url);

    $items = $html->find("article");

    foreach ($items as $item) {
        // Extract elements
        $item_title = $item->find('h2.blog-shortcode-post-title.entry-title a', 0)->plaintext;
        $item_text = $item->find('div.fusion-post-content-container p', 0)->plaintext;
        $item_link = $item->find('h2.blog-shortcode-post-title a', 0)->href;

        //Extract company ID from the image URL
        $company_img = $item->parent()->find('img', 0)->src; 
        preg_match('/logos\/(\d+)\.png/', $company_img, $matches);
        $oib_tvrtke = $matches[1] ?? null;

    
        // Create an object
        $data = [
            'naziv_rada' => $item_title,
            'tekst_rada' => $item_text,
            'link_rada' => $item_link,
            'oib_tvrtke' => $oib_tvrtke
        ];
    
        $thesis = new DiplomskiRadovi($data);

        $thesis->display();

        // //Extract .png images
        $img = $item->find('img[src$=".png"]', 0);
        $oib = $img ? basename($img->src, '.png') : 'No image found';

        // Extract first <a> tag URL
        $a = $item->find('a', 0);
        $link_rada = $a ? $a->href : 'No link found';

        // Print results
        echo "OIB: " . htmlspecialchars($oib) . "<br>";
        echo "Link Rada: <a href='$link_rada'>$link_rada</a>";
    }

    
} else {
    echo "Failed to fetch content.";
}
 
?>