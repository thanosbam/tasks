// This uses the built-in fetch available in Node 18+.

async function downloadAndCombine(apiUrls) {
    // Map each URL to a fetch promise
    const results = await Promise.all(apiUrls.map(async (url) => {
      try {
        const response = await fetch(url);
        if (!response.ok) {
          throw new Error(`HTTP error: ${response.status}`);
        }
        // Parse and return JSON data
        return await response.json();
      } catch (error) {
        console.error(`Error fetching ${url}: ${error.message}`);
        // Return an empty array if there's an error
        return [];
      }
    }));
  
    // Flatten the array of arrays into a single array, may not be needed but I believe it may provide readability at least on some occasions. 
    return results.flat();
  }
  
  // Example usage:
  const apiUrls = [
    'https://api.example.com/data1',
    'https://api.example.com/data2',
    'https://api.example.com/data3'
  ];
  
  downloadAndCombine(apiUrls)
    .then(combinedData => {
      console.log('Combined Data:', combinedData);
    })
    .catch(err => {
      console.error('Unexpected error:', err);
    });
  