<?php

//Uključivanje svih fileova i biblioteke
require_once('simple_html_dom.php'); 
require 'db.php';
require_once "iRadovi.php";

// Klasa DiplomskiRadovi koja implementira iRadovi, ima privatne varijable za naziv rada, 
// tekst i link rada te oib tvrtke te metode create, save i read
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


    public function save($conn) {
        // Provjera postoji li rad već u tablici diplomskiRadovi kako se ne bi spremali više puta isti radovi
        $sql_check = "SELECT id FROM diplomskiRadovi WHERE naziv_rada = ? AND oib_tvrtke = ?";
        $statement_check = $conn->prepare($sql_check);
        $statement_check->bind_param("ss", $this->naziv_rada, $this->oib_tvrtke);
        $statement_check->execute();
        $statement_check->store_result();
    
        if ($statement_check->num_rows > 0) {
            echo "This work is already saved in the database.<br>";
        } else {
            // Ako rad ne postoji u bazi umetni ga u tablicu diplomskiRadovi
            $sql = "INSERT INTO diplomskiRadovi (naziv_rada, tekst_rada, link_rada, oib_tvrtke) VALUES (?, ?, ?, ?)";
            $statement = $conn->prepare($sql);
            //Argumentom "ssss" u bind_param() specificiramo tipove podataka za vrijednosti koje se umeću, svi su stringovi
            $statement->bind_param("ssss", $this->naziv_rada, $this->tekst_rada, $this->link_rada, $this->oib_tvrtke);
    
            if ($statement->execute()) {
                echo "Thesis saved successfully.<br>";
            } else {
                echo "Error: " . $statement->error . "<br>";
            }
            $statement->close();
        }
    
        $statement_check->close();
    }

    /* Metoda read() dohvaća sve zapise iz tablice diplomskiRadovi u bazi podataka i prikazuje ih u 
       jednostavnom formatu. Dohvaća podatke za svaki rad, uključujući naslov, tekst rada, poveznicu rada, 
       oib tvrtke i ID pod kojim se nalazi spremljen u tablici diplomskiRadovi.
    */
    public function read($conn) {
        $sql = "SELECT * FROM diplomskiRadovi";
        $result = $conn->query($sql);
    
        if ($result->num_rows > 0) {

            echo '<h2>Radovi iz baze:</h2>';
            echo '<div class="container">';
            
            while ($row = $result->fetch_assoc()) {
                echo '
                    <div class="thesis-card">
                        <h3>' . htmlspecialchars($row['naziv_rada']) . '</h3>
                        <p>' . htmlspecialchars($row['tekst_rada']) . '</p>
                        <a href="' . htmlspecialchars($row['link_rada']) . '" target="_blank">View Thesis</a>
                        <p>Company ID: ' . htmlspecialchars($row['oib_tvrtke']) . '</p>
                        <p>Item ID: ' . htmlspecialchars($row['id']) . '</p>
                    </div>
                    <br>
                ';
            }
    
            echo '</div>';
        } else {
            echo '<div class="no-theses">No theses found.</div>';
        }
    }
    
    // Funkcija za prikaz podataka kada se dohvaćaju s url 'https://stup.ferit.hr/zavrsni-radovi/page/num';
    public function display() {
        echo "Title: " . $this->naziv_rada . "<br>";
        echo "Description: " . $this->tekst_rada . "<br>";
        echo "Link: <a href='" . $this->link_rada . "'>" . $this->link_rada . "</a><br>";
        echo "Company ID: " . htmlspecialchars($this->oib_tvrtke) . "<br><br>";
    }

}



function fetch_html($url) {
    // Parse the URL
    $url_pieces = parse_url($url);
    $host = $url_pieces['host'];
    $path = isset($url_pieces['path']) ? $url_pieces['path'] : '/';
    $port = isset($url_pieces['port']) ? $url_pieces['port'] : 80;
    $scheme = isset($url_pieces['scheme']) ? $url_pieces['scheme'] : 'http';

    // Korištenje SSL za HTTPS
    if ($scheme == 'https') {
        $port = 443;
        $host = 'ssl://' . $host;
    }

    // Otvaranje socket konekcije
    $fp = fsockopen($host, $port, $errno, $errstr, 30);
    if (!$fp) {
        echo "Error: $errstr ($errno)<br />\n";
        return false;
    }

    // Slanje HTTP GET request
    $out = "GET $path HTTP/1.1\r\n";
    $out .= "Host: {$url_pieces['host']}\r\n";
    $out .= "Connection: Close\r\n\r\n";
    fwrite($fp, $out);

    // Čitanje odgovora 
    $response = '';
    $is_header = true;
    while (!feof($fp)) {
        $line = fgets($fp, 4096);
        if ($is_header) {
            // Otkrij kraj zaglavlja
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

// URL za dohvaćanje podataka
$url = 'https://stup.ferit.hr/zavrsni-radovi/page/4';


$html_content = fetch_html($url);

// Provjera je li HTML dohvaćen
if (!$html_content) {
    die("Failed to fetch HTML. Check your URL and connection.");
}

// Uklonjanje znakove koji nisu HTML prije <!DOCTYPE>
$html_content = preg_replace('/^[^\<]+/', '', $html_content);

// Pretvaranje encodinga (u slučaju posebnih znakova) 
$html_content = mb_convert_encoding($html_content, 'HTML-ENTITIES', 'UTF-8');

// Provjera je li sadržaj dohvaćen, ako je pozivaju se metode create za stvaranje objekta i save  
if ($html_content) {
    
    // Parse HTML
    $html = new simple_html_dom();
    $html->load_file($url);

    $items = $html->find("article");

    foreach ($items as $item) {
        // Izdvajanje elemenata
        $item_title = $item->find('h2.blog-shortcode-post-title.entry-title a', 0)->plaintext;
        $item_text = $item->find('div.fusion-post-content-container p', 0)->plaintext;
        $item_link = $item->find('h2.blog-shortcode-post-title a', 0)->href;


        // Izdvajanje OIB-a tvrtke iz URL-a slike u objektu $item
        $company_img = $item->find('img[src$=".png"]', 0);
        //Traži URL slike u objektu $item koji završava s .png, izdvaja naziv datoteke (bez .png) kao 
        // ID tvrtke i pohranjuje ga u $oib_tvrtke.
        $oib_tvrtke = $company_img ? basename($company_img->src, '.png') : 'No image found';
    
        // Kreiranje objekta
        $data = [
            'naziv_rada' => $item_title,
            'tekst_rada' => $item_text,
            'link_rada' => $item_link,
            'oib_tvrtke' => $oib_tvrtke
        ];
    
        $thesis = new DiplomskiRadovi($data);

        //Pozivi metoda
        $thesis->display();
        $thesis->save($conn); 
    }
    
} else {
    echo "Failed to fetch content.";
}
 
     $thesis->read($conn);

?>