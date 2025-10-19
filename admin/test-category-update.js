import puppeteer from 'puppeteer';

async function testCategoryUpdate() {
  const browser = await puppeteer.launch({ 
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  const page = await browser.newPage();

  try {
    console.log('1. Logging in to admin panel...');
    await page.goto('http://localhost:5175/admin/login');
    
    // Fill login form
    await page.type('input[name="email"]', 'admin@example.com');
    await page.type('input[name="password"]', 'password123');
    
    // Submit login
    await page.click('button[type="submit"]');
    await page.waitForNavigation();
    
    console.log('2. Navigating to category edit page...');
    await page.goto('http://localhost:5175/admin/news-categories/1/edit');
    
    // Wait for form to load
    await page.waitForSelector('input[name="title_az"]', { timeout: 5000 });
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    // Clear and update the Azerbaijani title
    const titleAzInput = await page.$('input[name="title_az"]');
    await titleAzInput.click({ clickCount: 3 }); // Select all
    await titleAzInput.type('Maliyyə - Yenilənmiş');
    
    // Update the slug
    const slugInput = await page.$('input[name="slug"]');
    await slugInput.click({ clickCount: 3 });
    await slugInput.type('maliyye-yenilenmis');
    
    console.log('3. Submitting the form...');
    
    // Click update button
    await page.click('button[type="submit"]');
    
    // Wait for navigation or response
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    // Check if we're redirected to the list page
    const currentUrl = page.url();
    console.log('Current URL after save:', currentUrl);
    
    if (currentUrl.includes('/news-categories') && !currentUrl.includes('/edit')) {
      console.log('✅ SUCCESS: Redirected to list page after update!');
      
      // Check if the updated category appears in the list
      const categoryTitle = await page.evaluate(() => {
        const firstRow = document.querySelector('tbody tr');
        if (!firstRow) return null;
        const titleCell = firstRow.querySelector('td:nth-child(2)');
        return titleCell ? titleCell.textContent.trim() : null;
      });
      
      console.log('First category in list:', categoryTitle);
    } else {
      console.log('Still on edit page, checking for errors...');
      
      const errorMessages = await page.evaluate(() => {
        const errors = document.querySelectorAll('.text-red-600');
        return Array.from(errors).map(e => e.textContent).join(', ');
      });
      
      if (errorMessages) {
        console.log('Error messages:', errorMessages);
      }
    }
    
    // Verify via API
    console.log('\n4. Verifying via API...');
    const response = await page.evaluate(() => {
      return fetch('http://localhost:8000/api/admin/categories/1', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('admin_token')}`
        }
      }).then(r => r.json());
    });
    
    console.log('Category data from API:');
    console.log('  Title (AZ):', response.title?.az || response.title);
    console.log('  Slug:', response.slug);
    
  } catch (error) {
    console.error('Error:', error.message);
  } finally {
    await browser.close();
  }
}

testCategoryUpdate();