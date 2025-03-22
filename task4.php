<?php
// Include your CompanyClass from Task 1
require_once 'CompanyClass.php';

// --- Database Connection ---
$dbHost = 'localhost';
$dbPort = 5432;
$dbName = 'your_db';
$dbUser = 'your_user';
$dbPass = 'your_pass';

$dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// --- Generate Test Data if Needed ---
function generateTestData(PDO $pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM companies");
    $count = $stmt->fetchColumn();
    if ($count == 0) {
        // Insert some test data into the companies table
        $testData = [
            ['name' => ' OpenAI ', 'website' => 'https://openai.com ', 'address' => ' ', 'source' => 'API_1'],
            ['name' => 'Innovatiespotter', 'website' => null, 'address' => 'Groningen', 'source' => 'MANUAL'],
            ['name' => ' Apple ', 'website' => 'xhttps://apple.com ', 'address' => 'Some Address', 'source' => 'SCRAPER_2'],
            // Duplicate with higher priority (MANUAL) for OpenAI
            ['name' => ' OpenAI ', 'website' => 'https://www.openai.com ', 'address' => 'Silicon Valley', 'source' => 'MANUAL']
        ];
        $insertStmt = $pdo->prepare("INSERT INTO companies (name, website, address, source) VALUES (:name, :website, :address, :source)");
        foreach ($testData as $data) {
            $insertStmt->execute([
                ':name'    => $data['name'],
                ':website' => $data['website'],
                ':address' => $data['address'],
                ':source'  => $data['source']
            ]);
        }
        echo "Test data inserted.\n";
    }
}

generateTestData($pdo);

// Fetch Companies from Database
function fetchCompanies(PDO $pdo) {
    $stmt = $pdo->query("SELECT * FROM companies");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>