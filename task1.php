<?php

class CompanyClass
{
    public function normalizeCompanyData(array $data): ?array
    {
        // Validate required fields
        if (!$this->isCompanyDataValid($data)) {
            return null;
        }

        $normalized = [];

        // Normalize company name: trim whitespace and convert to lowercase
        $normalized['name'] = strtolower(trim($data['name']));

        // Normalize website (if provided)
        if (!empty($data['website'])) {
            $website = trim($data['website']);
            // Check if website URL starts with http:// or https://
            if (preg_match('/^https?:\/\//i', $website)) {
                $normalized['website'] = parse_url($website, PHP_URL_HOST);
            } else {
                $normalized['website'] = $website;
            }

            // Remove website key if normalization results in an empty value
            if (empty($normalized['website'])) {
                unset($normalized['website']);
            }
        }

        // Normalize address if provided
        if (isset($data['address'])) {
            $address = trim($data['address']);
            if ($address !== '') {
                $normalized['address'] = $address;
            } else {
                $normalized['address'] = null;
            }            
        } else {
            $normalized['address'] = null;
        }

        return $normalized;
    }

    private function isCompanyDataValid(array $data): bool
    {
        // Ensure required fields exist: name and address are expected
        return isset($data['name']) && isset($data['address']);
    }
}

// Test Data
$input = [
    'name' => ' OpenAI ',
    'website' => 'https://openai.com ',
    'address' => ' '
];

$input2 = [
    'name' => 'Innovatiespotter',
    'address' => 'Groningen'
];

$input3 = [
    'name' => ' Apple ',
    'website' => 'xhttps://apple.com ',
];



$company = new CompanyClass();

$result = $company->normalizeCompanyData($input);
var_dump($result);

$result2 = $company->normalizeCompanyData($input2);
var_dump($result2);

$result3 = $company->normalizeCompanyData($input3);
var_dump($result3);
