CREATE TABLE companies (
id SERIAL PRIMARY KEY,
name VARCHAR(255),
website VARCHAR(255),
address TEXT,
source VARCHAR(50), -- Es: 'API_1', 'SCRAPER_2', 'MANUAL'
inserted_at TIMESTAMP DEFAULT NOW()
);
CREATE TABLE normalized_companies (
id SERIAL PRIMARY KEY,
name VARCHAR(255) UNIQUE,
canonical_website VARCHAR(255),
address TEXT
--I would consider adding a source variable in this table also, so to pass the source that the company got scraped before narmolizing.
);

SELECT 
    LOWER(name) AS normalized_name, --this will do for companies with identical names, can't really conceptualize checking for "similar names" since companieswith similar names are still different companies.
    COUNT(*) AS occurrence_count,
    ARRAY_AGG(DISTINCT source) AS sources
FROM companies
GROUP BY LOWER(name)
HAVING COUNT(*) > 1;

WITH ranked_companies AS (
    SELECT 
        *,
        ROW_NUMBER() OVER (
            PARTITION BY LOWER(name)
            ORDER BY 
                CASE 
                    WHEN source ILIKE 'MANUAL%' THEN 1 --Assuming that there might also be multiple manuals, like 'MANUAL_3', 'MANUAL_4', etc.
                    WHEN source ILIKE 'API%' THEN 2
                    WHEN source ILIKE 'SCRAPER%' THEN 3
                    ELSE 4
                END,
                inserted_at ASC  -- if you want to use the earliest entry when sources are equal, maybe not needed
        ) AS rn
    FROM companies
)
INSERT INTO normalized_companies (name, canonical_website, address)
SELECT 
    name,
    website,
    address
FROM ranked_companies
WHERE rn = 1; --That will always be the highest priority source availiable.

--This query counts how many companies were collected from each source and orders the results from the highest count to the lowest.
SELECT 
    source, 
    COUNT(*) AS company_count
FROM companies
GROUP BY source
ORDER BY company_count DESC;

