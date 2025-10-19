import puppeteer from 'puppeteer';

async function testCategoryEdit() {
  const browser = await puppeteer.launch({ 
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  const page = await browser.newPage();

  // Enable console logging
  page.on('console', msg => {
    if (msg.text().includes('Category data loaded:') || msg.text().includes('Resetting form')) {
      console.log('Browser console:', msg.text());
    }
  });

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
    
    // Wait for the form to potentially load
    await new Promise(resolve => setTimeout(resolve, 3000));
    
    // Check current URL
    const currentUrl = page.url();
    console.log('Current URL:', currentUrl);
    
    if (currentUrl.includes('/login')) {
      console.log('Still on login page, authentication failed');
      await browser.close();
      return;
    }
    
    // Wait for form fields
    try {
      await page.waitForSelector('input[name="title_az"]', { timeout: 5000 });
      console.log('Form loaded successfully');
    } catch (e) {
      console.log('Form did not load, checking page content...');
      const pageTitle = await page.title();
      console.log('Page title:', pageTitle);
    }
    
    // Get all input values
    const formValues = await page.evaluate(() => {
      const values = {};
      const inputs = document.querySelectorAll('input[name], select[name], textarea[name]');
      inputs.forEach(input => {
        if (input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement) {
          values[input.name] = input.value;
        }
      });
      return values;
    });
    
    console.log('\n3. Form field values:');
    console.log('-------------------');
    const importantFields = ['title_az', 'title_en', 'title_ru', 'slug', 'order', 'status'];
    
    importantFields.forEach(field => {
      if (formValues[field] !== undefined) {
        console.log(`${field}: "${formValues[field]}" ${formValues[field] ? '✓' : '[EMPTY]'}`);
      }
    });
    
    // Check console logs from the page
    const logs = await page.evaluate(() => {
      // Try to access React component data
      const root = document.querySelector('#root');
      if (root && root._reactRootContainer) {
        return 'React app is mounted';
      }
      return 'React app status unknown';
    });
    console.log('\n4. React status:', logs);
    
    // Check if any main fields have values
    const hasValues = formValues.title_az || formValues.title_en || formValues.title_ru || formValues.slug;
    
    if (hasValues) {
      console.log('\n✅ SUCCESS: Form fields are populated with data!');
    } else {
      console.log('\n❌ ISSUE: All form fields are empty!');
      
      // Check network requests
      const requests = [];
      page.on('response', response => {
        if (response.url().includes('categories')) {
          requests.push({
            url: response.url(),
            status: response.status()
          });
        }
      });
      
      // Reload to capture network activity
      await page.reload();
      await new Promise(resolve => setTimeout(resolve, 2000));
      
      if (requests.length > 0) {
        console.log('\n5. API requests made:');
        requests.forEach(req => {
          console.log(`  - ${req.url} (Status: ${req.status})`);
        });
      }
    }
    
  } catch (error) {
    console.error('Error:', error.message);
  } finally {
    await browser.close();
  }
}

testCategoryEdit();